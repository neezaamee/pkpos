<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'cnic',
        'ntn',
        'strn',
        'address',
        'city',
        'price_list_id',
        'credit_limit_minor',
        'current_balance_minor',
        'loyalty_points',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit_minor' => 'integer',
            'current_balance_minor' => 'integer',
            'loyalty_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id')->latest();
    }

    public function priceList(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerTransaction::class, 'customer_id')->orderBy('created_at', 'asc');
    }

    /**
     * Check if customer can accept additional credit without exceeding ceiling.
     */
    public function canAcceptCredit(int $additionalMinor): bool
    {
        if ($this->credit_limit_minor <= 0) {
            // 0 limit means no credit allowed without explicit limit
            return false;
        }

        return ($this->current_balance_minor + $additionalMinor) <= $this->credit_limit_minor;
    }

    /**
     * Charge a credit sale (Udhaar) to this customer's account ledger.
     */
    public function chargeCreditSale(Sale $sale, int $amountMinor, ?User $user = null): CustomerTransaction
    {
        return DB::transaction(function () use ($sale, $amountMinor, $user) {
            $this->current_balance_minor += $amountMinor;
            $this->save();

            return $this->transactions()->create([
                'tenant_id' => $this->tenant_id,
                'sale_id' => $sale->id,
                'recorded_by_user_id' => $user?->id,
                'type' => 'debit_sale',
                'amount_minor' => $amountMinor,
                'running_balance_minor' => $this->current_balance_minor,
                'payment_method' => 'credit',
                'receipt_number' => 'INV-' . $sale->sale_number,
                'notes' => 'Credit sale Invoice #' . $sale->sale_number,
            ]);
        });
    }

    /**
     * Record a debt settlement payment from the customer.
     */
    public function recordPayment(
        int $amountMinor,
        string $paymentMethod = 'cash',
        ?User $user = null,
        ?string $notes = null
    ): CustomerTransaction {
        return DB::transaction(function () use ($amountMinor, $paymentMethod, $user, $notes) {
            $this->current_balance_minor -= $amountMinor;
            $this->save();

            $date = now()->format('Ymd');
            $countToday = CustomerTransaction::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant_id)
                ->where('type', 'credit_payment')
                ->whereDate('created_at', today())
                ->count() + 1;
            $receiptNumber = sprintf('REC-%s-%04d', $date, $countToday);

            return $this->transactions()->create([
                'tenant_id' => $this->tenant_id,
                'recorded_by_user_id' => $user?->id,
                'type' => 'credit_payment',
                'amount_minor' => $amountMinor,
                'running_balance_minor' => $this->current_balance_minor,
                'payment_method' => $paymentMethod,
                'receipt_number' => $receiptNumber,
                'notes' => $notes ?? 'Customer debt repayment',
            ]);
        });
    }

    /**
     * Audit and recalculate balance from all historical ledger transactions.
     */
    public function recalculateBalance(): int
    {
        $debits = $this->transactions()->whereIn('type', ['debit_sale', 'debit_adjustment'])->sum('amount_minor');
        $credits = $this->transactions()->whereIn('type', ['credit_payment', 'credit_return'])->sum('amount_minor');

        $computedBalance = (int) ($debits - $credits);
        $this->current_balance_minor = $computedBalance;
        $this->save();

        return $computedBalance;
    }
}
