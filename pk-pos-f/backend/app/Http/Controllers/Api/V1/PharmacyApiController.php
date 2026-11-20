<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\Pharmacy\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyApiController extends Controller
{
    public function __construct(protected BatchService $batchService)
    {
    }

    /**
     * Search pharmaceutical medicines by brand name, generic name, or DRAP registration number.
     */
    public function searchMedicines(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $query = $request->query('q');

        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                if ($query) {
                    $q->where('name', 'ilike', "%{$query}%")
                        ->orWhere('generic_name', 'ilike', "%{$query}%")
                        ->orWhere('drap_registration_number', 'ilike', "%{$query}%")
                        ->orWhere('barcode', $query);
                }
            })
            ->with([
                'activeBatches',
                'uomConversions' => function ($q) {
                    $q->where('is_active', true);
                },
            ])
            ->limit(30)
            ->get();

        $data = $products->map(function (Product $product) use ($request) {
            $fefo = $product->fefoBatch($request->query('location_id'));
            return [
                'id' => $product->id,
                'name' => $product->name,
                'generic_name' => $product->generic_name,
                'drap_registration_number' => $product->drap_registration_number,
                'dosage_form' => $product->dosage_form,
                'strength' => $product->strength,
                'manufacturer' => $product->manufacturer,
                'is_prescription_required' => $product->is_prescription_required,
                'is_narcotic' => $product->is_narcotic,
                'cold_chain' => $product->cold_chain,
                'rack_location' => $product->rack_location,
                'unit' => $product->unit,
                'price_minor' => $product->price_minor,
                'barcode' => $product->barcode,
                'hs_code' => $product->hs_code,
                'tax_rate_basis_points' => 0, // Medicines often exempt or specific GST
                'fefo_batch' => $fefo ? [
                    'id' => $fefo->id,
                    'batch_number' => $fefo->batch_number,
                    'expiry_date' => $fefo->expiry_date->toDateString(),
                    'days_until_expiry' => $fefo->daysUntilExpiry(),
                    'is_nearing_expiry' => $fefo->isNearingExpiry(60),
                    'quantity_on_hand' => $fefo->quantity_on_hand,
                    'mrp_minor' => $fefo->mrp_minor,
                ] : null,
                'available_batches_count' => $product->activeBatches->count(),
                'packaging_conversions' => $product->uomConversions->map(function ($uom) {
                    return [
                        'id' => $uom->id,
                        'unit_name' => $uom->unit_name,
                        'unit_code' => $uom->unit_code,
                        'conversion_factor' => (float) $uom->conversion_factor,
                        'price_minor' => $uom->price_minor,
                        'barcode' => $uom->barcode,
                    ];
                }),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get all active batches for a specific medicine sorted by FEFO.
     */
    public function getBatches(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $locationId = $request->query('location_id');

        $query = ProductBatch::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('expiry_date', '>=', now()->toDateString());

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $batches = $query->orderBy('expiry_date', 'asc')->get();

        $data = $batches->map(function (ProductBatch $b) {
            return [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'manufacturing_date' => $b->manufacturing_date?->toDateString(),
                'expiry_date' => $b->expiry_date->toDateString(),
                'days_until_expiry' => $b->daysUntilExpiry(),
                'is_nearing_expiry' => $b->isNearingExpiry(90),
                'quantity_on_hand' => (float) $b->quantity_on_hand,
                'mrp_minor' => $b->mrp_minor,
                'cost_minor' => $b->cost_minor,
            ];
        });

        return response()->json($data);
    }

    /**
     * Get batches nearing expiry across the pharmacy.
     */
    public function getExpiringBatches(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $days = (int) $request->query('days', 90);

        $expiring = $this->batchService->getExpiringBatches($tenant, $days);

        return response()->json($expiring);
    }
}
