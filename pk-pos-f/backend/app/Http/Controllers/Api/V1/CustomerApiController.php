<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    /**
     * Search and list customers for fast lookup during POS checkout.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $q = $request->query('q');

        $query = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true);

        if (! empty($q)) {
            $term = trim($q);
            $query->where(function ($sub) use ($term) {
                $sub->where('phone', 'like', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                    ->orWhere('cnic', 'like', "%{$term}%");
            });
        }

        $customers = $query->orderBy('name', 'asc')->paginate(20);

        return response()->json([
            'customers' => $customers->items(),
            'data' => $customers->items(),
            'current_page' => $customers->currentPage(),
            'last_page' => $customers->lastPage(),
            'total' => $customers->total(),
        ]);
    }

    /**
     * Register a new customer from the POS terminal.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'credit_limit_minor' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['credit_limit_minor'] = $validated['credit_limit_minor'] ?? 0;
        $validated['current_balance_minor'] = 0;

        $customer = Customer::create($validated);

        return response()->json([
            'message' => 'Customer created successfully.',
            'customer' => $customer,
        ], 201);
    }

    /**
     * Display a specific customer with balance and statistics.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($customer);
    }

    /**
     * Get paginated customer Khata / ledger transaction history.
     */
    public function ledger(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $transactions = CustomerTransaction::where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->with(['sale:id,sale_number,total_minor,created_at', 'recordedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'current_balance_minor' => $customer->current_balance_minor,
                'credit_limit_minor' => $customer->credit_limit_minor,
            ],
            'transactions' => $transactions,
        ]);
    }

    /**
     * Record a customer debt settlement payment directly from the terminal.
     */
    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,card,raast,bank_transfer,cheque'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction = $customer->recordPayment(
            $validated['amount_minor'],
            $validated['payment_method'],
            $request->user(),
            $validated['notes'] ?? null
        );

        $amountFormatted = number_format($validated['amount_minor'] / 100);

        return response()->json([
            'message' => "Payment of Rs. {$amountFormatted} recorded successfully.",
            'receipt_number' => $transaction->receipt_number,
            'transaction' => $transaction,
            'customer' => $customer->fresh(),
            'new_balance_minor' => $customer->current_balance_minor,
        ]);
    }
}
