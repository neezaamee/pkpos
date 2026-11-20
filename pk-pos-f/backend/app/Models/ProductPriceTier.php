<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceTier extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'product_id',
        'min_quantity',
        'max_quantity',
        'unit_price_minor',
        'discount_percentage',
        'tier_label',
        'is_active',
    ];

    protected $casts = [
        'min_quantity' => 'decimal:3',
        'max_quantity' => 'decimal:3',
        'unit_price_minor' => 'integer',
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Determine if given quantity falls within this price slab.
     */
    public function matchesQuantity(float $quantity): bool
    {
        if ($quantity < (float) $this->min_quantity) {
            return false;
        }

        if ($this->max_quantity !== null && $quantity > (float) $this->max_quantity) {
            return false;
        }

        return true;
    }
}
