<?php

use App\Actions\CreateSaleAction;
use App\Actions\ProcessSaleReturnAction;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductUomConversion;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Cash & Carry',
        'ntn' => '7654321-0',
        'province' => 'punjab',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Lahore Model Town Branch',
        'province' => 'punjab',
    ]);

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cashier Bilal',
        'email' => 'bilal@metro.test',
        'role' => 'cashier',
    ]);

    $this->terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'TERM-MT-01',
        'device_id' => 'POS-MT-01',
        'name' => 'Till 01',
        'status' => 'active',
        'paired_at' => now(),
    ]);

    // Regular retail product
    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Surf Excel 1kg',
        'sku' => 'SURF-1KG',
        'barcode' => '896400055001',
        'hs_code' => '34022000',
        'unit' => 'pcs',
        'price_minor' => 75000, // Rs. 750
        'cost_minor' => 60000,
        'tax_category' => 'general_goods',
        'is_active' => true,
    ]);

    // Product with packaging conversion
    $this->cartonProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Nestle Milkpak 1L',
        'sku' => 'NESTLE-1L',
        'barcode' => '896400055002',
        'hs_code' => '04012000',
        'unit' => 'pcs',
        'price_minor' => 28000, // Rs. 280 per pack
        'cost_minor' => 24000,
        'tax_category' => 'general_goods',
        'is_active' => true,
    ]);

    ProductUomConversion::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->cartonProduct->id,
        'unit_name' => 'Carton of 12',
        'unit_code' => 'ctn',
        'conversion_factor' => 12.0,
        'price_minor' => 320000, // Rs. 3,200
        'cost_minor' => 270000,
        'is_active' => true,
    ]);
});

test('system correctly calculates returnable quantities and blocks excessive returns', function () {
    // 1. Create a completed sale of 3 units
    $sale = Sale::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'sale_number' => 'INV-20261124-0001',
        'payment_method' => 'cash',
        'subtotal_minor' => 225000,
        'tax_minor' => 0,
        'total_minor' => 225000,
        'status' => 'completed',
        'fbr_usin' => 'USIN-112233',
    ]);

    $saleItem = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'hs_code' => $this->product->hs_code,
        'unit_code' => 'pcs',
        'unit_conversion_factor' => 1.0,
        'quantity' => 3.0,
        'unit_price_minor' => 75000,
        'tax_rate_basis_points' => 0,
        'tax_amount_minor' => 0,
        'line_total_minor' => 225000,
    ]);

    // Check returnable items helper
    $returnable = $sale->getReturnableItems();
    expect($returnable)->toHaveCount(1)
        ->and($returnable[0]['returnable_quantity'])->toBe(3.0);

    $action = app(ProcessSaleReturnAction::class);

    // Attempting to return 4 units when only 3 were purchased must fail with validation error
    expect(fn () => $action->execute([
        'sale_id' => $sale->id,
        'reason' => 'defective',
        'refund_method' => 'cash',
        'items' => [
            [
                'sale_item_id' => $saleItem->id,
                'quantity_returned' => 4.0,
                'restock_condition' => 'restock_shelf',
            ],
        ],
    ], $this->cashier))->toThrow(ValidationException::class);
});

test('sales return with shelf restock policy increments physical stock inventory', function () {
    $sale = Sale::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'sale_number' => 'INV-20261124-0002',
        'payment_method' => 'cash',
        'subtotal_minor' => 150000,
        'tax_minor' => 0,
        'total_minor' => 150000,
        'status' => 'completed',
        'fbr_usin' => 'USIN-445566',
    ]);

    $saleItem = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'hs_code' => $this->product->hs_code,
        'unit_code' => 'pcs',
        'unit_conversion_factor' => 1.0,
        'quantity' => 2.0,
        'unit_price_minor' => 75000,
        'tax_rate_basis_points' => 0,
        'tax_amount_minor' => 0,
        'line_total_minor' => 150000,
    ]);

    // Set initial on hand to 5
    StockLevel::updateOrCreate(
        ['product_id' => $this->product->id, 'location_id' => $this->location->id],
        ['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 5.0]
    );

    $action = app(ProcessSaleReturnAction::class);

    // Return 1 unit in pristine condition (restock_shelf)
    $saleReturn = $action->execute([
        'sale_id' => $sale->id,
        'reason' => 'customer_change_of_mind',
        'refund_method' => 'cash',
        'items' => [
            [
                'sale_item_id' => $saleItem->id,
                'quantity_returned' => 1.0,
                'restock_condition' => 'restock_shelf',
            ],
        ],
    ], $this->cashier);

    // 1. Verify Return record created with FBR credit note number
    expect($saleReturn->return_number)->toStartWith('RET-')
        ->and($saleReturn->fbr_credit_note_number)->toStartWith('CN-USIN-445566-')
        ->and($saleReturn->total_refund_minor)->toBe(75000)
        ->and($saleReturn->fbr_status)->toBe('submitted');

    // 2. Verify physical stock increased by 1 (5 -> 6)
    $stockAfter = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->value('quantity_on_hand');
    expect((float) $stockAfter)->toBe(6.0);

    // 3. Verify return movement logged
    $movement = StockMovement::where('product_id', $this->product->id)
        ->where('movement_type', 'return')
        ->latest()
        ->first();
    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(1.0);
});

