<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'price_lists';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'code',
        'description',
        'currency',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get items and volume tiers for this price list.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'price_list_id');
    }

    /**
     * Customers assigned to this price list.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'price_list_id');
    }

    /**
     * Resolve tier price for a product based on volume quantity.
     */
    public function resolveProductPrice(Product $product, float $quantity = 1.0): int
    {
        $tier = $this->items()
            ->where('product_id', $product->id)
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($tier) {
            return $tier->price_minor;
        }

        return $product->price_minor;
    }
}
