<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'kitchen_order_id',
        'sale_item_id',
        'product_id',
        'product_name',
        'quantity',
        'selected_modifiers',
        'cooking_notes',
        'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'selected_modifiers' => 'array',
    ];

    public function kitchenOrder(): BelongsTo
    {
        return $this->belongsTo(KitchenOrder::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
