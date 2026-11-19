<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUomConversion extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'product_uom_conversions';

    protected $fillable = [
        'id',
        'tenant_id',
        'product_id',
        'uom_id',
        'unit_name',
        'unit_code',
        'barcode',
        'conversion_factor',
        'price_minor',
        'cost_minor',
        'is_default_purchase_unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'float',
            'price_minor' => 'integer',
            'cost_minor' => 'integer',
            'is_default_purchase_unit' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get parent product for this conversion.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get associated UnitOfMeasure if linked.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
