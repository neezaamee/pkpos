<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    /**
     * The attributes that are mass assignable.
     * Note: 'id' is included to allow client-generated UUID assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'location_id',
        'user_id',
        'sale_number',
        'payment_method',
        'currency',
        'subtotal_minor',
        'tax_minor',
        'total_minor',
        'status',
        'fbr_usin',
        'fbr_status',
        'fbr_error',
        'fbr_submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'fbr_submitted_at' => 'datetime',
        ];
    }

    /**
     * Boot the model and enforce immutability guards.
     */
    protected static function booted(): void
    {
        static::updating(function (Sale $sale) {
            if ($sale->getOriginal('status') === 'completed') {
                $dirty = array_keys($sale->getDirty());
                $allowed = ['fbr_status', 'fbr_error', 'fbr_usin', 'fbr_submitted_at', 'updated_at'];
                if (count(array_diff($dirty, $allowed)) > 0) {
                    throw new DomainException('A completed sale cannot be updated.');
                }
            }
        });

        static::deleting(function (Sale $sale) {
            if ($sale->getOriginal('status') === 'completed') {
                throw new DomainException('A completed sale cannot be deleted.');
            }
        });
    }

    /**
     * Get the line items for the sale.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    /**
     * Get the location where the sale was made.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the user (cashier/manager/owner) who processed the sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
