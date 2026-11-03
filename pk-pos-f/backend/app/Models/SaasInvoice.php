<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasInvoice extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'saas_invoices';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_number',
        'tenant_id',
        'plan_id',
        'subscription_id',
        'status',
        'billing_cycle',
        'period_start',
        'period_end',
        'subtotal_paisa',
        'tax_paisa',
        'total_paisa',
        'currency',
        'tax_rate',
        'tax_authority',
        'onebill_consumer_number',
        'kuickpay_consumer_number',
        'due_date',
        'paid_at',
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
            'subtotal_paisa' => 'integer',
            'tax_paisa' => 'integer',
            'total_paisa' => 'integer',
            'tax_rate' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The merchant tenant billed.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * The subscription plan billed.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * The associated subscription record.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * Payment transactions and bank wire proofs submitted against this invoice.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'invoice_id');
    }

    /**
     * Scope unpaid invoices.
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', 'unpaid');
    }

    /**
     * Scope invoices awaiting admin wire review.
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'pending_review');
    }

    /**
     * Scope paid invoices.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * Generate an incremental unique invoice number: e.g. INV-2026-00001.
     */
    public static function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('INV-%s-%05d', $year, $count);
    }

    /**
     * Generate a 1Link 1Bill 18-digit consumer number.
     * Prefix: 10042 (PK-POS prefix), Year, Incremental Sequence.
     */
    public static function generateOneBillNumber(): string
    {
        $year = date('y');
        $random = mt_rand(100000000, 999999999);

        return sprintf('10042%s%09d', $year, $random);
    }

    /**
     * Generate a Kuickpay 16-digit consumer number.
     * Prefix: 99100.
     */
    public static function generateKuickpayNumber(): string
    {
        $year = date('y');
        $random = mt_rand(10000000, 99999999);

        return sprintf('99100%s%08d', $year, $random);
    }
}

