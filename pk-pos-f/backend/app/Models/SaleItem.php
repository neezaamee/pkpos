<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class SaleItem extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'sale_id',
        'product_id',
        'product_name',
        'hs_code',
        'unit_code',
        'unit_conversion_factor',
        'quantity',
        'unit_price_minor',
        'tax_rate_basis_points',
        'tax_amount_minor',
        'line_total_minor',
        'discount_type',
        'discount_amount_minor',
        'batch_id',
        'batch_number',
        'expiry_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_conversion_factor' => 'float',
            'unit_price_minor' => 'integer',
            'tax_rate_basis_points' => 'integer',
            'tax_amount_minor' => 'integer',
            'line_total_minor' => 'integer',
            'discount_amount_minor' => 'integer',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Compute actual base inventory units deducted by this line.
     */
    public function getBaseQuantityAttribute(): float
    {
        return (float) ($this->quantity * ($this->unit_conversion_factor ?: 1.0));
    }

    /**
     * Boot model and enforce required hs_code.
     */
    protected static function booted(): void
    {
        static::saving(function (SaleItem $item) {
            if (empty(trim((string) $item->hs_code))) {
                throw new InvalidArgumentException('The hs_code is required for all sale items.');
            }
        });
    }

    /**
     * Get the sale that this item belongs to.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * Get the product that this item references, if any.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the specific batch that this item was dispensed from.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
