<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductUomConversion;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UomWebController extends Controller
{
    /**
     * Display a listing of Units of Measure and packaging conversions.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $uoms = UnitOfMeasure::where('tenant_id', $tenantId)
            ->withCount('conversions')
            ->orderBy('name', 'asc')
            ->get();

        $conversions = ProductUomConversion::where('tenant_id', $tenantId)
            ->with(['product:id,name,sku,barcode,price_minor,unit', 'uom:id,name,code'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'sku', 'barcode', 'price_minor', 'unit']);

        return Inertia::render('Uom/Index', [
            'uoms' => $uoms,
            'conversions' => $conversions,
            'products' => $products,
        ]);
    }

    /**
     * Store a new Unit of Measure.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[a-z0-9_-]+$/i'],
            'allows_decimals' => ['nullable', 'boolean'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['allows_decimals'] = $validated['allows_decimals'] ?? false;
        $validated['is_active'] = true;

        UnitOfMeasure::create($validated);

        return redirect()->route('uom.index')
            ->with('success', "Unit of Measure '{$validated['name']}' created.");
    }

    /**
     * Store a product pack conversion (e.g. 1 Carton of 24 Pieces).
     */
    public function storeConversion(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'unit_name' => ['required', 'string', 'max:50'],
            'unit_code' => ['required', 'string', 'max:20'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'conversion_factor' => ['required', 'numeric', 'min:0.001'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'cost_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['is_active'] = true;

        ProductUomConversion::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_id' => $validated['product_id'],
                'unit_code' => $validated['unit_code'],
            ],
            $validated
        );

        return redirect()->route('uom.index')
            ->with('success', "Packaging conversion '{$validated['unit_name']}' saved.");
    }

    /**
     * Delete a product pack conversion.
     */
    public function destroyConversion(Request $request, string $id): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        ProductUomConversion::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->delete();

        return redirect()->route('uom.index')
            ->with('success', 'Packaging conversion removed.');
    }
}
