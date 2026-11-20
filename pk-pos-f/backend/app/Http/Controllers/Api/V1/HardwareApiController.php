<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContractorProject;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\Sale;
use App\Services\Hardware\HardwareBuildingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HardwareApiController extends Controller
{
    public function __construct(
        protected HardwareBuildingService $hardwareService
    ) {
    }

    /**
     * Get active construction projects / sites for a specific contractor / customer.
     */
    public function getContractorProjects(Request $request, string $customerId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        $projects = ContractorProject::where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('name')
            ->get();

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'current_balance_minor' => $customer->current_balance_minor,
            ],
            'projects' => $projects,
        ]);
    }

    /**
     * Create a new project / site for a contractor.
     */
    public function storeContractorProject(Request $request, string $customerId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'plot_number' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'supervisor_name' => ['nullable', 'string', 'max:100'],
            'supervisor_phone' => ['nullable', 'string', 'max:50'],
            'credit_limit_minor' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = ContractorProject::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'status' => 'active',
        ]));

        return response()->json([
            'message' => "Project {$project->name} registered for {$customer->name}.",
            'project' => $project,
        ], 201);
    }

    /**
     * Get site project ledger statement.
     */
    public function getProjectLedger(Request $request, string $projectId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $project = ContractorProject::where('tenant_id', $tenantId)->with('customer')->findOrFail($projectId);

        $ledger = $this->hardwareService->getProjectSiteLedger($project);

        return response()->json([
            'ledger' => $ledger,
        ]);
    }

    /**
     * Get tiered bulk wholesale prices for a product.
     */
    public function getProductPriceTiers(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product = Product::where('tenant_id', $tenantId)->findOrFail($productId);

        $tiers = $product->priceTiers;

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'base_price_minor' => $product->price_minor,
                'unit' => $product->unit,
                'dimensional_unit' => $product->dimensional_unit,
                'weight_kg_per_unit' => $product->weight_kg_per_unit,
            ],
            'tiers' => $tiers,
        ]);
    }

    /**
     * List Delivery Challans.
     */
    public function getDeliveryChallans(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $status = $request->query('status');
        $projectId = $request->query('project_id');

        $query = DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['contractorProject.customer', 'sale', 'items'])
            ->latest('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($projectId) {
            $query->where('contractor_project_id', $projectId);
        }

        $challans = $query->paginate(25);

        return response()->json($challans);
    }

    /**
     * Create delivery challan for a sale.
     */
    public function storeDeliveryChallan(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'contractor_project_id' => ['nullable', 'uuid', 'exists:contractor_projects,id'],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:50'],
            'transporter_name' => ['nullable', 'string', 'max:100'],
            'freight_minor' => ['nullable', 'integer', 'min:0'],
            'freight_payment_type' => ['nullable', 'string', 'in:paid_by_store,to_pay_customer'],
            'destination_site' => ['nullable', 'string', 'max:255'],
            'receiver_name' => ['nullable', 'string', 'max:100'],
            'receiver_phone' => ['nullable', 'string', 'max:50'],
            'items' => ['nullable', 'array'],
        ]);

        $sale = Sale::where('tenant_id', $tenantId)->findOrFail($validated['sale_id']);

        $challan = $this->hardwareService->createDeliveryChallan($sale, $validated);

        return response()->json([
            'message' => "Delivery Challan #{$challan->challan_number} issued successfully.",
            'challan' => $challan,
        ], 201);
    }

    /**
     * Update Delivery Challan status (e.g. dispatched, delivered).
     */
    public function updateChallanStatus(Request $request, string $challanId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $challan = DeliveryChallan::where('tenant_id', $tenantId)->findOrFail($challanId);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,dispatched,delivered,cancelled'],
        ]);

        $updated = $this->hardwareService->updateChallanStatus($challan, $validated['status']);

        return response()->json([
            'message' => "Challan #{$updated->challan_number} status updated to {$updated->status}.",
            'challan' => $updated,
        ]);
    }
}

