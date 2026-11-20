<?php

namespace Tests\Feature;

use App\Actions\CreateSaleAction;
use App\Actions\ProcessSaleReturnAction;
use App\Models\Account;
use App\Models\AccountingSetting;
use App\Models\CashDrawerTransaction;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Al-Madina Departmental Store',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Commercial Market Branch',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Accounts Manager Tariq',
        'email' => 'tariq@almadina.pk',
        'role' => 'manager',
    ]);

    $this->terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'T-01',
        'name' => 'Till 01 Front Counter',
        'device_id' => 'DEV-KHI-01',
        'is_active' => true,
    ]);

    $this->accountingService = app(AccountingService::class);
    $this->reportService = app(FinancialReportService::class);

    // Initialize COA for tenant
    $this->settings = $this->accountingService->provisionDefaultChartOfAccounts($this->tenant);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Basmati Rice 5kg Super Kernel',
        'price_minor' => 125000, // Rs. 1,250
        'cost_minor' => 95000,   // Rs. 950 (Cost)
        'is_active' => true,
    ]);
});

test('standard pakistani chart of accounts is provisioned with core system accounts', function () {
    $accountsCount = Account::where('tenant_id', $this->tenant->id)->count();
    expect($accountsCount)->toBeGreaterThanOrEqual(20);

    // Verify key accounts exist
    $cash = Account::where('tenant_id', $this->tenant->id)->where('code', '1011')->first();
    $bank = Account::where('tenant_id', $this->tenant->id)->where('code', '1012')->first();
    $ar = Account::where('tenant_id', $this->tenant->id)->where('code', '1030')->first();
    $inv = Account::where('tenant_id', $this->tenant->id)->where('code', '1040')->first();
    $ap = Account::where('tenant_id', $this->tenant->id)->where('code', '2011')->first();
    $tax = Account::where('tenant_id', $this->tenant->id)->where('code', '2020')->first();
    $revenue = Account::where('tenant_id', $this->tenant->id)->where('code', '4010')->first();
    $cogs = Account::where('tenant_id', $this->tenant->id)->where('code', '5010')->first();

    expect($cash)->not->toBeNull()
        ->and($bank)->not->toBeNull()
        ->and($ar)->not->toBeNull()
        ->and($inv)->not->toBeNull()
        ->and($ap)->not->toBeNull()
        ->and($tax)->not->toBeNull()
        ->and($revenue)->not->toBeNull()
        ->and($cogs)->not->toBeNull();

    // Verify accounting settings mapped
    expect($this->settings->default_cash_account_id)->toBe($cash->id)
        ->and($this->settings->default_ar_account_id)->toBe($ar->id)
        ->and($this->settings->default_inventory_account_id)->toBe($inv->id)
        ->and($this->settings->default_ap_account_id)->toBe($ap->id)
        ->and($this->settings->default_cogs_account_id)->toBe($cogs->id);
});

test('pos cash sale automatically creates balanced double-entry journal entry', function () {
    $action = app(CreateSaleAction::class);

    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'payment_method' => 'cash',
        'notes' => 'Counter Cash Sale',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price_minor' => 125000,
                'tax_rate_basis_points' => 1800, // 18% FBR GST
            ],
        ],
    ], $this->user->id);

    // Verify Sale created
    expect($sale->status)->toBe('completed');

    // Verify Journal Entry created automatically
    $entry = JournalEntry::where('tenant_id', $this->tenant->id)
        ->where('source_type', 'sale')
        ->where('source_id', $sale->id)
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe('posted')
        ->and($entry->total_debit_minor)->toBe($entry->total_credit_minor)
        ->and($entry->isBalanced())->toBeTrue();

    // Line check: Total gross = 250,000 + 18% tax (45,000) = 295,000
    // Total cost = 2 * 95,000 = 190,000
    // Total Debits = 295,000 (Cash) + 190,000 (COGS) = 485,000
    // Total Credits = 250,000 (Revenue) + 45,000 (Tax) + 190,000 (Inventory) = 485,000
    expect($entry->total_debit_minor)->toBe(485000)
        ->and($entry->total_credit_minor)->toBe(485000);

    // Check account balances
    $cashAccount = Account::find($this->settings->default_cash_account_id);
    expect($cashAccount->current_balance_minor)->toBe(295000);

    $revenueAccount = Account::find($this->settings->default_sales_revenue_account_id);
    expect($revenueAccount->current_balance_minor)->toBe(250000);

    $cogsAccount = Account::find($this->settings->default_cogs_account_id);
    expect($cogsAccount->current_balance_minor)->toBe(190000);
});

