<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'purchase_order_items';

    protected $fillable = [
        'id',
        'purchase_order_id',
        'product_id',
        'unit_code',
        'unit_conversion_factor',
        'ordered_quantity',
        'received_quantity',
        'cost_price_minor',
        'tax_rate_basis_points',
        'tax_minor',
        'line_total_minor',
    ];

    protected function casts(): array
    {
        return [
            'unit_conversion_factor' => 'float',
            'ordered_quantity' => 'float',
            'received_quantity' => 'float',
            'cost_price_minor' => 'integer',
            'tax_rate_basis_points' => 'integer',
            'tax_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Compute actual base units represented by this line item.
     */
    public function getBaseOrderedQuantityAttribute(): float
    {
        return (float) ($this->ordered_quantity * ($this->unit_conversion_factor ?: 1.0));
    }

    public function getBaseReceivedQuantityAttribute(): float
    {
        return (float) ($this->received_quantity * ($this->unit_conversion_factor ?: 1.0));
    }
}
