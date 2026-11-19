<?php

use App\Actions\CreateSaleAction;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\RegisterSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Superstore',
        'ntn' => '7654321-0',
        'province' => 'sindh',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Metro Clifton Branch',
        'province' => 'sindh',
    ]);

    $this->terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'device_id' => 'POS-CLIFTON-01',
        'name' => 'Register 01',
        'status' => 'active',
        'paired_at' => now(),
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Store Owner',
        'email' => 'owner@metro.test',
        'role' => 'owner',
        'cashier_pin_hash' => Hash::make('9999'),
    ]);

    $this->supervisor = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Shift Supervisor',
        'email' => 'supervisor@metro.test',
        'role' => 'manager',
        'cashier_pin_hash' => Hash::make('8888'),
    ]);

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cashier Ali',
        'email' => 'ali@metro.test',
        'role' => 'cashier',
        'cashier_pin_hash' => Hash::make('1234'),
        'max_discount_pct' => 10,
        'pos_permissions' => ['apply_discount', 'open_drawer'],
    ]);
});

test('cashier can authenticate with 4-digit PIN', function () {
    Sanctum::actingAs($this->cashier);

    $response = $this->postJson('/api/v1/pos/auth/pin', [
        'pin' => '1234',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'user' => [
                'name' => 'Cashier Ali',
                'role' => 'cashier',
                'max_discount_pct' => 10,
            ],
            'message' => 'Welcome, Cashier Ali',
        ]);
});

test('invalid PIN is rejected with 401 Unauthorized', function () {
    Sanctum::actingAs($this->cashier);

    $response = $this->postJson('/api/v1/pos/auth/pin', [
        'pin' => '0000',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid cashier PIN.',
        ]);
});

test('supervisor override succeeds for store manager and owner', function () {
    Sanctum::actingAs($this->cashier);

    // Supervisor PIN 8888 succeeds
    $response = $this->postJson('/api/v1/pos/auth/supervisor-override', [
        'pin' => '8888',
        'action' => 'void_sale',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'authorized' => true,
            'supervisor' => [
                'name' => 'Shift Supervisor',
                'role' => 'manager',
            ],
            'action' => 'void_sale',
        ]);
});

test('supervisor override fails if regular cashier PIN is entered', function () {
    Sanctum::actingAs($this->cashier);

    // Regular Cashier PIN 1234 does not have supervisor rights
    $response = $this->postJson('/api/v1/pos/auth/supervisor-override', [
        'pin' => '1234',
        'action' => 'void_sale',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'authorized' => false,
            'message' => 'Invalid supervisor PIN or unauthorized personnel.',
        ]);
});

test('cashier can open register shift with starting float', function () {
    Sanctum::actingAs($this->cashier);

    $response = $this->postJson('/api/v1/pos/shifts/open', [
        'location_id' => $this->location->id,
        'terminal_id' => $this->terminal->id,
        'opening_float_minor' => 500000, // Rs. 5,000
        'notes' => 'Morning shift start',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Shift opened successfully. Register is now operational.',
            'session' => [
                'status' => 'open',
                'opening_float_minor' => 500000,
                'expected_cash_minor' => 500000,
            ],
        ]);

    $this->assertDatabaseHas('register_sessions', [
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_id' => $this->terminal->id,
        'status' => 'open',
        'opening_float_minor' => 500000,
    ]);

    $this->assertDatabaseHas('cash_drawer_transactions', [
        'tenant_id' => $this->tenant->id,
        'type' => 'opening_float',
        'amount_minor' => 500000,
    ]);
});

test('cannot open a second shift on same terminal if one is already active', function () {
    Sanctum::actingAs($this->cashier);

    RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_id' => $this->terminal->id,
        'user_id' => $this->cashier->id,
        'status' => 'open',
        'opened_at' => now(),
        'opening_float_minor' => 500000,
        'expected_cash_minor' => 500000,
    ]);

    $response = $this->postJson('/api/v1/pos/shifts/open', [
        'location_id' => $this->location->id,
        'terminal_id' => $this->terminal->id,
        'opening_float_minor' => 300000,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'An active shift is already open for this terminal. Please close the active shift first.',
        ]);
});

