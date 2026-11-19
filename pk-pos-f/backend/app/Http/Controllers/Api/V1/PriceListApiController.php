<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListApiController extends Controller
{
    /**
     * List all price lists for this tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $lists = PriceList::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['items.product:id,name,sku,barcode,price_minor'])
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'price_lists' => $lists,
        ]);
    }

    /**
     * Resolve tier price for a product based on customer price list and purchase volume.
     */
    public function resolvePrice(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'price_list_id' => ['nullable', 'uuid', 'exists:price_lists,id'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $product = Product::where('tenant_id', $tenantId)
            ->where('id', $validated['product_id'])
            ->firstOrFail();

        $quantity = (float) ($validated['quantity'] ?? 1.0);
        $resolvedPriceMinor = $product->price_minor;
        $priceListName = 'Default Retail';

        if (! empty($validated['price_list_id'])) {
            $priceList = PriceList::where('tenant_id', $tenantId)
                ->where('id', $validated['price_list_id'])
                ->first();

            if ($priceList) {
                $resolvedPriceMinor = $priceList->resolveProductPrice($product, $quantity);
                $priceListName = $priceList->name;
            }
        }

        return response()->json([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price_list_name' => $priceListName,
            'base_price_minor' => $product->price_minor,
            'resolved_price_minor' => $resolvedPriceMinor,
            'is_discounted' => $resolvedPriceMinor < $product->price_minor,
            'savings_minor' => max(0, $product->price_minor - $resolvedPriceMinor),
        ]);
    }
}
