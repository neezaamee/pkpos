<?php

namespace App\Http\Controllers\Hardware;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPriceTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceTierWebController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $productId = $request->query('product_id');

        $query = ProductPriceTier::where('tenant_id', $tenantId)
            ->with('product')
            ->orderBy('product_id')
            ->orderBy('min_quantity');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $tiers = $query->paginate(20);
        $products = Product::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Hardware/Tiers/Index', [
            'tiers' => $tiers,
            'products' => $products,
            'filters' => [
                'product_id' => $productId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'min_quantity' => ['required', 'numeric', 'min:0.001'],
            'max_quantity' => ['nullable', 'numeric', 'gte:min_quantity'],
            'unit_price_minor' => ['required', 'integer', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tier_label' => ['nullable', 'string', 'max:100'],
        ]);

        ProductPriceTier::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]));

        return back()->with('success', 'Wholesale price slab registered successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $tier = ProductPriceTier::where('tenant_id', $tenantId)->findOrFail($id);
        $tier->delete();

        return back()->with('success', 'Wholesale price slab removed.');
    }
}
