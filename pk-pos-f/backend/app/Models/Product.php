<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'barcode',
        'hs_code',
        'unit',
        'price_minor',
        'cost_minor',
        'tax_category',
        'is_active',
        'product_meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'cost_minor' => 'integer',
            'is_active' => 'boolean',
            'product_meta' => 'array',
        ];
    }

    /**
     * Get the variants for the product.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /**
     * Get the sale items referencing this product.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'product_id');
    }

    /**
     * Get UOM packaging conversions for this product (e.g. cartons, boxes).
     */
    public function uomConversions(): HasMany
    {
        return $this->hasMany(ProductUomConversion::class, 'product_id');
    }

    /**
     * Get price list tier entries for this product.
     */
    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'product_id');
    }

    /**
     * Get stock levels across physical locations.
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'product_id');
    }

    /**
     * Resolve selling price for a specific UOM packaging unit.
     */
    public function getPriceForUnit(string $unitCode): int
    {
        if (strtolower($unitCode) === strtolower($this->unit) || strtolower($unitCode) === 'pcs') {
            return $this->price_minor;
        }

        $conversion = $this->uomConversions()
            ->where('is_active', true)
            ->where(function ($q) use ($unitCode) {
                $q->where('unit_code', $unitCode)
                    ->orWhere('unit_name', $unitCode);
            })
            ->first();

        return $conversion ? $conversion->price_minor : $this->price_minor;
    }
}
