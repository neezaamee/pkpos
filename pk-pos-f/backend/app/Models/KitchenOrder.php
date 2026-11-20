<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenOrder extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'location_id',
        'sale_id',
        'kitchen_station_id',
        'kot_number',
        'order_type',
        'table_number',
        'server_name',
        'status',
        'special_instructions',
        'fired_at',
        'ready_at',
        'served_at',
    ];

    protected $casts = [
        'fired_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenOrderItem::class);
    }

    public function getElapsedMinutesAttribute(): int
    {
        return (int) $this->fired_at->diffInMinutes(now());
    }

    public function markPreparing(): void
    {
        $this->update(['status' => 'preparing']);
        $this->items()->where('status', 'pending')->update(['status' => 'preparing']);
    }

    public function markReady(): void
    {
        $this->update([
            'status' => 'ready',
            'ready_at' => now(),
        ]);
        $this->items()->whereIn('status', ['pending', 'preparing'])->update(['status' => 'ready']);
    }

    public function markServed(): void
    {
        $this->update([
            'status' => 'served',
            'served_at' => now(),
        ]);
        $this->items()->where('status', '!=', 'cancelled')->update(['status' => 'served']);
    }

    public function markCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
        $this->items()->update(['status' => 'cancelled']);
    }
}
