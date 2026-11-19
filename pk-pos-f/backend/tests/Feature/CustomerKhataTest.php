<?php

use App\Actions\CreateSaleAction;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Superstore',
        'ntn' => '7654321-0',
        'province' => 'punjab',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Metro Gulberg Branch',
        'province' => 'punjab',
    ]);

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cashier Usman',
        'email' => 'usman@metro.test',
        'role' => 'cashier',
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Basmati Rice 5kg',
        'price_minor' => 150000, // Rs. 1,500
        'unit' => 'bag',
    ]);
});

test('cashier can create a customer with credit limit via API', function () {
    Sanctum::actingAs($this->cashier);

    $response = $this->postJson('/api/v1/pos/customers', [
        'name' => 'Haji Muhammad Din',
        'phone' => '03001234567',
        'cnic' => '35202-1234567-1',
        'credit_limit_minor' => 5000000, // Rs. 50,000 credit limit
        'city' => 'Lahore',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Customer created successfully.',
            'customer' => [
                'name' => 'Haji Muhammad Din',
                'phone' => '03001234567',
                'credit_limit_minor' => 5000000,
                'current_balance_minor' => 0,
            ],
        ]);

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $this->tenant->id,
        'phone' => '03001234567',
        'credit_limit_minor' => 5000000,
    ]);
});

test('pos can search customers by name or phone query', function () {
    Sanctum::actingAs($this->cashier);

    Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Tariq Mehmood',
        'phone' => '03219876543',
        'credit_limit_minor' => 1000000,
    ]);

    Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Rashid Khan',
        'phone' => '03335551212',
        'credit_limit_minor' => 0,
    ]);

    $res = $this->getJson('/api/v1/pos/customers?q=Tariq');
    $res->assertStatus(200);
    $data = $res->json('customers');
    expect(count($data))->toBe(1);
    expect($data[0]['name'])->toBe('Tariq Mehmood');

    $resByPhone = $this->getJson('/api/v1/pos/customers?q=0333555');
    $resByPhone->assertStatus(200);
    $phoneData = $resByPhone->json('customers');
    expect(count($phoneData))->toBe(1);
    expect($phoneData[0]['name'])->toBe('Rashid Khan');
});

test('credit sale books successfully and increments customer balance and ledger', function () {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Chaudhry Akram',
        'phone' => '03009998877',
        'credit_limit_minor' => 2000000, // Rs. 20,000 credit limit
        'current_balance_minor' => 0,
    ]);

    $action = app(CreateSaleAction::class);
    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'customer_id' => $customer->id,
        'sale_number' => 'INV-20261120-0001',
        'payment_method' => 'credit',
        'items' => [
            [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'hs_code' => '1006.3010',
                'quantity' => 2,
                'unit_price_minor' => 150000,
                'tax_rate_basis_points' => 0,
                'tax_minor' => 0,
                'total_minor' => 300000, // Rs. 3,000
            ],
        ],
    ]);

    expect($sale->payment_method)->toBe('credit');
    expect($sale->customer_id)->toBe($customer->id);

    // Customer balance must have increased by Rs. 3,000 (300,000 minor)
    $customer->refresh();
    expect($customer->current_balance_minor)->toBe(300000);

    // CustomerTransaction debit entry must exist
    $tx = CustomerTransaction::where('customer_id', $customer->id)->first();
    expect($tx)->not->toBeNull();
    expect($tx->type)->toBe('debit_sale');
    expect($tx->amount_minor)->toBe(300000);
    expect($tx->balance_after_minor)->toBe(300000);
    expect($tx->sale_id)->toBe($sale->id);
});

test('credit sale is rejected if customer has 0 credit limit (cash-only account)', function () {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cash Only Buyer',
        'phone' => '03001112233',
        'credit_limit_minor' => 0, // Cash only
        'current_balance_minor' => 0,
    ]);

    $action = app(CreateSaleAction::class);

    expect(fn () => $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'customer_id' => $customer->id,
        'sale_number' => 'INV-20261120-0002',
        'payment_method' => 'credit',
        'items' => [
            [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'hs_code' => '1006.3010',
                'quantity' => 1,
                'unit_price_minor' => 150000,
                'tax_rate_basis_points' => 0,
                'tax_minor' => 0,
                'total_minor' => 150000,
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

test('credit sale is rejected if sale total exceeds customer remaining credit ceiling', function () {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Near Limit Buyer',
        'phone' => '03004445566',
        'credit_limit_minor' => 500000, // Rs. 5,000 credit limit
        'current_balance_minor' => 400000, // Already owes Rs. 4,000 (Rs. 1,000 room)
    ]);

    $action = app(CreateSaleAction::class);

    // Attempt purchase of Rs. 3,000 (exceeds Rs. 1,000 room)
    expect(fn () => $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'customer_id' => $customer->id,
        'sale_number' => 'INV-20261120-0003',
        'payment_method' => 'credit',
        'items' => [
            [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'hs_code' => '1006.3010',
                'quantity' => 2,
                'unit_price_minor' => 150000,
                'tax_rate_basis_points' => 0,
                'tax_minor' => 0,
                'total_minor' => 300000,
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

test('recording debt payment reduces customer balance and logs credit transaction', function () {
    Sanctum::actingAs($this->cashier);

    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Malik Zubair',
        'phone' => '03007776655',
        'credit_limit_minor' => 10000000,
        'current_balance_minor' => 5000000, // Owes Rs. 50,000
    ]);

    $response = $this->postJson("/api/v1/pos/customers/{$customer->id}/payments", [
        'amount_minor' => 2000000, // Repays Rs. 20,000
        'payment_method' => 'raast',
        'notes' => 'Raast Ref #12345678',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Payment of Rs. 20,000 recorded successfully.',
            'customer' => [
                'current_balance_minor' => 3000000, // 50,000 - 20,000 = 30,000 (3,000,000 minor)
            ],
        ]);

    $customer->refresh();
    expect($customer->current_balance_minor)->toBe(3000000);

    // Verify CustomerTransaction
    $tx = CustomerTransaction::where('customer_id', $customer->id)
        ->whereIn('type', ['payment', 'credit_payment'])
        ->first();

    expect($tx)->not->toBeNull();
    expect($tx->amount_minor)->toBe(2000000);
    expect($tx->balance_after_minor)->toBe(3000000);
    expect($tx->payment_method)->toBe('raast');
    expect($tx->receipt_number)->toStartWith('REC-');
});
