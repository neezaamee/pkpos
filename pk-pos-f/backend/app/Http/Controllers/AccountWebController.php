<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountWebController extends Controller
{
    /**
     * Display the Chart of Accounts list & tree.
     */
    public function index(Request $request, AccountingService $accountingService): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $accountingService->provisionDefaultChartOfAccounts($tenant);

        $type = $request->query('type');
        $search = $request->query('search');

        $query = Account::where('tenant_id', $tenant->id)
            ->with(['parent:id,code,name'])
            ->orderBy('code', 'asc');

        if ($type) {
            $query->where('type', $type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $accounts = $query->get();

        $metrics = [
            'total_accounts' => $accounts->count(),
            'assets_count' => $accounts->where('type', 'asset')->count(),
            'liabilities_count' => $accounts->where('type', 'liability')->count(),
            'equity_count' => $accounts->where('type', 'equity')->count(),
            'revenue_count' => $accounts->where('type', 'revenue')->count(),
            'expense_count' => $accounts->where('type', 'expense')->count(),
        ];

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $accounts,
            'filters' => [
                'type' => $type,
                'search' => $search,
            ],
            'metrics' => $metrics,
        ]);
    }

    /**
     * Store a newly created custom account.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenantId = $this->requireTenant($request)->id;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:accounts,code,NULL,id,tenant_id,' . $tenantId],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:asset,liability,equity,revenue,expense'],
            'sub_type' => ['required', 'string', 'max:100'],
            'normal_balance' => ['required', 'string', 'in:debit,credit'],
            'parent_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'opening_balance_minor' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['is_system'] = false;
        $validated['is_active'] = true;
        $validated['current_balance_minor'] = $validated['opening_balance_minor'] ?? 0;

        Account::create($validated);

        return redirect()->route('accounts.index')->with('success', "Account {$validated['code']} - {$validated['name']} created successfully.");
    }

    /**
     * Display chronological General Ledger for an account.
     */
    public function show(Request $request, string $id, FinancialReportService $reportService): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenantId = $this->requireTenant($request)->id;
        $account = Account::where('tenant_id', $tenantId)->findOrFail($id);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $ledgerData = $reportService->getAccountLedger($account, $startDate, $endDate);

        return Inertia::render('Accounting/Accounts/Ledger', [
            'ledger' => $ledgerData,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Retroactively sync past commercial transactions into the General Ledger.
     */
    public function sync(Request $request, AccountingService $accountingService): RedirectResponse
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $results = $accountingService->syncExistingTransactions($tenant);

        return redirect()->route('accounts.index')->with('success', "Historical sync completed: {$results['sales_synced']} sales, {$results['returns_synced']} returns, {$results['grn_synced']} GRN purchases synchronized to the General Ledger.");
    }
}
