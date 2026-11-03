<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    /**
     * Disable updated_at timestamp because stock movements are strictly append-only.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     * Note: 'id' is included to allow client-generated UUID assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'product_id',
        'location_id',
        'movement_type',
        'quantity_delta',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Backward-compatible accessor for quantity attribute.
     */
    public function getQuantityAttribute(): int
    {
        return $this->quantity_delta;
    }

    /**
     * Get the product for this movement.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the location for this movement.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the user who recorded this movement, if any.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
