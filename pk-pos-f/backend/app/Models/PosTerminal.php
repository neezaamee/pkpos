<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosTerminal extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pos_terminals';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'location_id',
        'terminal_code',
        'name',
        'hardware_uuid',
        'hostname',
        'platform_os',
        'app_version',
        'ip_address',
        'pairing_token',
        'pairing_token_expires_at',
        'pairing_status',
        'is_active',
        'revocation_reason',
        'last_heartbeat_at',
        'last_sync_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pairing_token_expires_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the location branch that this terminal is assigned to.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Determine if the terminal is currently online (heartbeat within 15 minutes).
     */
    public function isOnline(): bool
    {
        return $this->last_heartbeat_at && $this->last_heartbeat_at->greaterThanOrEqualTo(now()->subMinutes(15));
    }
}

