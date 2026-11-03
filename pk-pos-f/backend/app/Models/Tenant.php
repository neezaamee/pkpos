<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'ntn',
        'strn',
        'province',
        'fbr_pos_id',
        'suspended_at',
        'suspension_reason',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Determine if the tenant is currently suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Get the users for the tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * Get the locations for the tenant.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'tenant_id');
    }

    /**
     * Get the POS terminals for the tenant.
     */
    public function terminals(): HasMany
    {
        return $this->hasMany(PosTerminal::class, 'tenant_id');
    }

    /**
     * Get all subscriptions for the tenant.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id');
    }

    /**
     * Get the current active subscription for the tenant.
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->withoutGlobalScopes()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * Get the active plan for the tenant.
     */
    public function currentPlan(): ?Plan
    {
        return $this->currentSubscription()?->plan;
    }

    /**
     * Check if the tenant can add an additional branch location under their plan quota.
     */
    public function canAddLocation(): bool
    {
        $plan = $this->currentPlan();
        if (! $plan || $plan->max_locations === null) {
            return true;
        }

        $currentCount = $this->locations()->withoutGlobalScopes()->count();
        return $currentCount < $plan->max_locations;
    }

    /**
     * Check if the tenant can register or pair an additional terminal under their plan quota.
     */
    public function canAddTerminal(): bool
    {
        $plan = $this->currentPlan();
        if (! $plan || $plan->max_terminals === null) {
            return true;
        }

        $activeTerminalsCount = $this->terminals()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where('pairing_status', '!=', 'revoked')
            ->count();

        return $activeTerminalsCount < $plan->max_terminals;
    }

    /**
     * Get the sales for the tenant.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'tenant_id');
    }

    /**
     * Get the FBR credentials for the tenant.
     */
    public function fbrCredential(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FbrCredential::class, 'tenant_id');
    }
}
