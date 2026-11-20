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
        'drap_registration_number',
        'generic_name',
        'dosage_form',
        'strength',
        'manufacturer',
        'is_prescription_required',
        'is_narcotic',
        'cold_chain',
        'rack_location',
        'kitchen_station_id',
        'preparation_time_minutes',
        'is_kitchen_item',
        'dimensional_unit',
        'weight_kg_per_unit',
        'sqft_per_box',
        'length_feet_per_piece',
        'requires_delivery_challan',
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
            'is_prescription_required' => 'boolean',
            'is_narcotic' => 'boolean',
            'cold_chain' => 'boolean',
            'requires_delivery_challan' => 'boolean',
            'weight_kg_per_unit' => 'decimal:3',
            'sqft_per_box' => 'decimal:3',
            'length_feet_per_piece' => 'decimal:3',
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
     * Get pharmaceutical batches for this medicine.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'product_id')->orderBy('expiry_date', 'asc');
    }

    /**
     * Get active non-expired batches ordered by FEFO (First Expired, First Out).
     */
    public function activeBatches(): HasMany
    {
        return $this->batches()
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->where('expiry_date', '>=', now()->toDateString())
            ->orderBy('expiry_date', 'asc');
    }

    /**
     * Get the single earliest-expiring active batch with available stock (FEFO).
     */
    public function fefoBatch(?string $locationId = null): ?ProductBatch
    {
        $query = $this->activeBatches();
        if ($locationId) {
            $query->where('location_id', $locationId);
        }
        return $query->first();
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

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    public function modifierGroups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_group')->with('options');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->where('is_active', true)->orderBy('min_quantity');
    }

    public function getTieredPriceForQuantity(float $quantity): int
    {
        $tiers = $this->priceTiers;
        foreach ($tiers as $tier) {
            if ($tier->matchesQuantity($quantity)) {
                return (int) $tier->unit_price_minor;
            }
        }
        return (int) $this->price_minor;
    }
}
