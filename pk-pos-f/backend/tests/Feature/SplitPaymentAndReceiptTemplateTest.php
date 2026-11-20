<?php

namespace Tests\Feature;

use App\Actions\CreateSaleAction;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ReceiptTemplate;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Cash & Carry Pakistan',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Lahore Thokar Niaz Baig Branch',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cashier Usman',
        'email' => 'usman@metro.pk',
        'role' => 'manager',
    ]);

    $this->terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'T-01',
        'name' => 'Till 01 Front Counter',
        'device_id' => 'DEV-LHE-01',
        'is_active' => true,
    ]);

    // Provision COA
    $this->accountingService = app(AccountingService::class);
    $this->settings = $this->accountingService->provisionDefaultChartOfAccounts($this->tenant);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Dalda Cooking Oil 5L Tin',
        'price_minor' => 250000, // Rs. 2,500
        'cost_minor' => 200000,  // Rs. 2,000
        'is_active' => true,
    ]);
});

test('pos sale can be settled using split payments across cash and card', function () {
    // Open Register Session
    $session = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'terminal_id' => $this->terminal->id,
        'opening_float_minor' => 1000000, // Rs. 10,000 opening cash float
        'expected_cash_minor' => 1000000,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    $action = app(CreateSaleAction::class);

    // Total sale is Rs. 5,000 (2 tins @ Rs. 2,500)
    // Tendered: Rs. 2,000 Cash + Rs. 3,000 Card
    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'subtotal_minor' => 500000,
        'tax_minor' => 0,
        'total_minor' => 500000,
        'payments' => [
            [
                'payment_method' => 'cash',
                'amount_minor' => 200000, // Rs. 2,000
                'tendered_minor' => 200000,
                'change_minor' => 0,
            ],
            [
                'payment_method' => 'card',
                'amount_minor' => 300000, // Rs. 3,000
                'reference' => 'AUTH-HBL-98124',
            ],
        ],
        'items' => [
            [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'hs_code' => '15121900',
                'quantity' => 2,
                'unit_price_minor' => 250000,
                'line_total_minor' => 500000,
            ],
        ],
    ], $this->user->id);

    expect($sale->payment_method)->toBe('split');
    expect($sale->isSplitPayment())->toBeTrue();
    expect($sale->payments)->toHaveCount(2);

    $cashPayment = $sale->payments->where('payment_method', 'cash')->first();
    $cardPayment = $sale->payments->where('payment_method', 'card')->first();

    expect($cashPayment->amount_minor)->toBe(200000);
    expect($cardPayment->amount_minor)->toBe(300000);
    expect($cardPayment->reference)->toBe('AUTH-HBL-98124');

    // Register session cash drawer tracking:
    // Only the cash portion should increase cash sales and expected cash!
    $session->refresh();
    expect($session->cash_sales_minor)->toBe(200000); // Rs. 2,000
    expect($session->card_sales_minor)->toBe(300000); // Rs. 3,000
    expect($session->gross_sales_minor)->toBe(500000); // Rs. 5,000 total
    expect($session->expected_cash_minor)->toBe(1200000); // Rs. 10,000 float + Rs. 2,000 cash

    // Accounting General Ledger Posting verification:
    $journal = JournalEntry::where('tenant_id', $this->tenant->id)
        ->where('source_type', 'sale')
        ->where('source_id', $sale->id)
        ->first();

    expect($journal)->not->toBeNull();
    expect($journal->status)->toBe('posted');

    // Debits: Cash in Drawer (1011) Rs. 2,000 & Bank Settlement (1012) Rs. 3,000
    $cashDebitLine = $journal->lines->where('account_id', $this->settings->default_cash_account_id)->first();
    $bankDebitLine = $journal->lines->where('account_id', $this->settings->default_bank_account_id)->first();

    expect($cashDebitLine)->not->toBeNull();
    expect($cashDebitLine->debit_minor)->toBe(200000);
    expect($bankDebitLine)->not->toBeNull();
    expect($bankDebitLine->debit_minor)->toBe(300000);

    // Credit: Sales Revenue (4010) Rs. 5,000
    $revenueCreditLine = $journal->lines->where('account_id', $this->settings->default_sales_revenue_account_id)->first();
    expect($revenueCreditLine)->not->toBeNull();
    expect($revenueCreditLine->credit_minor)->toBe(500000);

    // Total Journal Entry Debits must equal Credits
    expect($journal->total_debit_minor)->toBe($journal->total_credit_minor);
});

