<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Supplier;
use App\Services\Pharmacy\BatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BatchWebController extends Controller
{
    public function __construct(protected BatchService $batchService)
    {
    }

    /**
     * Display pharmaceutical batches index and expiry monitoring dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;
        $filter = $request->query('filter', 'all'); // 'all', 'expiring_30', 'expiring_60', 'expiring_90', 'expired', 'quarantined'
        $search = $request->query('search');

        $query = ProductBatch::with(['product', 'location', 'supplier'])
            ->where('tenant_id', $tenant->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'ilike', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('generic_name', 'ilike', "%{$search}%")
                            ->orWhere('drap_registration_number', 'ilike', "%{$search}%");
                    });
            });
        }

        $today = now()->toDateString();

        switch ($filter) {
            case 'expiring_30':
                $query->where('status', 'active')
                    ->where('quantity_on_hand', '>', 0)
                    ->whereBetween('expiry_date', [$today, now()->addDays(30)->toDateString()]);
                break;
            case 'expiring_60':
                $query->where('status', 'active')
                    ->where('quantity_on_hand', '>', 0)
                    ->whereBetween('expiry_date', [$today, now()->addDays(60)->toDateString()]);
                break;
            case 'expiring_90':
                $query->where('status', 'active')
                    ->where('quantity_on_hand', '>', 0)
                    ->whereBetween('expiry_date', [$today, now()->addDays(90)->toDateString()]);
                break;
            case 'expired':
                $query->where(function ($q) use ($today) {
                    $q->where('status', 'expired')
                        ->orWhere('expiry_date', '<', $today);
                });
                break;
            case 'quarantined':
                $query->where('status', 'quarantined');
                break;
            default:
                // Active first
                break;
        }

        $batches = $query->orderBy('expiry_date', 'asc')
            ->paginate(20)
            ->withQueryString();

        // Metrics for summary cards
        $totalActiveBatches = ProductBatch::where('tenant_id', $tenant->id)->where('status', 'active')->count();
        $expiringCount = ProductBatch::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->whereBetween('expiry_date', [$today, now()->addDays(90)->toDateString()])
            ->count();
        $expiredCount = ProductBatch::where('tenant_id', $tenant->id)
            ->where(function ($q) use ($today) {
                $q->where('status', 'expired')->orWhere('expiry_date', '<', $today);
            })->count();

        $products = Product::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->select('id', 'name', 'generic_name', 'drap_registration_number', 'unit')
            ->orderBy('name')
            ->get();

        $locations = Location::where('tenant_id', $tenant->id)->get();
        $suppliers = Supplier::where('tenant_id', $tenant->id)->get();

        return Inertia::render('Pharmacy/Batches/Index', [
            'batches' => $batches,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
            ],
            'metrics' => [
                'total_active' => $totalActiveBatches,
                'expiring_90_days' => $expiringCount,
                'expired_count' => $expiredCount,
            ],
            'products' => $products,
            'locations' => $locations,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a newly registered medicine batch.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'batch_number' => ['required', 'string', 'max:50'],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date'],
            'quantity_on_hand' => ['required', 'numeric', 'min:0'],
            'mrp_minor' => ['nullable', 'integer', 'min:0'],
            'cost_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,quarantined,recalled,expired'],
        ]);

        $validated['status'] = $validated['status'] ?? 'active';
        $this->batchService->recordBatch($tenant, $validated);

        return redirect()->back()->with('success', "Batch {$validated['batch_number']} registered successfully.");
    }

    /**
     * Update an existing batch (e.g. quantity adjustment, recall, quarantine).
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;
        $batch = ProductBatch::where('tenant_id', $tenant->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'quantity_on_hand' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'in:active,quarantined,recalled,expired'],
            'expiry_date' => ['sometimes', 'date'],
            'mrp_minor' => ['nullable', 'integer', 'min:0'],
            'cost_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $batch->update($validated);

        return redirect()->back()->with('success', "Batch {$batch->batch_number} updated successfully.");
    }

    /**
     * Quarantine all expired batches.
     */
    public function quarantineExpired(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;
        $count = $this->batchService->quarantineExpiredBatches($tenant);

        return redirect()->back()->with('success', "{$count} expired batches quarantined successfully.");
    }

    protected function authorizeAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, ['owner', 'manager']),
            403,
            'Unauthorized. Requires owner or manager role.'
        );
    }
}
