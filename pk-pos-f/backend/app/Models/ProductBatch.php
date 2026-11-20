<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatch extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'location_id',
        'supplier_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity_on_hand',
        'mrp_minor',
        'cost_minor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'quantity_on_hand' => 'float',
            'mrp_minor' => 'integer',
            'cost_minor' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Check if this batch has passed its statutory expiration date.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    /**
     * Get number of days remaining until expiration (negative if expired).
     */
    public function daysUntilExpiry(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }

    /**
     * Check if this batch is nearing expiry within the threshold (default 90 days).
     */
    public function isNearingExpiry(int $days = 90): bool
    {
        $rem = $this->daysUntilExpiry();
        return $rem >= 0 && $rem <= $days;
    }
}
