<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingSetting extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'default_cash_account_id',
        'default_bank_account_id',
        'default_ar_account_id',
        'default_inventory_account_id',
        'default_ap_account_id',
        'default_sales_tax_account_id',
        'default_sales_revenue_account_id',
        'default_sales_return_account_id',
        'default_sales_discount_account_id',
        'default_cogs_account_id',
        'default_retained_earnings_account_id',
    ];

    public function defaultCashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_cash_account_id');
    }

    public function defaultBankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_bank_account_id');
    }

    public function defaultArAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_ar_account_id');
    }

    public function defaultInventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_inventory_account_id');
    }

    public function defaultApAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_ap_account_id');
    }

    public function defaultSalesTaxAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_sales_tax_account_id');
    }

    public function defaultSalesRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_sales_revenue_account_id');
    }

    public function defaultSalesReturnAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_sales_return_account_id');
    }

    public function defaultSalesDiscountAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_sales_discount_account_id');
    }

    public function defaultCogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_cogs_account_id');
    }

    public function defaultRetainedEarningsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_retained_earnings_account_id');
    }
}