test('pos credit sale books debt to customer khata accounts receivable', function () {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Sheikh Usman Enterprises',
        'phone' => '03214567890',
        'credit_limit_minor' => 1000000, // Rs. 10,000 limit
        'current_balance_minor' => 0,
    ]);

    $action = app(CreateSaleAction::class);

    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'customer_id' => $customer->id,
        'payment_method' => 'credit',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price_minor' => 125000,
                'tax_rate_basis_points' => 0,
            ],
        ],
    ], $this->user->id);

    $entry = JournalEntry::where('source_type', 'sale')->where('source_id', $sale->id)->first();
    expect($entry)->not->toBeNull();

    // Verify AR line
    $arLine = $entry->lines()->where('account_id', $this->settings->default_ar_account_id)->first();
    expect($arLine)->not->toBeNull()
        ->and($arLine->debit_minor)->toBe(125000);

    // Verify Customer Khata AR balance
    $arAccount = Account::find($this->settings->default_ar_account_id);
    expect($arAccount->current_balance_minor)->toBe(125000);
});

test('sales return automatically reverses revenue and restocks inventory in general ledger', function () {
    // 1. Create a sale of 2 units
    $action = app(CreateSaleAction::class);
    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price_minor' => 125000,
                'tax_rate_basis_points' => 0,
            ],
        ],
    ], $this->user->id);

    $saleItem = $sale->items->first();

    // 2. Return 1 unit for cash refund
    $returnAction = app(ProcessSaleReturnAction::class);
    $saleReturn = $returnAction->execute([
        'sale_id' => $sale->id,
        'reason' => 'defective',
        'refund_method' => 'cash',
        'items' => [
            [
                'sale_item_id' => $saleItem->id,
                'quantity_returned' => 1.0,
                'restock_condition' => 'restock_shelf',
            ],
        ],
    ], $this->user);

    $returnEntry = JournalEntry::where('source_type', 'sale_return')->where('source_id', $saleReturn->id)->first();
    expect($returnEntry)->not->toBeNull()
        ->and($returnEntry->isBalanced())->toBeTrue();

    // Return Debits = 125,000 (Sales Return) + 95,000 (Inventory Asset) = 220,000
    // Return Credits = 125,000 (Cash) + 95,000 (COGS) = 220,000
    expect($returnEntry->total_debit_minor)->toBe(220000)
        ->and($returnEntry->total_credit_minor)->toBe(220000);

    $salesReturnAcc = Account::find($this->settings->default_sales_return_account_id);
    expect($salesReturnAcc->current_balance_minor)->toBe(125000);
});

test('goods received note GRN capitalizes inventory asset and creates accounts payable', function () {
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Engro Rice Mills Ltd',
        'company_name' => 'Engro Foods',
        'phone' => '021111222333',
        'current_balance_minor' => 0,
    ]);

    $po = PurchaseOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'po_number' => 'PO-202611-0099',
        'status' => 'issued',
        'total_minor' => 950000,
    ]);

    $poItem = $po->items()->create([
        'product_id' => $this->product->id,
        'ordered_quantity' => 10,
        'received_quantity' => 0,
        'cost_price_minor' => 95000,
        'line_total_minor' => 950000,
    ]);

    // Receive full shipment
    $po->receiveGoods([
        [
            'po_item_id' => $poItem->id,
            'quantity_received' => 10,
        ],
    ], $this->user);

    $grnEntry = JournalEntry::where('source_type', 'purchase_order_grn')->where('source_id', $po->id)->first();
    expect($grnEntry)->not->toBeNull()
        ->and($grnEntry->isBalanced())->toBeTrue();

    // Line 1: Debit Inventory Asset = 950,000
    // Line 2: Credit Accounts Payable = 950,000
    expect($grnEntry->total_debit_minor)->toBe(950000)
        ->and($grnEntry->total_credit_minor)->toBe(950000);

    $apAccount = Account::find($this->settings->default_ap_account_id);
    expect($apAccount->current_balance_minor)->toBe(950000);
});

