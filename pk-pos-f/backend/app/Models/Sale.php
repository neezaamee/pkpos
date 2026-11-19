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
        'customer_id',
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
     * Get the customer associated with this invoice (if credit or registered customer).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
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

    /**
     * Get any sales returns / credit notes issued against this sale.
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class, 'sale_id')->latest();
    }

    /**
     * Compute returnable quantities for each item in the sale.
     */
    public function getReturnableItems(): array
    {
        $items = $this->items()->get();
        $returnedQuantities = SaleReturnItem::whereIn('sale_item_id', $items->pluck('id'))
            ->groupBy('sale_item_id')
            ->selectRaw('sale_item_id, sum(quantity_returned) as total_returned')
            ->pluck('total_returned', 'sale_item_id');

        return $items->map(function (SaleItem $item) use ($returnedQuantities) {
            $alreadyReturned = (float) ($returnedQuantities[$item->id] ?? 0);
            $soldQty = (float) $item->quantity;
            $returnableQty = max(0, $soldQty - $alreadyReturned);

            return [
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit_code' => $item->unit_code ?: 'pcs',
                'unit_conversion_factor' => (float) ($item->unit_conversion_factor ?: 1.0),
                'sold_quantity' => $soldQty,
                'already_returned_quantity' => $alreadyReturned,
                'returnable_quantity' => $returnableQty,
                'unit_price_minor' => $item->unit_price_minor,
                'tax_rate_basis_points' => $item->tax_rate_basis_points ?: 0,
            ];
        })->values()->all();
    }
}
