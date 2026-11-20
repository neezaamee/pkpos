<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegisterSession extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'terminal_id',
        'user_id',
        'closed_by_user_id',
        'z_report_number',
        'status',
        'opened_at',
        'closed_at',
        'opening_float_minor',
        'cash_sales_minor',
        'card_sales_minor',
        'raast_sales_minor',
        'wallet_sales_minor',
        'cash_in_minor',
        'cash_drop_minor',
        'payout_minor',
        'refund_cash_minor',
        'expected_cash_minor',
        'closing_cash_minor',
        'variance_minor',
        'sales_count',
        'refunds_count',
        'tax_collected_minor',
        'gross_sales_minor',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float_minor' => 'integer',
            'cash_sales_minor' => 'integer',
            'card_sales_minor' => 'integer',
            'raast_sales_minor' => 'integer',
            'wallet_sales_minor' => 'integer',
            'cash_in_minor' => 'integer',
            'cash_drop_minor' => 'integer',
            'payout_minor' => 'integer',
            'refund_cash_minor' => 'integer',
            'expected_cash_minor' => 'integer',
            'closing_cash_minor' => 'integer',
            'variance_minor' => 'integer',
            'sales_count' => 'integer',
            'refunds_count' => 'integer',
            'tax_collected_minor' => 'integer',
            'gross_sales_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'terminal_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashDrawerTransaction::class, 'register_session_id')->orderBy('created_at', 'asc');
    }

    /**
     * Recalculates expected cash in drawer based on opening float + cash sales + cash in - cash drops - payouts - cash refunds.
     */
    public function recalculateExpectedCash(): int
    {
        $expected = $this->opening_float_minor
            + $this->cash_sales_minor
            + $this->cash_in_minor
            - $this->cash_drop_minor
            - $this->payout_minor
            - $this->refund_cash_minor;

        $this->expected_cash_minor = $expected;
        return $expected;
    }

    /**
     * Record a sale in this register session (supports single and split tenders).
     */
    public function recordSale(Sale $sale): void
    {
        $this->sales_count += 1;
        $this->gross_sales_minor += $sale->total_minor;
        $this->tax_collected_minor += $sale->tax_minor;

        $cashAmount = 0;
        $payments = $sale->payments()->get();

        if ($sale->isSplitPayment() || $payments->isNotEmpty()) {
            foreach ($payments as $payment) {
                switch ($payment->payment_method) {
                    case 'cash':
                        $this->cash_sales_minor += $payment->amount_minor;
                        $cashAmount += $payment->amount_minor;
                        break;
                    case 'card':
                        $this->card_sales_minor += $payment->amount_minor;
                        break;
                    case 'raast':
                        $this->raast_sales_minor += $payment->amount_minor;
                        break;
                    case 'wallet':
                        $this->wallet_sales_minor += $payment->amount_minor;
                        break;
                    // Note: credit (Khata) and cheque do not increment POS cash drawer or digital card balances
                }
            }
        } else {
            switch ($sale->payment_method) {
                case 'cash':
                    $this->cash_sales_minor += $sale->total_minor;
                    $cashAmount = $sale->total_minor;
                    break;
                case 'card':
                    $this->card_sales_minor += $sale->total_minor;
                    break;
                case 'raast':
                    $this->raast_sales_minor += $sale->total_minor;
                    break;
                case 'wallet':
                    $this->wallet_sales_minor += $sale->total_minor;
                    break;
                case 'credit':
                    // Customer Khata credit does not increment register cash
                    break;
                default:
                    $this->cash_sales_minor += $sale->total_minor;
                    $cashAmount = $sale->total_minor;
                    break;
            }
        }

        $this->recalculateExpectedCash();
        $this->save();

        if ($cashAmount > 0) {
            $this->transactions()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $sale->user_id,
                'sale_id' => $sale->id,
                'type' => 'sale',
                'amount_minor' => $cashAmount,
                'balance_after_minor' => $this->expected_cash_minor,
                'reason' => 'Sale #' . $sale->sale_number . ($sale->isSplitPayment() ? ' (Cash portion)' : ''),
            ]);
        }
    }

    /**
     * Record a cash movement in the drawer (cash_in, cash_drop, payout_expense, refund).
     */
    public function recordCashMovement(string $type, int $amountMinor, User|string|null $user = null, ?string $reason = null, ?string $authorizedByUserId = null): CashDrawerTransaction
    {
        $userId = $user instanceof User ? $user->id : ($user ?? $this->user_id);

        if ($type === 'cash_in') {
            $this->cash_in_minor += $amountMinor;
        } elseif ($type === 'cash_drop') {
            $this->cash_drop_minor += $amountMinor;
        } elseif ($type === 'payout' || $type === 'payout_expense') {
            $this->payout_minor += $amountMinor;
        } elseif ($type === 'refund') {
            $this->refund_cash_minor += $amountMinor;
            $this->refunds_count += 1;
        }

        $this->recalculateExpectedCash();
        $this->save();

        return $this->transactions()->create([
            'tenant_id' => $this->tenant_id,
            'user_id' => $userId,
            'authorized_by_user_id' => $authorizedByUserId,
            'type' => $type === 'payout' ? 'payout_expense' : $type,
            'amount_minor' => $amountMinor,
            'balance_after_minor' => $this->expected_cash_minor,
            'reason' => $reason ?? ucfirst(str_replace('_', ' ', $type)),
        ]);
    }

    /**
     * Close the shift session, compute variance, and generate Z-Report.
     */
    public function closeSession(int $closingCashCountedMinor, User $closedBy, ?string $notes = null): self
    {
        $this->recalculateExpectedCash();
        $this->closing_cash_minor = $closingCashCountedMinor;
        $this->variance_minor = $closingCashCountedMinor - $this->expected_cash_minor;
        $this->closed_at = now();
        $this->closed_by_user_id = $closedBy->id;
        $this->status = 'closed';
        $this->notes = $notes;

        if (! $this->z_report_number) {
            $date = now()->format('Ymd');
            $countToday = static::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant_id)
                ->whereDate('closed_at', today())
                ->count() + 1;
            $this->z_report_number = sprintf('Z-%s-%04d', $date, $countToday);
        }

        $this->save();
        return $this;
    }

    /**
     * Generate thermal-friendly snapshot data for X-Report or Z-Report.
     */
    public function toReportSummary(string $reportType = 'X'): array
    {
        $this->recalculateExpectedCash();

        return [
            'report_type' => $reportType,
            'report_title' => $reportType === 'Z' ? 'END-OF-DAY FISCAL Z-REPORT' : 'MID-SHIFT X-REPORT',
            'session_id' => $this->id,
            'z_report_number' => $this->z_report_number ?? 'X-' . substr($this->id, 0, 8),
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'cashier_name' => $this->user?->name ?? 'Cashier',
            'closed_by_name' => $this->closedByUser?->name,
            'location_name' => $this->location?->name ?? 'Main Store',
            'terminal_code' => $this->terminal?->device_id ?? 'T-01',
            'opening_float_minor' => $this->opening_float_minor,
            'gross_sales_minor' => $this->gross_sales_minor,
            'net_sales_minor' => $this->gross_sales_minor - $this->tax_collected_minor,
            'tax_collected_minor' => $this->tax_collected_minor,
            'sales_count' => $this->sales_count,
            'refunds_count' => $this->refunds_count,
            'payment_breakdown' => [
                'cash_minor' => $this->cash_sales_minor,
                'card_minor' => $this->card_sales_minor,
                'raast_minor' => $this->raast_sales_minor,
                'wallet_minor' => $this->wallet_sales_minor,
            ],
            'cash_drawer_reconciliation' => [
                'opening_float' => $this->opening_float_minor,
                'cash_sales' => $this->cash_sales_minor,
                'cash_in' => $this->cash_in_minor,
                'cash_drops' => $this->cash_drop_minor,
                'payout_expenses' => $this->payout_minor,
                'cash_refunds' => $this->refund_cash_minor,
                'expected_cash' => $this->expected_cash_minor,
                'counted_cash' => $this->closing_cash_minor,
                'variance' => $this->variance_minor,
                'variance_status' => $this->variance_minor === 0
                    ? 'exact'
                    : ($this->variance_minor > 0 ? 'overage' : 'shortage'),
            ],
            'notes' => $this->notes,
        ];
    }
}