test('manual journal entry validates debits equal credits and rejects unbalanced input', function () {
    // 1. Balanced entry: Capital injection of Rs. 500,000 into Meezan Bank
    $bankAcc = Account::where('tenant_id', $this->tenant->id)->where('code', '1012')->first();
    $equityAcc = Account::where('tenant_id', $this->tenant->id)->where('code', '3010')->first();

    $entry = $this->accountingService->postManualJournal([
        'entry_date' => now()->toDateString(),
        'reference' => 'CAP-INJ-001',
        'description' => 'Owner Capital Injection via Meezan Bank Cheque',
        'lines' => [
            [
                'account_id' => $bankAcc->id,
                'debit_minor' => 50000000, // Rs. 500,000
                'credit_minor' => 0,
                'memo' => 'Meezan Bank Deposit',
            ],
            [
                'account_id' => $equityAcc->id,
                'debit_minor' => 0,
                'credit_minor' => 50000000, // Rs. 500,000
                'memo' => "Owner's Capital Contribution",
            ],
        ],
    ], $this->user);

    expect($entry->status)->toBe('posted')
        ->and($entry->total_debit_minor)->toBe(50000000)
        ->and($entry->total_credit_minor)->toBe(50000000);

    // 2. Unbalanced entry attempt should throw DomainException
    expect(fn () => $this->accountingService->postManualJournal([
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_id' => $bankAcc->id, 'debit_minor' => 100000, 'credit_minor' => 0],
            ['account_id' => $equityAcc->id, 'debit_minor' => 0, 'credit_minor' => 50000], // Unbalanced!
        ],
    ], $this->user))->toThrow(DomainException::class);
});

test('financial reporting engine generates accurate trial balance, profit & loss, and balance sheet', function () {
    // 1. Post a cash sale: Gross 250,000, Cost 190,000
    $saleAction = app(CreateSaleAction::class);
    $saleAction->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price_minor' => 125000,
                'tax_rate_basis_points' => 0,
            ],
        ],
    ], $this->user->id);

    // 2. Verify Trial Balance equilibrium
    $trialBalance = $this->reportService->getTrialBalance($this->tenant);
    expect($trialBalance['is_balanced'])->toBeTrue()
        ->and($trialBalance['total_debits_minor'])->toBe($trialBalance['total_credits_minor'])
        ->and($trialBalance['difference_minor'])->toBe(0);

    // 3. Verify Profit & Loss: Net Revenue = 250,000, COGS = 190,000, Gross Profit = 60,000
    $pnl = $this->reportService->getProfitAndLoss($this->tenant);
    expect($pnl['revenue']['net_sales_revenue_minor'])->toBe(250000)
        ->and($pnl['cogs']['total_cogs_minor'])->toBe(190000)
        ->and($pnl['gross_profit_minor'])->toBe(60000)
        ->and($pnl['net_profit_minor'])->toBe(60000);

    // 4. Verify Balance Sheet equilibrium: Assets = Liabilities + Equity
    $balanceSheet = $this->reportService->getBalanceSheet($this->tenant);
    expect($balanceSheet['is_balanced'])->toBeTrue()
        ->and($balanceSheet['assets']['total_assets_minor'])->toBe($balanceSheet['total_liabilities_and_equity_minor'])
        ->and($balanceSheet['difference_minor'])->toBe(0);
});
