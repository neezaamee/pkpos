<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChallanItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'delivery_challan_id',
        'sale_item_id',
        'product_id',
        'product_name',
        'ordered_quantity',
        'dispatched_quantity',
        'unit_code',
        'weight_kg',
    ];

    protected $casts = [
        'ordered_quantity' => 'decimal:3',
        'dispatched_quantity' => 'decimal:3',
        'weight_kg' => 'decimal:3',
    ];

    protected $appends = [
        'quantity_dispatched',
        'quantity_ordered',
        'unit',
    ];

    public function getQuantityDispatchedAttribute()
    {
        return $this->attributes['dispatched_quantity'] ?? 0;
    }

    public function setQuantityDispatchedAttribute($value): void
    {
        $this->attributes['dispatched_quantity'] = $value;
    }

    public function getQuantityOrderedAttribute()
    {
        return $this->attributes['ordered_quantity'] ?? 0;
    }

    public function setQuantityOrderedAttribute($value): void
    {
        $this->attributes['ordered_quantity'] = $value;
    }

    public function getUnitAttribute(): string
    {
        return $this->attributes['unit_code'] ?? 'pcs';
    }

    public function setUnitAttribute($value): void
    {
        $this->attributes['unit_code'] = $value;
    }

    public function deliveryChallan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'delivery_challan_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
