<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sale_return_items';

    protected $fillable = [
        'id',
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'unit_code',
        'unit_conversion_factor',
        'quantity_returned',
        'unit_price_minor',
        'tax_rate_basis_points',
        'tax_minor',
        'refund_amount_minor',
        'restock_condition',
    ];

    protected function casts(): array
    {
        return [
            'unit_conversion_factor' => 'float',
            'quantity_returned' => 'float',
            'unit_price_minor' => 'integer',
            'tax_rate_basis_points' => 'integer',
            'tax_minor' => 'integer',
            'refund_amount_minor' => 'integer',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Compute actual base units represented by this returned quantity.
     */
    public function getBaseQuantityReturnedAttribute(): float
    {
        return (float) ($this->quantity_returned * ($this->unit_conversion_factor ?: 1.0));
    }
}
