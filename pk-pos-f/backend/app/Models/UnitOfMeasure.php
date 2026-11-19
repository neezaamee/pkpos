<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'code',
        'allows_decimals',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allows_decimals' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get conversions associated with this UOM.
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(ProductUomConversion::class, 'uom_id');
    }
}
