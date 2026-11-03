<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Adjust stock level to a specific new quantity.
     */
    public function adjust(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'new_quantity' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $tenant = $user->tenant;

        // Verify product & location belong to authenticated tenant
        Product::findOrFail($validated['product_id']);
        Location::findOrFail($validated['location_id']);

        $currentStock = $this->stockService->getCurrentStock(
            $tenant,
            $validated['product_id'],
            $validated['location_id']
        );

        $quantityDelta = $validated['new_quantity'] - $currentStock;

        $movement = $this->stockService->recordMovement(
            tenant: $tenant,
            productId: $validated['product_id'],
            locationId: $validated['location_id'],
            movementType: 'adjustment',
            quantityDelta: $quantityDelta,
            referenceType: 'manual_adjustment',
            referenceId: null,
            note: $validated['note'] ?? "Adjusted stock from {$currentStock} to {$validated['new_quantity']}",
            createdBy: $user->id
        );

        $newLevel = StockLevel::where('tenant_id', $tenant->id)
            ->where('product_id', $validated['product_id'])
            ->where('location_id', $validated['location_id'])
            ->first();

        return response()->json([
            'status' => 'ok',
            'movement' => $movement,
            'stock_level' => $newLevel,
        ]);
    }

    /**
     * Restock an inventory item with a positive quantity addition.
     */
    public function restock(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $tenant = $user->tenant;

        Product::findOrFail($validated['product_id']);
        Location::findOrFail($validated['location_id']);

        $movement = $this->stockService->recordMovement(
            tenant: $tenant,
            productId: $validated['product_id'],
            locationId: $validated['location_id'],
            movementType: 'restock',
            quantityDelta: $validated['quantity'],
            referenceType: 'restock',
            referenceId: null,
            note: $validated['note'] ?? 'Inventory restock',
            createdBy: $user->id
        );

        $newLevel = StockLevel::where('tenant_id', $tenant->id)
            ->where('product_id', $validated['product_id'])
            ->where('location_id', $validated['location_id'])
            ->first();

        return response()->json([
            'status' => 'ok',
            'movement' => $movement,
            'stock_level' => $newLevel,
        ]);
    }

    /**
     * Display paginated stock levels.
     */
    public function levels(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $threshold = (int) config('inventory.low_stock_threshold', 10);

        $query = StockLevel::with(['product', 'location'])
            ->when($request->filled('location_id'), function ($q) use ($request) {
                $q->where('location_id', $request->input('location_id'));
            })
            ->when($request->boolean('low_stock_only'), function ($q) use ($threshold) {
                $q->where('quantity_on_hand', '<=', $threshold);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%")
                        ->orWhere('barcode', 'ilike', "%{$search}%")
                        ->orWhere('hs_code', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('quantity_on_hand', 'asc');

        return response()->json($query->paginate(20));
    }

    /**
     * Display stock movement history.
     */
    public function movements(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $query = StockMovement::with(['product', 'location', 'creator'])
            ->when($request->filled('product_id'), function ($q) use ($request) {
                $q->where('product_id', $request->input('product_id'));
            })
            ->when($request->filled('location_id'), function ($q) use ($request) {
                $q->where('location_id', $request->input('location_id'));
            })
            ->when($request->filled('movement_type'), function ($q) use ($request) {
                $q->where('movement_type', $request->input('movement_type'));
            })
            ->latest('created_at');

        return response()->json($query->paginate(20));
    }

    /**
     * Authorize that the authenticated user is an owner or manager.
     */
    protected function authorizeAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, ['owner', 'manager']),
            403,
            'Unauthorized. Requires owner or manager role.'
        );
    }
}
