<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastAnnouncement extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'broadcast_announcements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'message',
        'level',
        'target_audience',
        'target_province',
        'target_tenant_id',
        'dismissible',
        'is_active',
        'starts_at',
        'expires_at',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dismissible' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The staff member who broadcasted the message.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The target tenant if scoped to a specific business.
     */
    public function targetTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    /**
     * Scope to currently live broadcasts.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    /**
     * Scope broadcasts relevant to a given audience context.
     */
    public function scopeForAudience(Builder $query, ?string $province = null, ?string $tenantId = null, bool $isDesktop = false): Builder
    {
        return $query->where(function (Builder $q) use ($province, $tenantId, $isDesktop) {
            $q->where('target_audience', 'all');

            if ($province) {
                $q->orWhere(function ($sub) use ($province) {
                    $sub->where('target_audience', 'province')
                        ->where('target_province', strtolower($province));
                });
            }

            if ($tenantId) {
                $q->orWhere(function ($sub) use ($tenantId) {
                    $sub->where('target_audience', 'tenant')
                        ->where('target_tenant_id', $tenantId);
                });
            }

            if ($isDesktop) {
                $q->orWhere('target_audience', 'desktop_terminals_only');
            }
        });
    }
}

