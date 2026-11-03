<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantApplication extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_applications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'application_number',
        'business_name',
        'trade_name',
        'business_type',
        'sector',
        'province',
        'city',
        'address',
        'contact_name',
        'contact_email',
        'contact_phone',
        'ntn',
        'strn',
        'is_atl_active',
        'atl_checked_at',
        'atl_taxpayer_name',
        'pos_terminal_count',
        'requested_plan_id',
        'document_url',
        'status',
        'rejection_reason',
        'internal_notes',
        'reviewed_by',
        'reviewed_at',
        'provisioned_tenant_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_atl_active' => 'boolean',
            'atl_checked_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'pos_terminal_count' => 'integer',
        ];
    }

    /**
     * The SaaS subscription plan requested by merchant applicant.
     */
    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    /**
     * The platform staff member who adjudicated this application.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The tenant organization provisioned upon approval.
     */
    public function provisionedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'provisioned_tenant_id');
    }

    /**
     * Scope pending review applications.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending_review');
    }

    /**
     * Scope approved applications.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope rejected applications.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Generate an incremental unique application number: e.g. APP-2026-00001.
     */
    public static function generateApplicationNumber(): string
    {
        $year = date('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('APP-%s-%05d', $year, $count);
    }

    /**
     * Execute simulated/automated FBR Active Taxpayer List (ATL) inquiry.
     */
    public function verifyFbrAtlStatus(): bool
    {
        return app(\App\Actions\VerifyFbrAtlAction::class)->execute($this);
    }
}