test('split payment with customer khata charges credit portion accurately to customer ledger', function () {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Chaudhry Riaz Ahmed',
        'phone' => '0300-9876543',
        'credit_limit_minor' => 500000, // Rs. 5,000 limit
        'current_balance_minor' => 100000, // Rs. 1,000 already owed
        'is_active' => true,
    ]);

    $action = app(CreateSaleAction::class);

    // Sale of Rs. 4,000: Rs. 1,500 Cash + Rs. 2,500 Customer Khata Credit
    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'location_id' => $this->location->id,
        'subtotal_minor' => 400000,
        'tax_minor' => 0,
        'total_minor' => 400000,
        'payments' => [
            [
                'payment_method' => 'cash',
                'amount_minor' => 150000,
                'tendered_minor' => 150000,
            ],
            [
                'payment_method' => 'credit',
                'amount_minor' => 250000,
                'reference' => 'Udhaar Book Page 42',
            ],
        ],
        'items' => [
            [
                'product_name' => 'General Provisions',
                'hs_code' => '21039000',
                'quantity' => 1,
                'unit_price_minor' => 400000,
                'line_total_minor' => 400000,
            ],
        ],
    ], $this->user->id);

    // Customer balance should only increment by Rs. 2,500 (credit part), from 1,000 to 3,500
    $customer->refresh();
    expect($customer->current_balance_minor)->toBe(350000);

    // Customer transaction ledger entry
    $tx = $customer->transactions()->where('sale_id', $sale->id)->first();
    expect($tx)->not->toBeNull();
    expect($tx->type)->toBe('debit_sale');
    expect($tx->amount_minor)->toBe(250000); // exactly the credit portion!

    // GL Posting: Debit AR Khata (1030) Rs. 2,500 and Debit Cash (1011) Rs. 1,500
    $journal = JournalEntry::where('tenant_id', $this->tenant->id)
        ->where('source_type', 'sale')
        ->where('source_id', $sale->id)
        ->first();

    $arLine = $journal->lines->where('account_id', $this->settings->default_ar_account_id)->first();
    $cashLine = $journal->lines->where('account_id', $this->settings->default_cash_account_id)->first();

    expect($arLine->debit_minor)->toBe(250000);
    expect($cashLine->debit_minor)->toBe(150000);
    expect($journal->total_debit_minor)->toBe($journal->total_credit_minor);
});

test('store sale api validates split payments sum against invoice total', function () {
    $this->actingAs($this->user);

    $saleId = (string) Str::uuid();

    // Mismatched sum: invoice total is Rs. 1,000, but payments sum is Rs. 800
    $response = $this->postJson('/api/v1/sales', [
        'id' => $saleId,
        'location_id' => $this->location->id,
        'sale_number' => 'INV-TEST-001',
        'payment_method' => 'split',
        'subtotal_minor' => 100000,
        'tax_minor' => 0,
        'total_minor' => 100000,
        'status' => 'completed',
        'payments' => [
            [
                'payment_method' => 'cash',
                'amount_minor' => 50000,
            ],
            [
                'payment_method' => 'card',
                'amount_minor' => 30000, // 500 + 300 = 800 (short by 200)
            ],
        ],
        'items' => [
            [
                'product_name' => 'Sample Item',
                'hs_code' => '21039000',
                'quantity' => 1,
                'unit_price_minor' => 100000,
                'tax_rate_basis_points' => 0,
                'tax_amount_minor' => 0,
                'line_total_minor' => 100000,
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['payments']);
});

test('receipt template customizer can be configured and fetched by pos client', function () {
    $this->actingAs($this->user);

    // 1. Web Controller Edit: loads template with defaults
    $response = $this->get('/settings/receipt-template');
    $response->assertOk();

    // 2. Web Controller Update: save custom branding & 58mm width
    $updateResponse = $this->put('/settings/receipt-template', [
        'business_name' => 'Al-Hafiz Mart & Pharmacy',
        'branch_name' => 'Gulberg Express Outlet',
        'address_line1' => 'Plot #42, Main Boulevard, Gulberg III',
        'phone' => '042-35789123',
        'email' => 'support@alhafiz.pk',
        'website' => 'www.alhafiz.pk',
        'ntn_label' => 'Tax NTN:',
        'strn_label' => 'Sales Tax STRN:',
        'pos_id_label' => 'FBR Tier-1 POS ID:',
        'paper_width' => '58mm',
        'show_tax_breakdown' => true,
        'show_customer_info' => true,
        'show_fbr_qr' => true,
        'header_note' => 'Official Computerized Tax Invoice',
        'footer_policy' => 'Exchanges acceptable within 7 days with intact seal and receipt.',
        'footer_greeting' => 'Thank you for shopping with us! Shukriya.',
    ]);

    $updateResponse->assertRedirect();

    $template = ReceiptTemplate::forTenant($this->tenant);
    expect($template->business_name)->toBe('Al-Hafiz Mart & Pharmacy');
    expect($template->paper_width)->toBe('58mm');
    expect($template->header_note)->toBe('Official Computerized Tax Invoice');

    // 3. POS API endpoint: POS Client downloads active template
    $apiResponse = $this->getJson('/api/v1/pos/receipt-template');
    $apiResponse->assertOk();
    $apiResponse->assertJsonPath('template.business_name', 'Al-Hafiz Mart & Pharmacy');
    $apiResponse->assertJsonPath('template.paper_width', '58mm');
    $apiResponse->assertJsonPath('tenant.id', $this->tenant->id);
});
