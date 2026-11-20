<?php

namespace App\Services\Restaurant;

use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\KitchenStation;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KitchenOrderService
{
    /**
     * Fire Kitchen Order Tickets (KOT) for a sale, routing items to their respective kitchen stations.
     *
     * @param Sale $sale
     * @param array<int, string>|null $itemIds Optional subset of sale_item IDs to fire
     * @return Collection<int, KitchenOrder>
     */
    public function fireKitchenOrders(Sale $sale, ?array $itemIds = null): Collection
    {
        return DB::transaction(function () use ($sale, $itemIds) {
            $itemsQuery = $sale->items();
            if (! empty($itemIds)) {
                $itemsQuery->whereIn('id', $itemIds);
            }
            $items = $itemsQuery->get();

            if ($items->isEmpty()) {
                return collect();
            }

            // Group items by their kitchen_station_id
            $grouped = $items->groupBy(function (SaleItem $item) {
                return $item->kitchen_station_id ?? ($item->product?->kitchen_station_id ?? 'default');
            });

            $createdOrders = collect();

            foreach ($grouped as $stationKey => $stationItems) {
                $stationId = $stationKey === 'default' ? null : $stationKey;

                // Generate incremental daily KOT Number
                $datePrefix = now()->format('ymd');
                $count = KitchenOrder::where('tenant_id', $sale->tenant_id)
                    ->whereDate('created_at', now()->toDateString())
                    ->count() + 1;
                $kotNumber = "KOT-{$datePrefix}-" . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

                $kot = KitchenOrder::create([
                    'tenant_id' => $sale->tenant_id,
                    'location_id' => $sale->location_id,
                    'sale_id' => $sale->id,
                    'kitchen_station_id' => $stationId,
                    'kot_number' => $kotNumber,
                    'order_type' => $sale->order_type ?? 'dine_in',
                    'table_number' => $sale->table_number ?? $sale->restaurantTable?->table_number,
                    'server_name' => $sale->server_name,
                    'status' => 'pending',
                    'special_instructions' => null,
                    'fired_at' => now(),
                ]);

                foreach ($stationItems as $item) {
                    $kot->items()->create([
                        'sale_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'selected_modifiers' => $item->selected_modifiers,
                        'cooking_notes' => $item->cooking_notes,
                        'status' => 'pending',
                    ]);
                }

                $createdOrders->push($kot->load('items'));
            }

            // If Dine-In table attached, ensure table is marked occupied
            if ($sale->restaurant_table_id) {
                $table = RestaurantTable::find($sale->restaurant_table_id);
                if ($table && $table->status !== 'occupied') {
                    $table->occupy($sale);
                }
            }

            return $createdOrders;
        });
    }

    /**
     * Advance KOT status (bump bar workflow: pending -> preparing -> ready -> served).
     */
    public function bumpKot(KitchenOrder $kot, ?string $targetStatus = null): KitchenOrder
    {
        if ($targetStatus) {
            match ($targetStatus) {
                'preparing' => $kot->markPreparing(),
                'ready' => $kot->markReady(),
                'served' => $kot->markServed(),
                'cancelled' => $kot->markCancelled(),
                default => null,
            };
            return $kot->fresh();
        }

        match ($kot->status) {
            'pending' => $kot->markPreparing(),
            'preparing' => $kot->markReady(),
            'ready' => $kot->markServed(),
            default => null,
        };

        return $kot->fresh();
    }

    /**
     * Update individual KOT line item status.
     */
    public function updateItemStatus(KitchenOrderItem $item, string $status): KitchenOrderItem
    {
        $item->update(['status' => $status]);

        // If all items are ready, mark entire KOT ready
        $kot = $item->kitchenOrder;
        if ($kot && $kot->items()->where('status', '!=', 'ready')->where('status', '!=', 'cancelled')->count() === 0) {
            $kot->update([
                'status' => 'ready',
                'ready_at' => now(),
            ]);
        }

        return $item->fresh();
    }

    /**
     * Get active kitchen orders for Kitchen Display System (KDS).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getLiveKdsOrders(Tenant $tenant, ?string $stationId = null, ?string $locationId = null): Collection
    {
        $query = KitchenOrder::with(['items', 'kitchenStation'])
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->orderBy('fired_at', 'asc');

        if ($stationId) {
            $query->where('kitchen_station_id', $stationId);
        }

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query->get()->map(function (KitchenOrder $order) {
            $elapsed = $order->elapsed_minutes;
            $urgency = 'normal'; // <10m = normal (green), 10-20m = warning (yellow), >20m = late (red)
            if ($elapsed >= 20) {
                $urgency = 'critical';
            } elseif ($elapsed >= 10) {
                $urgency = 'warning';
            }

            return [
                'id' => $order->id,
                'kot_number' => $order->kot_number,
                'sale_id' => $order->sale_id,
                'station_name' => $order->kitchenStation?->name ?? 'Main Kitchen',
                'order_type' => $order->order_type,
                'table_number' => $order->table_number,
                'server_name' => $order->server_name,
                'status' => $order->status,
                'fired_at' => $order->fired_at->toIso8601String(),
                'elapsed_minutes' => $elapsed,
                'urgency' => $urgency,
                'items' => $order->items->map(fn (KitchenOrderItem $i) => [
                    'id' => $i->id,
                    'product_name' => $i->product_name,
                    'quantity' => (float) $i->quantity,
                    'selected_modifiers' => $i->selected_modifiers,
                    'cooking_notes' => $i->cooking_notes,
                    'status' => $i->status,
                ]),
            ];
        });
    }

    /**
     * Compute and apply service charge (e.g. 5% or 10% on dine-in).
     */
    public function applyServiceCharge(Sale $sale, int $basisPoints = 500): Sale
    {
        $subtotal = $sale->subtotal_minor;
        $charge = (int) round(($subtotal * $basisPoints) / 10000);

        $sale->update([
            'service_charge_basis_points' => $basisPoints,
            'service_charge_minor' => $charge,
            'total_minor' => $sale->total_minor + $charge,
        ]);

        return $sale->fresh();
    }

    /**
     * Release a dining table when bill is settled or vacated.
     */
    public function releaseTable(RestaurantTable $table): void
    {
        $table->release();
    }
}
