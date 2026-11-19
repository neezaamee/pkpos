<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierWebController extends Controller
{
    /**
     * Display a listing of suppliers and Accounts Payable overview.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $q = $request->query('q');

        $query = Supplier::where('tenant_id', $tenantId);

        if (! empty($q)) {
            $term = trim($q);
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('contact_person', 'ilike', "%{$term}%")
                    ->orWhere('ntn', 'like', "%{$term}%")
                    ->orWhere('city', 'ilike', "%{$term}%");
            });
        }

        $suppliers = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();

        $totalPayablesMinor = (int) Supplier::where('tenant_id', $tenantId)
            ->where('current_balance_minor', '>', 0)
            ->sum('current_balance_minor');

        $creditorsCount = Supplier::where('tenant_id', $tenantId)
            ->where('current_balance_minor', '>', 0)
            ->count();

        $totalSuppliers = Supplier::where('tenant_id', $tenantId)->count();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => ['q' => $q],
            'metrics' => [
                'total_payables_minor' => $totalPayablesMinor,
                'creditors_count' => $creditorsCount,
                'total_suppliers' => $totalSuppliers,
            ],
        ]);
    }

    /**
     * Show form for creating a new supplier.
     */
    public function create(): Response
    {
        return Inertia::render('Suppliers/Create');
    }

    /**
     * Store a newly created supplier.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'strn' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['payment_terms_days'] = $validated['payment_terms_days'] ?? 0;
        $validated['current_balance_minor'] = 0;
        $validated['is_active'] = true;

        $supplier = Supplier::create($validated);

        return redirect()->route('suppliers.show', $supplier->id)
            ->with('success', "Supplier '{$supplier->name}' registered successfully.");
    }

    /**
     * Display a specific supplier's statement, payables balance, and ledger history.
     */
    public function show(Request $request, string $id): Response
    {
        $tenantId = $request->user()->tenant_id;
        $supplier = Supplier::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $transactions = SupplierTransaction::where('tenant_id', $tenantId)
            ->where('supplier_id', $supplier->id)
            ->with(['purchaseOrder:id,po_number,total_minor,created_at', 'recordedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $purchaseOrders = $supplier->purchaseOrders()
            ->with(['location:id,name'])
            ->limit(10)
            ->get();

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'transactions' => $transactions,
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    /**
     * Record a payment made to a supplier (reducing accounts payable balance).
     */
    public function recordPayment(Request $request, string $id): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $supplier = Supplier::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,cheque,raast'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $tx = $supplier->recordPayment(
            $validated['amount_minor'],
            $validated['payment_method'],
            $request->user(),
            $validated['notes'] ?? null,
            $validated['reference_number'] ?? null
        );

        return redirect()->route('suppliers.show', $supplier->id)
            ->with('success', "Disbursement of Rs. " . number_format($validated['amount_minor'] / 100) . " recorded. Receipt #{$tx->receipt_number}");
    }
}
