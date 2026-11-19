<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierApiController extends Controller
{
    /**
     * Search and list suppliers for procurement.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $q = $request->query('q');

        $query = Supplier::where('tenant_id', $tenantId)
            ->where('is_active', true);

        if (! empty($q)) {
            $term = trim($q);
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('contact_person', 'ilike', "%{$term}%")
                    ->orWhere('ntn', 'like', "%{$term}%");
            });
        }

        $suppliers = $query->orderBy('name', 'asc')->limit(50)->get();

        return response()->json([
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a newly registered supplier.
     */
    public function store(Request $request): JsonResponse
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
            'payment_terms_days' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['current_balance_minor'] = 0;
        $validated['is_active'] = true;

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Supplier registered successfully.',
            'supplier' => $supplier,
        ], 201);
    }

    /**
     * Retrieve supplier profile and balance.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $supplier = Supplier::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'supplier' => $supplier,
        ]);
    }

    /**
     * Retrieve supplier Accounts Payable ledger history.
     */
    public function ledger(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $supplier = Supplier::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $transactions = SupplierTransaction::where('tenant_id', $tenantId)
            ->where('supplier_id', $supplier->id)
            ->with(['purchaseOrder:id,po_number,total_minor', 'recordedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json([
            'supplier' => $supplier,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Record a payment made to the supplier.
     */
    public function recordPayment(Request $request, string $id): JsonResponse
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

        return response()->json([
            'message' => 'Payment to supplier recorded successfully.',
            'transaction' => $tx,
            'supplier' => $supplier->fresh(),
        ]);
    }
}
