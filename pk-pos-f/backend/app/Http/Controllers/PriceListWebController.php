<?php

namespace App\Http\Controllers;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceListWebController extends Controller
{
    /**
     * Display a listing of price lists.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $priceLists = PriceList::where('tenant_id', $tenantId)
            ->withCount('items')
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->paginate(15);

        return Inertia::render('PriceLists/Index', [
            'priceLists' => $priceLists,
        ]);
    }

    /**
     * Show form for creating a new price list.
     */
    public function create(): Response
    {
        return Inertia::render('PriceLists/Create');
    }

    /**
     * Store a newly created price list.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/i'],
            'description' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['currency'] = $validated['currency'] ?? 'PKR';
        $validated['is_default'] = $validated['is_default'] ?? false;
        $validated['is_active'] = $validated['is_active'] ?? true;

        if ($validated['is_default']) {
            PriceList::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        $priceList = PriceList::create($validated);

        return redirect()->route('price-lists.show', $priceList->id)
            ->with('success', "Price List '{$priceList->name}' created successfully.");
    }

    /**
     * Display a specific price list and its tiered product items.
     */
    public function show(Request $request, string $id): Response
    {
        $tenantId = $request->user()->tenant_id;

        $priceList = PriceList::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $items = PriceListItem::where('tenant_id', $tenantId)
            ->where('price_list_id', $priceList->id)
            ->with('product:id,name,sku,barcode,price_minor,unit')
            ->orderBy('product_id')
            ->orderBy('min_quantity', 'asc')
            ->paginate(25);

        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'sku', 'price_minor', 'unit']);

        return Inertia::render('PriceLists/Show', [
            'priceList' => $priceList,
            'items' => $items,
            'products' => $products,
        ]);
    }

    /**
     * Store a tiered product pricing rule for this price list.
     */
    public function storeItem(Request $request, string $id): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $priceList = PriceList::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'min_quantity' => ['required', 'numeric', 'min:0.001'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['price_list_id'] = $priceList->id;

        PriceListItem::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'price_list_id' => $priceList->id,
                'product_id' => $validated['product_id'],
                'min_quantity' => $validated['min_quantity'],
            ],
            [
                'price_minor' => $validated['price_minor'],
                'discount_pct' => $validated['discount_pct'] ?? null,
            ]
        );

        return redirect()->route('price-lists.show', $priceList->id)
            ->with('success', 'Tier pricing rule saved successfully.');
    }

    /**
     * Delete a tier pricing rule.
     */
    public function destroyItem(Request $request, string $id, string $itemId): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        PriceListItem::where('tenant_id', $tenantId)
            ->where('price_list_id', $id)
            ->where('id', $itemId)
            ->delete();

        return redirect()->route('price-lists.show', $id)
            ->with('success', 'Tier pricing rule removed.');
    }
}
