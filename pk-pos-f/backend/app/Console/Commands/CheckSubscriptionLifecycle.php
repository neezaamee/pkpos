<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionRenewalNoticeMail;
use App\Mail\SubscriptionSuspendedMail;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckSubscriptionLifecycle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-lifecycle {--dry-run : Simulate execution without mutating database or queuing emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit subscription lifecycles, send renewal notices, and enforce automated lockout after grace period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('Running in DRY-RUN mode. No database updates or emails will be dispatched.');
        }

        $now = Carbon::now();
        $this->info("Executing subscription lifecycle evaluation at {$now->toDateTimeString()}...");

        $stats = [
            'trials_expired' => 0,
            'actives_expired' => 0,
            'reminders_sent' => 0,
            'tenants_suspended' => 0,
        ];

        // 1. Process Trial Expirations
        $stats['trials_expired'] = $this->processTrialExpirations($now, $isDryRun);

        // 2. Process Active Term Expirations
        $stats['actives_expired'] = $this->processActiveExpirations($now, $isDryRun);

        // 3. Send Renewal Warning Reminders (7 days, 3 days, 1 day)
        $stats['reminders_sent'] = $this->sendRenewalReminders($now, $isDryRun);

        // 4. Enforce Grace Period Auto-Suspension
        $stats['tenants_suspended'] = $this->enforceGracePeriodSuspension($now, $isDryRun);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Trials Transitioned to Past Due', $stats['trials_expired']],
                ['Active Subscriptions Transitioned to Past Due', $stats['actives_expired']],
                ['Renewal Notice Emails Queued', $stats['reminders_sent']],
                ['Overdue Accounts Suspended (Grace Period Elapsed)', $stats['tenants_suspended']],
            ]
        );

        $this->info('Subscription lifecycle audit completed successfully.');
        return Command::SUCCESS;
    }

    /**
     * Transition expired trials to past_due (or active if an unexpired paid ends_at is present).
     */
    protected function processTrialExpirations(Carbon $now, bool $isDryRun): int
    {
        $count = 0;
        $expiredTrials = Subscription::withoutGlobalScopes()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->get();

        foreach ($expiredTrials as $sub) {
            $hasPaidPeriod = $sub->ends_at && $sub->ends_at->isFuture();
            $newStatus = $hasPaidPeriod ? 'active' : 'past_due';

            $this->line("Trial expired for subscription {$sub->id} (Tenant: {$sub->tenant_id}) -> transitioning to {$newStatus}");

            if (! $isDryRun) {
                $sub->update(['status' => $newStatus]);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Transition expired active subscriptions to past_due.
     */
    protected function processActiveExpirations(Carbon $now, bool $isDryRun): int
    {
        $count = 0;
        $expiredActives = Subscription::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->get();

        foreach ($expiredActives as $sub) {
            $this->line("Subscription term expired for {$sub->id} (Tenant: {$sub->tenant_id}) -> transitioning to past_due");

            if (! $isDryRun) {
                $sub->update(['status' => 'past_due']);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Send email reminders to tenant owners for subscriptions expiring in 7, 3, or 1 days.
     */
    protected function sendRenewalReminders(Carbon $now, bool $isDryRun): int
    {
        $remindersCount = 0;
        $noticeWindows = [7, 3, 1];

        foreach ($noticeWindows as $days) {
            $windowStart = $now->copy()->addDays($days)->startOfDay();
            $windowEnd = $now->copy()->addDays($days)->endOfDay();

            $expiringSubs = Subscription::withoutGlobalScopes()
                ->with(['tenant', 'plan'])
                ->whereIn('status', ['active', 'trialing'])
                ->where(function ($query) use ($windowStart, $windowEnd) {
                    $query->whereBetween('ends_at', [$windowStart, $windowEnd])
                        ->orWhere(function ($q2) use ($windowStart, $windowEnd) {
                            $q2->whereNull('ends_at')
                                ->whereBetween('trial_ends_at', [$windowStart, $windowEnd]);
                        });
                })
                ->get();

            foreach ($expiringSubs as $sub) {
                $tenant = $sub->tenant;
                if (! $tenant || $tenant->is_suspended) {
                    continue;
                }

                $cacheKey = "sub_renewal_notice_{$sub->id}_{$days}d";
                if (Cache::has($cacheKey)) {
                    continue; // Already notified for this window
                }

                $owner = User::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('role', 'owner')
                    ->first();

                if (! $owner) {
                    continue;
                }

                $this->line("Queueing {$days}-day renewal reminder to {$owner->email} for tenant {$tenant->name}");

                if (! $isDryRun) {
                    try {
                        Mail::to($owner->email)->queue(
                            new SubscriptionRenewalNoticeMail($tenant, $owner, $sub, $days)
                        );
                        Cache::put($cacheKey, true, now()->addDays(2));
                    } catch (\Throwable $e) {
                        Log::error("Failed to queue renewal notice to {$owner->email}: " . $e->getMessage());
                    }
                }
                $remindersCount++;
            }
        }

        return $remindersCount;
    }

    /**
     * Suspend tenants whose subscriptions have exceeded the grace period.
     */
    protected function enforceGracePeriodSuspension(Carbon $now, bool $isDryRun): int
    {
        $suspendedCount = 0;
        $graceDays = (int) SystemSetting::get('subscription_grace_period_days', 7);
        $cutoff = $now->copy()->subDays($graceDays);

        $overdueSubs = Subscription::withoutGlobalScopes()
            ->with(['tenant', 'plan'])
            ->where('status', 'past_due')
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($subQ) use ($cutoff) {
                    $subQ->whereNotNull('ends_at')
                        ->where('ends_at', '<=', $cutoff);
                })->orWhere(function ($subQ) use ($cutoff) {
                    $subQ->whereNull('ends_at')
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<=', $cutoff);
                });
            })
            ->get();

        foreach ($overdueSubs as $sub) {
            $tenant = $sub->tenant;
            if (! $tenant) {
                continue;
            }

            $reason = "Subscription past due: {$graceDays}-day grace period elapsed.";

            $this->warn("Locking out tenant '{$tenant->name}' ({$tenant->id}) - Grace period expired");

            if (! $isDryRun) {
                // Update subscription to expired
                $sub->update(['status' => 'expired']);

                if (! $tenant->is_suspended) {
                    $tenant->update([
                        'suspended_at' => $now,
                        'suspension_reason' => $reason,
                    ]);

                    AuditLog::create([
                        'tenant_id' => $tenant->id,
                        'user_id' => null,
                        'event' => 'tenant_auto_suspended_overdue',
                        'auditable_type' => Tenant::class,
                        'auditable_id' => $tenant->id,
                        'new_values' => [
                            'grace_period_days' => $graceDays,
                            'subscription_id' => $sub->id,
                            'suspension_reason' => $reason,
                        ],
                    ]);

                    $owner = User::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('role', 'owner')
                        ->first();

                    if ($owner) {
                        try {
                            Mail::to($owner->email)->queue(
                                new SubscriptionSuspendedMail($tenant, $owner, $sub, $reason)
                            );
                        } catch (\Throwable $e) {
                            Log::error("Failed to queue suspension notice to {$owner->email}: " . $e->getMessage());
                        }
                    }
                }
            }
            $suspendedCount++;
        }

        return $suspendedCount;
    }
}
