<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerWebController extends Controller
{
    /**
     * Display a listing of customers and receivables overview.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $q = $request->query('q');

        $query = Customer::where('tenant_id', $tenantId);

        if (! empty($q)) {
            $term = trim($q);
            $query->where(function ($sub) use ($term) {
                $sub->where('phone', 'like', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                    ->orWhere('cnic', 'like', "%{$term}%");
            });
        }

        $customers = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();

        $totalReceivablesMinor = (int) Customer::where('tenant_id', $tenantId)
            ->where('current_balance_minor', '>', 0)
            ->sum('current_balance_minor');

        $debtorsCount = Customer::where('tenant_id', $tenantId)
            ->where('current_balance_minor', '>', 0)
            ->count();

        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['q' => $q],
            'metrics' => [
                'total_receivables_minor' => $totalReceivablesMinor,
                'debtors_count' => $debtorsCount,
                'total_customers' => $totalCustomers,
            ],
        ]);
    }

    /**
     * Show form for creating a new customer.
     */
    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'strn' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'credit_limit_minor' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['credit_limit_minor'] = $validated['credit_limit_minor'] ?? 0;
        $validated['current_balance_minor'] = 0;

        $customer = Customer::create($validated);

        return redirect()->route('customers.show', $customer->id)
            ->with('success', "Customer '{$customer->name}' registered successfully.");
    }

    /**
     * Display a specific customer's Khata statement and transaction history.
     */
    public function show(Request $request, string $id): Response
    {
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $transactions = CustomerTransaction::where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->with(['sale:id,sale_number,total_minor,created_at', 'recordedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Record a debt repayment for a customer.
     */
    public function recordPayment(Request $request, string $id): RedirectResponse
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

        $tx = $customer->recordPayment(
            $validated['amount_minor'],
            $validated['payment_method'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return redirect()->route('customers.show', $customer->id)
            ->with('success', "Payment of Rs. " . number_format($validated['amount_minor'] / 100) . " recorded. Receipt #{$tx->receipt_number}");
    }
}