test('cash refund deducts expected cash drawer balance from active shift session', function () {
    // Open a register shift with starting float of Rs. 10,000
    $session = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'terminal_id' => $this->terminal->id,
        'opened_at' => now(),
        'opening_float_minor' => 1000000,
        'expected_cash_minor' => 1000000,
        'status' => 'open',
    ]);

    $sale = Sale::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'sale_number' => 'INV-20261124-0003',
        'payment_method' => 'cash',
        'subtotal_minor' => 75000,
        'tax_minor' => 0,
        'total_minor' => 75000,
        'status' => 'completed',
    ]);

    $saleItem = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'hs_code' => $this->product->hs_code,
        'unit_code' => 'pcs',
        'unit_conversion_factor' => 1.0,
        'quantity' => 1.0,
        'unit_price_minor' => 75000,
        'tax_rate_basis_points' => 0,
        'tax_amount_minor' => 0,
        'line_total_minor' => 75000,
    ]);

    $action = app(ProcessSaleReturnAction::class);

    // Refund Rs. 750 in cash from this drawer
    $saleReturn = $action->execute([
        'sale_id' => $sale->id,
        'reason' => 'defective',
        'refund_method' => 'cash',
        'items' => [
            [
                'sale_item_id' => $saleItem->id,
                'quantity_returned' => 1.0,
                'restock_condition' => 'damaged_quarantine',
            ],
        ],
    ], $this->cashier, $session);

    // Verify expected cash reduced: 10,000 - 750 = 9,250 (925,000 minor)
    $session->refresh();
    expect($session->expected_cash_minor)->toBe(925000);
});

test('sales return can credit customer khata ledger reducing outstanding udhaar balance', function () {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Chaudhry Akram',
        'phone' => '03001239876',
        'credit_limit_minor' => 5000000,
        'current_balance_minor' => 200000, // Owes Rs. 2,000
    ]);

    $sale = Sale::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'sale_number' => 'INV-20261124-0004',
        'payment_method' => 'credit',
        'subtotal_minor' => 75000,
        'tax_minor' => 0,
        'total_minor' => 75000,
        'status' => 'completed',
    ]);

    $saleItem = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'hs_code' => $this->product->hs_code,
        'unit_code' => 'pcs',
        'unit_conversion_factor' => 1.0,
        'quantity' => 1.0,
        'unit_price_minor' => 75000,
        'tax_rate_basis_points' => 0,
        'tax_amount_minor' => 0,
        'line_total_minor' => 75000,
    ]);

    $action = app(ProcessSaleReturnAction::class);

    // Customer returns item and receives Credit Note adjustment on their Khata
    $saleReturn = $action->execute([
        'sale_id' => $sale->id,
        'reason' => 'wrong_item',
        'refund_method' => 'credit_note_khata',
        'items' => [
            [
                'sale_item_id' => $saleItem->id,
                'quantity_returned' => 1.0,
                'restock_condition' => 'restock_shelf',
            ],
        ],
    ], $this->cashier);

    // Customer balance must decrease by Rs. 750 (2,000 - 750 = Rs. 1,250 / 125,000 minor)
    $customer->refresh();
    expect($customer->current_balance_minor)->toBe(125000);
});

test('api endpoints allow verifying invoice and processing pos returns', function () {
    $sale = Sale::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'sale_number' => 'INV-20261124-0005',
        'payment_method' => 'cash',
        'subtotal_minor' => 75000,
        'tax_minor' => 0,
        'total_minor' => 75000,
        'status' => 'completed',
        'fbr_usin' => 'USIN-778899',
    ]);

    $saleItem = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'hs_code' => $this->product->hs_code,
        'unit_code' => 'pcs',
        'unit_conversion_factor' => 1.0,
        'quantity' => 1.0,
        'unit_price_minor' => 75000,
        'tax_rate_basis_points' => 0,
        'tax_amount_minor' => 0,
        'line_total_minor' => 75000,
    ]);

    Sanctum::actingAs($this->cashier);

    // 1. Verify invoice endpoint
    $verifyRes = $this->postJson('/api/v1/pos/returns/verify-invoice', [
        'invoice_number' => 'INV-20261124-0005',
    ]);
    $verifyRes->assertOk()
        ->assertJsonPath('sale.sale_number', 'INV-20261124-0005')
        ->assertJsonCount(1, 'returnable_items');

    // 2. Submit POS return endpoint
    $returnRes = $this->postJson('/api/v1/pos/returns', [
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
    ]);

    $returnRes->assertStatus(201)
        ->assertJsonPath('sale_return.fbr_status', 'submitted');

    $returnId = $returnRes->json('sale_return.id');

    // 3. Retrieve return receipt endpoint
    $showRes = $this->getJson("/api/v1/pos/returns/{$returnId}");
    $showRes->assertOk()
        ->assertJsonPath('sale_return.id', $returnId);
});
