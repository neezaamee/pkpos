<?php

namespace App\Services\Hardware;

use App\Models\ContractorProject;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HardwareBuildingService
{
    /**
     * Compute dimensional weight (KG) and quantity for building materials.
     * Handles Cement bags (50kg), Steel rebar (kg/foot), Sand/Crush (CFT), and Tiles (sq.ft / boxes).
     */
    public function calculateDimensionalDetails(Product $product, float $quantity, ?string $unit = null): array
    {
        $unit = strtolower($unit ?: ($product->dimensional_unit ?: $product->unit ?: 'pcs'));
        $weightKg = 0.0;
        $calculatedBoxes = null;
        $totalSqft = null;

        if (($unit === 'bag' || $unit === 'bag_50kg' || str_contains(strtolower($product->name), 'cement')) && ($product->weight_kg_per_unit || str_contains(strtolower($product->name), 'cement'))) {
            $kgPerBag = (float) ($product->weight_kg_per_unit ?: 50.0);
            $weightKg = $quantity * $kgPerBag;
        } elseif ($unit === 'ton') {
            $weightKg = $quantity * 1000.0;
        } elseif ($unit === 'maund' || $unit === 'mann') {
            $weightKg = $quantity * 40.0;
        } elseif ($unit === 'kg') {
            $weightKg = $quantity;
        } elseif (($unit === 'feet' || $unit === 'rft' || $unit === 'length_ft' || $unit === 'rebar_feet') && $product->weight_kg_per_unit) {
            $weightKg = $quantity * (float) $product->weight_kg_per_unit;
        } elseif ($unit === 'cft') {
            // Sand / Crush standard bulk density ~ 45 KG per cubic foot
            $density = (float) ($product->weight_kg_per_unit ?: 45.0);
            $weightKg = $quantity * $density;
        } elseif (($unit === 'sqft' || $unit === 'tile_box' || $unit === 'box') && $product->sqft_per_box && $product->sqft_per_box > 0) {
            // Tiles / Marbles box calculation
            $totalSqft = $quantity;
            $sqftPerBox = (float) $product->sqft_per_box;
            $calculatedBoxes = (int) ceil($quantity / $sqftPerBox);
        }

        return [
            'quantity' => $quantity,
            'unit' => $unit,
            'weight_kg' => round($weightKg, 3),
            'gross_weight_kg' => round($weightKg, 3),
            'weight_tons' => round($weightKg / 1000.0, 3),
            'calculated_boxes' => $calculatedBoxes,
            'boxes_count' => $calculatedBoxes,
            'total_sqft' => $totalSqft,
        ];
    }

    /**
     * Resolve wholesale / tiered bulk pricing for a given quantity.
     * E.g. 1-20 bags @ Rs 1400, 21-100 bags @ Rs 1350, 100+ bags @ Rs 1310.
     */
    public function resolveTieredUnitPrice(Product $product, float $quantity): int
    {
        return $product->getTieredPriceForQuantity($quantity);
    }

    /**
     * Create a Delivery Challan / Gate Pass for truck or loader dispatch.
     */
    public function createDeliveryChallan(Sale $sale, array $challanData): DeliveryChallan
    {
        return DB::transaction(function () use ($sale, $challanData) {
            $date = now()->format('Ymd');
            $countToday = DeliveryChallan::where('tenant_id', $sale->tenant_id)
                ->whereDate('created_at', today())
                ->count() + 1;

            $challanNumber = sprintf('CHL-%s-%03d', $date, $countToday);
            $gatePassNumber = sprintf('GP-%s-%03d', $date, $countToday);

            $challan = DeliveryChallan::create([
                'tenant_id' => $sale->tenant_id,
                'location_id' => $sale->location_id,
                'sale_id' => $sale->id,
                'contractor_project_id' => $challanData['contractor_project_id'] ?? $sale->contractor_project_id,
                'challan_number' => $challanNumber,
                'gate_pass_number' => $challanData['gate_pass_number'] ?? $gatePassNumber,
                'vehicle_number' => $challanData['vehicle_number'] ?? 'LES-4820',
                'vehicle_type' => $challanData['vehicle_type'] ?? 'Shahzore / Mazda',
                'driver_name' => $challanData['driver_name'] ?? 'Driver',
                'driver_phone' => $challanData['driver_phone'] ?? null,
                'transporter_name' => $challanData['transporter_name'] ?? null,
                'freight_minor' => (int) ($challanData['freight_minor'] ?? $challanData['freight_amount_minor'] ?? 0),
                'freight_payment_type' => $challanData['freight_payment_type'] ?? $challanData['freight_terms'] ?? 'to_pay_customer',
                'destination_site' => $challanData['destination_site'] ?? $challanData['site_destination'] ?? $sale->project_site_name ?? $sale->delivery_address,
                'receiver_name' => $challanData['receiver_name'] ?? null,
                'receiver_phone' => $challanData['receiver_phone'] ?? null,
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'notes' => $challanData['notes'] ?? null,
            ]);

            // Add line items
            $items = $challanData['items'] ?? null;
            if (!empty($items) && is_array($items)) {
                foreach ($items as $item) {
                    $challan->items()->create([
                        'sale_item_id' => $item['sale_item_id'] ?? null,
                        'product_id' => $item['product_id'] ?? null,
                        'product_name' => $item['product_name'] ?? 'Material',
                        'ordered_quantity' => (float) ($item['ordered_quantity'] ?? 1),
                        'dispatched_quantity' => (float) ($item['dispatched_quantity'] ?? $item['ordered_quantity'] ?? 1),
                        'unit_code' => $item['unit_code'] ?? 'pcs',
                        'weight_kg' => isset($item['weight_kg']) ? (float) $item['weight_kg'] : null,
                    ]);
                }
            } else {
                // Auto-create from sale items
                foreach ($sale->items as $saleItem) {
                    $weight = $saleItem->weight_kg;
                    $product = $saleItem->product ?? Product::find($saleItem->product_id);
                    if (($weight === null || (float) $weight === 0.0) && $product) {
                        $calc = $this->calculateDimensionalDetails($product, (float) $saleItem->quantity, $saleItem->dimensional_unit ?: $saleItem->unit_code);
                        $weight = $calc['weight_kg'] > 0 ? $calc['weight_kg'] : null;
                    }

                    $challan->items()->create([
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'product_name' => $saleItem->product_name,
                        'ordered_quantity' => (float) $saleItem->quantity,
                        'dispatched_quantity' => (float) $saleItem->quantity,
                        'unit_code' => $saleItem->unit_code ?: ($product?->unit ?: 'pcs'),
                        'weight_kg' => $weight,
                    ]);
                }
            }

            return $challan->load('items');
        });
    }

    /**
     * Advance Delivery Challan dispatch status.
     */
    public function updateChallanStatus(DeliveryChallan $challan, string $status): DeliveryChallan
    {
        if ($status === 'dispatched') {
            $challan->markDispatched();
        } elseif ($status === 'delivered') {
            $challan->markDelivered();
        } else {
            $challan->update(['status' => $status]);
        }

        return $challan->fresh();
    }

    /**
     * Retrieve Project-Wise Site Ledger Statement for a Contractor.
     */
    public function getProjectSiteLedger(ContractorProject $project): array
    {
        $sales = Sale::where('contractor_project_id', $project->id)
            ->with(['items', 'deliveryChallans'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalBilledMinor = (int) $sales->sum('total_minor');
        $creditSalesMinor = (int) $sales->where('payment_method', 'credit')->sum('total_minor');
        $cashSalesMinor = (int) $sales->where('payment_method', 'cash')->sum('total_minor');
        $challansCount = DeliveryChallan::where('contractor_project_id', $project->id)->count();

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'site_address' => $project->site_address,
                'plot_number' => $project->plot_number,
                'supervisor_name' => $project->supervisor_name,
                'supervisor_phone' => $project->supervisor_phone,
                'status' => $project->status,
                'contractor_name' => $project->customer?->name,
            ],
            'total_billed_minor' => $totalBilledMinor,
            'total_receivable_minor' => $creditSalesMinor,
            'credit_receivable_minor' => $creditSalesMinor,
            'cash_paid_minor' => $cashSalesMinor,
            'sales_count' => $sales->count(),
            'delivery_challans_count' => $challansCount,
            'summary' => [
                'total_billed_minor' => $totalBilledMinor,
                'total_receivable_minor' => $creditSalesMinor,
                'credit_receivable_minor' => $creditSalesMinor,
                'cash_paid_minor' => $cashSalesMinor,
                'sales_count' => $sales->count(),
                'delivery_challans_count' => $challansCount,
            ],
            'sales' => $sales,
        ];
    }
}

