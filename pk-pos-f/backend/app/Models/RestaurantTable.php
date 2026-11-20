<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantTable extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'location_id',
        'floor_id',
        'table_number',
        'capacity',
        'status',
        'current_sale_id',
        'shape',
        'pos_x',
        'pos_y',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'pos_x' => 'integer',
        'pos_y' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'seating_capacity',
    ];

    protected static function booted(): void
    {
        static::creating(function ($table) {
            if (empty($table->location_id) && ! empty($table->floor_id)) {
                $floor = RestaurantFloor::find($table->floor_id);
                if ($floor) {
                    $table->location_id = $floor->location_id;
                }
            }
        });
    }

    public function getSeatingCapacityAttribute(): int
    {
        return (int) ($this->attributes['capacity'] ?? 4);
    }

    public function setSeatingCapacityAttribute($value): void
    {
        $this->attributes['capacity'] = (int) $value;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(RestaurantFloor::class, 'floor_id');
    }

    public function currentSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'current_sale_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeOccupied(Builder $query): Builder
    {
        return $query->where('status', 'occupied');
    }

    public function occupy(Sale $sale): void
    {
        $this->update([
            'status' => 'occupied',
            'current_sale_id' => $sale->id,
        ]);
    }

    public function bill(): void
    {
        $this->update([
            'status' => 'billed',
        ]);
    }

    public function release(): void
    {
        $this->update([
            'status' => 'available',
            'current_sale_id' => null,
        ]);
    }
}
