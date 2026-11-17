<?php

use App\Mail\SubscriptionRenewalNoticeMail;
use App\Mail\SubscriptionSuspendedMail;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->plan = Plan::create([
        'name' => 'Professional Growth',
        'code' => 'pro-growth',
        'price_monthly' => 500000,
        'price_yearly' => 5000000,
        'max_locations' => 3,
        'max_terminals' => 5,
        'max_staff' => 10,
        'max_skus' => 1000,
        'is_active' => true,
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Chughtai Pharmacies',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'email' => 'director@chughtaipharmacy.test',
    ]);
});

test('dry-run simulation mode does not modify subscription statuses or dispatch emails', function () {
    Mail::fake();

    $sub = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'trialing',
        'starts_at' => Carbon::now()->subDays(15),
        'trial_ends_at' => Carbon::now()->subDay(),
    ]);

    $exitCode = Artisan::call('subscriptions:check-lifecycle', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);

    $sub->refresh();
    expect($sub->status)->toBe('trialing'); // Unchanged in dry-run
    Mail::assertNothingQueued();
});

test('expired trial transitions to past_due when no paid period is present', function () {
    $sub = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'trialing',
        'starts_at' => Carbon::now()->subDays(14),
        'trial_ends_at' => Carbon::now()->subHours(2),
    ]);

    Artisan::call('subscriptions:check-lifecycle');

    $sub->refresh();
    expect($sub->status)->toBe('past_due');
});

test('expired trial transitions to active when a paid period is present', function () {
    $sub = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'trialing',
        'starts_at' => Carbon::now()->subDays(14),
        'trial_ends_at' => Carbon::now()->subHours(2),
        'ends_at' => Carbon::now()->addDays(30),
    ]);

    Artisan::call('subscriptions:check-lifecycle');

    $sub->refresh();
    expect($sub->status)->toBe('active');
});

test('expired active subscription transitions to past_due', function () {
    $sub = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => Carbon::now()->subMonth(),
        'ends_at' => Carbon::now()->subMinutes(15),
    ]);

    Artisan::call('subscriptions:check-lifecycle');

    $sub->refresh();
    expect($sub->status)->toBe('past_due');
});

test('expiring subscriptions dispatch renewal warning notice to tenant owner', function () {
    Mail::fake();

    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => Carbon::now()->subMonth(),
        'ends_at' => Carbon::now()->addDays(3)->startOfDay()->addHours(12),
    ]);

    Artisan::call('subscriptions:check-lifecycle');

    Mail::assertQueued(SubscriptionRenewalNoticeMail::class, function ($mail) {
        return $mail->hasTo('director@chughtaipharmacy.test') && $mail->daysRemaining === 3;
    });
});

test('past due subscription past the grace period triggers automated tenant lockout and audit log', function () {
    Mail::fake();

    $overdueSub = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'past_due',
        'starts_at' => Carbon::now()->subMonths(2),
        'ends_at' => Carbon::now()->subDays(8), // 8 days overdue (> 7-day grace period)
    ]);

    expect($this->tenant->is_suspended)->toBeFalse();

    Artisan::call('subscriptions:check-lifecycle');

    $overdueSub->refresh();
    $this->tenant->refresh();

    // Verify subscription status is expired
    expect($overdueSub->status)->toBe('expired');

    // Verify tenant is suspended
    expect($this->tenant->is_suspended)->toBeTrue();
    expect($this->tenant->suspension_reason)->toContain('grace period elapsed');

    // Verify suspension notification email queued
    Mail::assertQueued(SubscriptionSuspendedMail::class, function ($mail) {
        return $mail->hasTo('director@chughtaipharmacy.test');
    });

    // Verify system audit trail
    $auditLog = AuditLog::where('event', 'tenant_auto_suspended_overdue')
        ->where('tenant_id', $this->tenant->id)
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->new_values['subscription_id'])->toBe($overdueSub->id);
});
