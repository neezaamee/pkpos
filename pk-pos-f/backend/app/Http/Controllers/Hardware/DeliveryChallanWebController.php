<?php

namespace App\Http\Controllers\Hardware;

use App\Http\Controllers\Controller;
use App\Models\ContractorProject;
use App\Models\DeliveryChallan;
use App\Models\Sale;
use App\Services\Hardware\HardwareBuildingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryChallanWebController extends Controller
{
    public function __construct(
        protected HardwareBuildingService $hardwareService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenantId = $this->requireTenant($request)->id;
        $status = $request->query('status');

        $query = DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['contractorProject.customer', 'sale', 'items'])
            ->latest('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $challans = $query->paginate(20);
        $projects = ContractorProject::where('tenant_id', $tenantId)->where('status', 'active')->get();

        return Inertia::render('Hardware/Challans/Index', [
            'challans' => $challans,
            'projects' => $projects,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenantId = $this->requireTenant($request)->id;
        $challan = DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['contractorProject.customer', 'sale.customer', 'items.product', 'tenant', 'location'])
            ->findOrFail($id);

        return Inertia::render('Hardware/Challans/Show', [
            'challan' => $challan,
        ]);
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenantId = $this->requireTenant($request)->id;
        $challan = DeliveryChallan::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,dispatched,delivered,cancelled'],
        ]);

        $this->hardwareService->updateChallanStatus($challan, $validated['status']);

        return back()->with('success', "Delivery Challan #{$challan->challan_number} marked as {$validated['status']}.");
    }
}
