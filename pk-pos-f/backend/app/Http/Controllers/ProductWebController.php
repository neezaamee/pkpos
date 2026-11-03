<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductWebController extends Controller
{
    /**
     * Display a paginated listing of products.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $products = Product::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%")
                        ->orWhere('barcode', 'ilike', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): Response
    {
        $this->authorizeAccess($request);

        return Inertia::render('Products/Create');
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'hs_code' => ['required', 'regex:/^[0-9]{8}$/'],
            'unit' => ['required', Rule::in(['piece', 'kg', 'gram', 'litre', 'ml', 'hour', 'session'])],
            'price_in_pkr' => ['required', 'numeric', 'min:0'],
            'cost_in_pkr' => ['nullable', 'numeric', 'min:0'],
            'tax_category' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:50'],
            'variants.*.sku' => ['nullable', 'string', 'max:50'],
            'variants.*.price_override_in_pkr' => ['nullable', 'numeric', 'min:0'],
        ]);

        $priceMinor = (int) round($validated['price_in_pkr'] * 100);
        $costMinor = isset($validated['cost_in_pkr']) ? (int) round($validated['cost_in_pkr'] * 100) : null;

        DB::transaction(function () use ($validated, $priceMinor, $costMinor, $request) {
            $product = Product::create([
                'tenant_id' => $request->user()->tenant_id,
                'name' => $validated['name'],
                'sku' => $validated['sku'] ?? null,
                'barcode' => $validated['barcode'] ?? null,
                'hs_code' => $validated['hs_code'],
                'unit' => $validated['unit'],
                'price_minor' => $priceMinor,
                'cost_minor' => $costMinor,
                'tax_category' => $validated['tax_category'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (! empty($validated['variants'])) {
                foreach ($validated['variants'] as $v) {
                    $variantOverride = isset($v['price_override_in_pkr']) ? (int) round($v['price_override_in_pkr'] * 100) : null;
                    ProductVariant::create([
                        'tenant_id' => $request->user()->tenant_id,
                        'product_id' => $product->id,
                        'name' => $v['name'],
                        'sku' => $v['sku'] ?? null,
                        'price_override_minor' => $variantOverride,
                    ]);
                }
            }
        });

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Request $request, string $id): Response
    {
        $this->authorizeAccess($request);

        $product = Product::with('variants')->findOrFail($id);
        $hasSales = SaleItem::where('product_id', $product->id)->exists();

        return Inertia::render('Products/Edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'hs_code' => $product->hs_code,
                'unit' => $product->unit,
                'price_in_pkr' => $product->price_minor / 100,
                'cost_in_pkr' => $product->cost_minor !== null ? $product->cost_minor / 100 : null,
                'tax_category' => $product->tax_category,
                'is_active' => (bool) $product->is_active,
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'price_override_in_pkr' => $v->price_override_minor !== null ? $v->price_override_minor / 100 : null,
                ]),
            ],
            'hasSales' => $hasSales,
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAccess($request);

        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'hs_code' => ['required', 'regex:/^[0-9]{8}$/'],
            'unit' => ['required', Rule::in(['piece', 'kg', 'gram', 'litre', 'ml', 'hour', 'session'])],
            'price_in_pkr' => ['required', 'numeric', 'min:0'],
            'cost_in_pkr' => ['nullable', 'numeric', 'min:0'],
            'tax_category' => ['required', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validated['hs_code'] !== $product->hs_code) {
            $isReferenced = SaleItem::where('product_id', $product->id)->exists();
            if ($isReferenced) {
                return redirect()->back()->withErrors([
                    'hs_code' => 'Cannot change HS code for a product referenced in completed sales.',
                ]);
            }
        }

        $product->update([
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'hs_code' => $validated['hs_code'],
            'unit' => $validated['unit'],
            'price_minor' => (int) round($validated['price_in_pkr'] * 100),
            'cost_minor' => isset($validated['cost_in_pkr']) ? (int) round($validated['cost_in_pkr'] * 100) : null,
            'tax_category' => $validated['tax_category'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
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
