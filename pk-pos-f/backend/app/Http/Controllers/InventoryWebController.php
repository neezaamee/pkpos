<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryWebController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Display the stock levels projection table.
     */
    public function levels(Request $request): Response
    {
        $this->authorizeAccess($request);

        $threshold = (int) config('inventory.low_stock_threshold', 10);

        $levels = StockLevel::with(['product', 'location'])
            ->when($request->filled('location_id'), function ($q) use ($request) {
                $q->where('location_id', $request->input('location_id'));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->whereLike('name', "%{$search}%")
                        ->orWhereLike('sku', "%{$search}%")
                        ->orWhereLike('barcode', "%{$search}%")
                        ->orWhereLike('hs_code', "%{$search}%");
                });
            })
            ->orderBy('quantity_on_hand', 'asc')
            ->paginate(15)
            ->withQueryString();

        $locations = Location::all(['id', 'name']);

        return Inertia::render('Inventory/Levels', [
            'levels' => $levels,
            'locations' => $locations,
            'threshold' => $threshold,
            'filters' => $request->only(['location_id', 'search']),
        ]);
    }

    /**
     * Display the immutable stock movements history log.
     */
    public function movements(Request $request): Response
    {
        $this->authorizeAccess($request);

        $movements = StockMovement::with(['product', 'location', 'creator'])
            ->when($request->filled('product_id'), function ($q) use ($request) {
                $q->where('product_id', $request->input('product_id'));
            })
            ->when($request->filled('movement_type'), function ($q) use ($request) {
                $q->where('movement_type', $request->input('movement_type'));
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Movements', [
            'movements' => $movements,
            'filters' => $request->only(['product_id', 'movement_type']),
        ]);
    }

    /**
     * Show the manual stock adjustment form.
     */
    public function adjust(Request $request): Response
    {
        $this->authorizeAccess($request);

        $products = Product::where('is_active', true)->get(['id', 'name', 'sku', 'hs_code']);
        $locations = Location::all(['id', 'name']);

        return Inertia::render('Inventory/Adjust', [
            'products' => $products,
            'locations' => $locations,
        ]);
    }

    /**
     * Store a manual stock adjustment.
     */
    public function storeAdjust(Request $request): RedirectResponse
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

        Product::findOrFail($validated['product_id']);
        Location::findOrFail($validated['location_id']);

        $currentStock = $this->stockService->getCurrentStock(
            $tenant,
            $validated['product_id'],
            $validated['location_id']
        );

        $quantityDelta = $validated['new_quantity'] - $currentStock;

        $this->stockService->recordMovement(
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

        return redirect()->route('inventory.levels')->with('success', 'Stock level adjusted successfully.');
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
