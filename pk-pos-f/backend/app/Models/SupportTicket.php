<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'support_tickets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket_number',
        'tenant_id',
        'user_id',
        'assigned_to_user_id',
        'subject',
        'category',
        'priority',
        'status',
        'diagnostic_snapshot',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diagnostic_snapshot' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * User who submitted this ticket.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Super Admin staff member assigned to resolve this ticket.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Discussion thread and internal notes for this ticket.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('created_at', 'asc');
    }

    /**
     * Build an automated diagnostic snapshot for this merchant and terminal environment.
     */
    public static function buildDiagnosticSnapshot(Tenant $tenant, ?PosTerminal $terminal = null, ?string $fbrError = null, int $pendingOutbox = 0): array
    {
        return [
            'tenant' => [
                'name' => $tenant->name,
                'ntn' => $tenant->fbrCredential?->ntn ?? $tenant->ntn ?? 'N/A',
                'strn' => $tenant->fbrCredential?->strn ?? $tenant->strn ?? 'N/A',
                'province' => strtoupper($tenant->province),
                'fbr_pos_id' => $tenant->fbrCredential?->pos_id ?? $tenant->fbr_pos_id ?? 'N/A',
                'active_plan' => $tenant->currentPlan()?->name ?? 'Starter',
            ],
            'terminal' => [
                'terminal_code' => $terminal?->terminal_code ?? 'WEB-ADMIN',
                'hostname' => $terminal?->hostname ?? 'N/A',
                'hardware_uuid' => $terminal?->hardware_uuid ?? 'N/A',
                'platform_os' => $terminal?->platform_os ?? 'windows',
                'app_version' => $terminal?->app_version ?? '0.2.0',
            ],
            'telemetry' => [
                'last_fbr_error' => $fbrError ?: 'None reported',
                'pending_outbox_count' => $pendingOutbox,
                'captured_at' => now()->toIso8601String(),
            ],
        ];
    }
}

