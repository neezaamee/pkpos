<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Supplier extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $table = 'suppliers';

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'cnic',
        'ntn',
        'strn',
        'address',
        'city',
        'payment_terms_days',
        'current_balance_minor',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_balance_minor' => 'integer',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Purchase orders linked to this supplier.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id')->latest();
    }

    /**
     * Accounts payable transactions (Supplier Khata).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class, 'supplier_id')->latest();
    }

    /**
     * Record an incoming invoice / bill from the supplier (Increases payable balance).
     */
    public function recordBill(int $amountMinor, ?PurchaseOrder $po = null, ?User $user = null, ?string $notes = null): SupplierTransaction
    {
        return DB::transaction(function () use ($amountMinor, $po, $user, $notes) {
            $this->lockForUpdate();
            $newBalance = $this->current_balance_minor + $amountMinor;
            $this->update(['current_balance_minor' => $newBalance]);

            $dateNum = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $receiptNumber = "BILL-{$dateNum}-{$random}";

            return SupplierTransaction::create([
                'tenant_id' => $this->tenant_id,
                'supplier_id' => $this->id,
                'purchase_order_id' => $po?->id,
                'type' => 'bill_received',
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $newBalance,
                'receipt_number' => $receiptNumber,
                'recorded_by_user_id' => $user?->id,
                'notes' => $notes ?? ($po ? "Invoice for PO #{$po->po_number}" : 'Goods bill received'),
            ]);
        });
    }

    /**
     * Record a payment made to the supplier (Reduces payable balance).
     */
    public function recordPayment(
        int $amountMinor,
        string $paymentMethod = 'bank_transfer',
        ?User $user = null,
        ?string $notes = null,
        ?string $refNumber = null
    ): SupplierTransaction {
        return DB::transaction(function () use ($amountMinor, $paymentMethod, $user, $notes, $refNumber) {
            $this->lockForUpdate();
            $newBalance = $this->current_balance_minor - $amountMinor;
            $this->update(['current_balance_minor' => $newBalance]);

            $dateNum = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $receiptNumber = "VEND-PAY-{$dateNum}-{$random}";

            $transaction = SupplierTransaction::create([
                'tenant_id' => $this->tenant_id,
                'supplier_id' => $this->id,
                'type' => 'payment_made',
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $newBalance,
                'payment_method' => $paymentMethod,
                'receipt_number' => $receiptNumber,
                'reference_number' => $refNumber,
                'recorded_by_user_id' => $user?->id,
                'notes' => $notes,
            ]);

            // Auto-post to General Ledger
            try {
                app(\App\Services\AccountingService::class)->postSupplierPayment($this, $amountMinor, $paymentMethod, $refNumber, $user);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Accounting auto-post skipped for supplier payment: " . $e->getMessage());
            }

            return $transaction;
        });
    }
}