test('cash movements (drop, in, payout) update expected cash drawer balance', function () {
    Sanctum::actingAs($this->cashier);

    $session = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_id' => $this->terminal->id,
        'user_id' => $this->cashier->id,
        'status' => 'open',
        'opened_at' => now(),
        'opening_float_minor' => 500000, // Rs. 5,000
        'expected_cash_minor' => 500000,
    ]);

    // 1. Record petty expense payout of Rs. 350 (35,000 minor) for store tea
    $payoutRes = $this->postJson('/api/v1/pos/shifts/cash-movement', [
        'register_session_id' => $session->id,
        'type' => 'payout_expense',
        'amount_minor' => 35000,
        'reason' => 'Tea for customers and staff',
    ]);
    $payoutRes->assertStatus(200)
        ->assertJson([
            'expected_cash_minor' => 465000, // 500,000 - 35,000 = 465,000
        ]);

    // 2. Record cash drop of Rs. 1,000 (100,000 minor) to safe
    $dropRes = $this->postJson('/api/v1/pos/shifts/cash-movement', [
        'register_session_id' => $session->id,
        'type' => 'cash_drop',
        'amount_minor' => 100000,
        'reason' => 'Midday safe deposit',
        'supervisor_pin' => '8888',
    ]);
    $dropRes->assertStatus(200)
        ->assertJson([
            'expected_cash_minor' => 365000, // 465,000 - 100,000 = 365,000
        ]);

    // 3. Record cash in of Rs. 500 (50,000 minor) for change coins
    $inRes = $this->postJson('/api/v1/pos/shifts/cash-movement', [
        'register_session_id' => $session->id,
        'type' => 'cash_in',
        'amount_minor' => 50000,
        'reason' => 'Coin roll change replenished',
    ]);
    $inRes->assertStatus(200)
        ->assertJson([
            'expected_cash_minor' => 415000, // 365,000 + 50,000 = 415,000
        ]);
});

test('processing sales automatically feeds active register session and expected cash', function () {
    $session = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_id' => $this->terminal->id,
        'user_id' => $this->cashier->id,
        'status' => 'open',
        'opened_at' => now(),
        'opening_float_minor' => 500000, // Rs. 5,000
        'expected_cash_minor' => 500000,
    ]);

    $action = app(CreateSaleAction::class);

    // Cash sale of Rs. 2,000 (200,000 minor)
    $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'sale_number' => 'SALE-TEST-001',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 170000,
        'tax_minor' => 30000,
        'total_minor' => 200000,
        'status' => 'completed',
        'items' => [
            [
                'product_name' => 'Basmati Rice 5kg',
                'hs_code' => '10063010',
                'quantity' => 1,
                'unit_price_minor' => 170000,
                'tax_rate_basis_points' => 1800,
                'tax_amount_minor' => 30000,
                'line_total_minor' => 200000,
            ],
        ],
    ], $this->cashier->id);

    // Refresh session
    $session->refresh();

    expect($session->sales_count)->toBe(1)
        ->and($session->cash_sales_minor)->toBe(200000)
        ->and($session->expected_cash_minor)->toBe(700000); // 500,000 + 200,000 = 700,000
});

test('cashier can close shift, compute variance, and generate sequential Z-Report', function () {
    Sanctum::actingAs($this->cashier);

    $session = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_id' => $this->terminal->id,
        'user_id' => $this->cashier->id,
        'status' => 'open',
        'opened_at' => now()->subHours(8),
        'opening_float_minor' => 500000, // 5,000
        'cash_sales_minor' => 1500000, // 15,000
        'expected_cash_minor' => 2000000, // 20,000 expected
        'gross_sales_minor' => 1500000,
        'sales_count' => 5,
    ]);

    // Cashier counts 20,200 (2020000 minor) -> Overage of +200
    $response = $this->postJson("/api/v1/pos/shifts/{$session->id}/close", [
        'closing_cash_minor' => 2020000,
        'notes' => 'Evening shift closed, Rs 200 tip left in drawer',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Shift closed successfully. Official Z-Report generated.',
            'session' => [
                'status' => 'closed',
                'closing_cash_minor' => 2020000,
                'variance_minor' => 20000, // +20,000 minor = +Rs. 200
            ],
            'z_report' => [
                'report_type' => 'Z',
                'cash_drawer_reconciliation' => [
                    'expected_cash' => 2000000,
                    'counted_cash' => 2020000,
                    'variance' => 20000,
                    'variance_status' => 'overage',
                ],
            ],
        ]);

    $session->refresh();
    expect($session->status)->toBe('closed')
        ->and($session->z_report_number)->toStartWith('Z-');
});

test('store owner can view shifts index and inspect specific Z-Report in backoffice', function () {
    $session = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_id' => $this->terminal->id,
        'user_id' => $this->cashier->id,
        'closed_by_user_id' => $this->supervisor->id,
        'status' => 'closed',
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subDay()->addHours(8),
        'opening_float_minor' => 500000,
        'cash_sales_minor' => 1200000,
        'expected_cash_minor' => 1700000,
        'closing_cash_minor' => 1700000,
        'variance_minor' => 0,
        'z_report_number' => 'Z-20261118-0001',
    ]);

    $indexRes = $this->actingAs($this->owner)->get('/shifts');
    $indexRes->assertStatus(200);

    $showRes = $this->actingAs($this->owner)->get("/shifts/{$session->id}");
    $showRes->assertStatus(200);
});
