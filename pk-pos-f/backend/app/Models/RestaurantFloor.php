<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantFloor extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'location_id',
        'name',
        'floor_number',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'floor_number' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class, 'floor_id')->orderBy('table_number');
    }
}
