<?php

namespace App\Http\Controllers\Hardware;

use App\Http\Controllers\Controller;
use App\Models\ContractorProject;
use App\Models\Customer;
use App\Services\Hardware\HardwareBuildingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectWebController extends Controller
{
    public function __construct(
        protected HardwareBuildingService $hardwareService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenantId = $this->requireTenant($request)->id;
        $customerId = $request->query('customer_id');

        $query = ContractorProject::where('tenant_id', $tenantId)
            ->with(['customer', 'sales'])
            ->orderBy('created_at', 'desc');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $projects = $query->paginate(20);
        $customers = Customer::where('tenant_id', $tenantId)->orderBy('name')->get();

        return Inertia::render('Hardware/Projects/Index', [
            'projects' => $projects,
            'customers' => $customers,
            'filters' => [
                'customer_id' => $customerId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenantId = $this->requireTenant($request)->id;

        $validated = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:150'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'plot_number' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'supervisor_name' => ['nullable', 'string', 'max:100'],
            'supervisor_phone' => ['nullable', 'string', 'max:50'],
            'credit_limit_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,completed,on_hold'],
            'notes' => ['nullable', 'string'],
        ]);

        ContractorProject::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'status' => $validated['status'] ?? 'active',
        ]));

        return back()->with('success', 'Contractor project site created successfully.');
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenantId = $this->requireTenant($request)->id;
        $project = ContractorProject::where('tenant_id', $tenantId)->with('customer')->findOrFail($id);

        $ledger = $this->hardwareService->getProjectSiteLedger($project);

        return Inertia::render('Hardware/Projects/Show', [
            'project' => $project,
            'ledger' => $ledger,
        ]);
    }
}
