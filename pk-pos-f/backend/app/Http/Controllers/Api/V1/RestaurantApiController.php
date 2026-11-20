<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KitchenOrder;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\RestaurantFloor;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Services\Restaurant\KitchenOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantApiController extends Controller
{
    public function __construct(
        protected KitchenOrderService $kotService
    ) {}

    /**
     * Get restaurant floors with nested tables and their live occupancy statuses.
     */
    public function getFloorsAndTables(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $locationId = $request->query('location_id');

        $floorsQuery = RestaurantFloor::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['tables' => function ($q) {
                $q->where('is_active', true)->orderBy('table_number');
            }])
            ->orderBy('sort_order');

        if ($locationId) {
            $floorsQuery->where('location_id', $locationId);
        }

        $floors = $floorsQuery->get();

        // Also fetch any unassigned tables
        $unassignedTablesQuery = RestaurantTable::where('tenant_id', $tenantId)
            ->whereNull('floor_id')
            ->where('is_active', true)
            ->orderBy('table_number');

        if ($locationId) {
            $unassignedTablesQuery->where('location_id', $locationId);
        }

        $unassignedTables = $unassignedTablesQuery->get();

        return response()->json([
            'floors' => $floors,
            'unassigned_tables' => $unassignedTables,
        ]);
    }

    /**
     * Get specific table status with currently active order items if occupied.
     */
    public function getTableStatus(Request $request, string $tableId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $table = RestaurantTable::where('tenant_id', $tenantId)
            ->with(['floor', 'currentSale.items'])
            ->findOrFail($tableId);

        return response()->json([
            'table' => $table,
            'current_sale' => $table->currentSale,
        ]);
    }

    /**
     * Mark table occupied or link an ongoing order.
     */
    public function occupyTable(Request $request, string $tableId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $table = RestaurantTable::where('tenant_id', $tenantId)->findOrFail($tableId);

        $validated = $request->validate([
            'sale_id' => ['nullable', 'uuid', 'exists:sales,id'],
            'covers' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! empty($validated['sale_id'])) {
            $sale = Sale::where('tenant_id', $tenantId)->findOrFail($validated['sale_id']);
            $table->occupy($sale);
        } else {
            $table->update(['status' => 'occupied']);
        }

        return response()->json([
            'message' => "Table {$table->table_number} marked occupied.",
            'table' => $table->fresh(),
            'status' => 'occupied',
        ]);
    }

    /**
     * Vacate / release table to available status.
     */
    public function releaseTable(Request $request, string $tableId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $table = RestaurantTable::where('tenant_id', $tenantId)->findOrFail($tableId);

        $this->kotService->releaseTable($table);

        return response()->json([
            'message' => "Table {$table->table_number} is now available.",
            'table' => $table->fresh(),
            'status' => 'available',
        ]);
    }

    /**
     * Get modifier groups (optionally filtered for a specific product).
     */
    public function getModifierGroups(Request $request, ?string $productId = null): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        if ($productId) {
            $product = Product::where('tenant_id', $tenantId)->findOrFail($productId);
            $groups = $product->modifierGroups()->where('is_active', true)->with('options')->get();
        } else {
            $groups = ModifierGroup::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with(['options' => function ($q) {
                    $q->where('is_active', true)->orderBy('sort_order');
                }])
                ->get();
        }

        return response()->json([
            'data' => $groups,
        ]);
    }

    /**
     * Live Kitchen Display System (KDS) order feed for kitchen screens.
     */
    public function getKdsOrders(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $stationId = $request->query('station_id');
        $locationId = $request->query('location_id');

        $orders = $this->kotService->getLiveKdsOrders($tenant, $stationId, $locationId);

        return response()->json([
            'orders' => $orders,
            'count' => $orders->count(),
        ]);
    }

    /**
     * Bump KOT status on KDS bump bar or chef touch screen.
     */
    public function bumpKdsOrder(Request $request, string $kotId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $kot = KitchenOrder::where('tenant_id', $tenantId)->findOrFail($kotId);

        $targetStatus = $request->input('status'); // preparing, ready, served, cancelled
        $updated = $this->kotService->bumpKot($kot, $targetStatus);

        return response()->json([
            'success' => true,
            'status' => $updated->status,
            'message' => "Order {$updated->kot_number} updated to {$updated->status}.",
            'order' => $updated,
        ]);
    }

    /**
     * Fire Kitchen Order Tickets (KOT) for a given sale.
     */
    public function fireKot(Request $request, string $saleId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $sale = Sale::where('tenant_id', $tenantId)->with('items.product')->findOrFail($saleId);

        $itemIds = $request->input('item_ids'); // optional subset
        $orders = $this->kotService->fireKitchenOrders($sale, $itemIds);

        return response()->json([
            'message' => "Fired " . $orders->count() . " Kitchen Order Ticket(s).",
            'orders' => $orders,
        ]);
    }
}
