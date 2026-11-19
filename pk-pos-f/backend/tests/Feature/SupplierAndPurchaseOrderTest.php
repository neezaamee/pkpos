<?php

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductUomConversion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Cash & Carry',
        'ntn' => '7654321-0',
        'province' => 'punjab',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Central Distribution Warehouse',
        'province' => 'punjab',
    ]);

    $this->manager = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Procurement Manager Tariq',
        'email' => 'tariq@metro.test',
        'role' => 'manager',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Shan Biryani Masala 50g',
        'sku' => 'SHAN-BM-50G',
        'barcode' => '896400030033',
        'hs_code' => '21039000',
        'cost_minor' => 8000, // Rs. 80
        'price_minor' => 12000, // Rs. 120
        'unit' => 'pcs',
        'tax_category' => 'general_goods',
        'is_active' => true,
    ]);

    // Carton conversion: 1 carton = 24 pcs
    ProductUomConversion::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'unit_name' => 'Carton (24 Boxes)',
        'unit_code' => 'carton',
        'conversion_factor' => 24.0,
        'price_minor' => 260000,
        'cost_minor' => 180000,
        'is_active' => true,
    ]);
});

test('tenant can register a supplier and track credit payment terms', function () {
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Shan Foods Distributors (Pvt) Ltd',
        'contact_person' => 'Rashid Mehmood',
        'phone' => '03009876543',
        'email' => 'orders@shanfoods.test',
        'ntn' => '1234567-9',
        'strn' => '12-34-5678-001-01',
        'city' => 'Karachi',
        'payment_terms_days' => 30,
        'current_balance_minor' => 0,
        'is_active' => true,
    ]);

    expect($supplier->id)->not->toBeNull()
        ->and($supplier->name)->toBe('Shan Foods Distributors (Pvt) Ltd')
        ->and($supplier->current_balance_minor)->toBe(0)
        ->and($supplier->payment_terms_days)->toBe(30);
});

test('manager can issue purchase order with multi-unit packaging conversion', function () {
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Unilever Pakistan Ltd',
        'phone' => '042-35112233',
        'payment_terms_days' => 15,
        'current_balance_minor' => 0,
    ]);

    $po = PurchaseOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'location_id' => $this->location->id,
        'user_id' => $this->manager->id,
        'po_number' => 'PO-20261123-0001',
        'status' => 'ordered',
        'expected_delivery_date' => now()->addDays(5)->toDateString(),
        'subtotal_minor' => 192000, // 10 cartons * Rs. 1920
        'tax_minor' => 0,
        'total_minor' => 192000,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'unit_code' => 'carton',
        'unit_conversion_factor' => 24.0,
        'ordered_quantity' => 10.0,
        'received_quantity' => 0.0,
        'cost_price_minor' => 192000 / 10, // Rs. 1,920 per carton
        'line_total_minor' => 192000,
    ]);

    expect($poItem->base_ordered_quantity)->toBe(240.0) // 10 cartons * 24 = 240 pcs
        ->and($po->items)->toHaveCount(1)
        ->and($po->status)->toBe('ordered');
});

test('goods received note (GRN) receipt restocks base inventory and creates vendor bill on khata', function () {
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'National Foods Limited',
        'phone' => '021-35001122',
        'payment_terms_days' => 30,
        'current_balance_minor' => 0,
    ]);

    $po = PurchaseOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'location_id' => $this->location->id,
        'user_id' => $this->manager->id,
        'po_number' => 'PO-20261123-0002',
        'status' => 'ordered',
        'subtotal_minor' => 96000,
        'tax_minor' => 0,
        'total_minor' => 96000,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'unit_code' => 'carton',
        'unit_conversion_factor' => 24.0,
        'ordered_quantity' => 5.0, // 5 cartons = 120 pcs
        'received_quantity' => 0.0,
        'cost_price_minor' => 19200, // Rs. 192 per carton
        'line_total_minor' => 96000,
    ]);

    // Initial stock is 0
    $stockBefore = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->value('quantity_on_hand') ?? 0;
    expect((float) $stockBefore)->toBe(0.0);

    // Process GRN for 5 cartons
    $po->receiveGoods([
        [
            'item_id' => $poItem->id,
            'quantity_received' => 5.0,
        ],
    ], $this->manager, 'First shipment received at central dock');

    // 1. Verify stock restocked: 5 cartons * 24 = 120 pcs
    $stockAfter = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->value('quantity_on_hand');
    expect((float) $stockAfter)->toBe(120.0);

    // 2. Verify stock movement recorded
    $movement = StockMovement::where('product_id', $this->product->id)
        ->where('movement_type', 'restock')
        ->latest()
        ->first();
    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(120.0);

    // 3. Verify PO status is received
    $po->refresh();
    expect($po->status)->toBe('received')
        ->and($po->received_at)->not->toBeNull();

    // 4. Verify vendor bill posted to supplier ledger
    $supplier->refresh();
    expect($supplier->current_balance_minor)->toBe(96000); // Store owes supplier Rs. 960

    $tx = SupplierTransaction::where('supplier_id', $supplier->id)->first();
    expect($tx)->not->toBeNull()
        ->and($tx->type)->toBe('bill_received')
        ->and($tx->amount_minor)->toBe(96000)
        ->and($tx->balance_after_minor)->toBe(96000);
});

test('paying supplier reduces accounts payable balance on khata', function () {
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Reckitt Benckiser PK',
        'phone' => '03211122334',
        'current_balance_minor' => 250000, // Rs. 2,500 payable
    ]);

    // Disburse partial payment of Rs. 1,500 via Bank Transfer
    $tx = $supplier->recordPayment(
        150000,
        'bank_transfer',
        $this->manager,
        'Partial payment for Dettol shipment',
        'HBL-TX-884920'
    );

    expect($tx->type)->toBe('payment_made')
        ->and($tx->amount_minor)->toBe(150000)
        ->and($tx->balance_after_minor)->toBe(100000)
        ->and($tx->payment_method)->toBe('bank_transfer')
        ->and($tx->reference_number)->toBe('HBL-TX-884920');

    $supplier->refresh();
    expect($supplier->current_balance_minor)->toBe(100000); // Remaining payable Rs. 1,000
});

test('supplier api endpoints list and return statement history', function () {
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Colgate-Palmolive Pakistan',
        'phone' => '03331234567',
        'current_balance_minor' => 50000,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->manager);

    // List suppliers
    $res = $this->getJson('/api/v1/suppliers?q=Colgate');
    $res->assertOk()
        ->assertJsonFragment(['name' => 'Colgate-Palmolive Pakistan']);

    // Show supplier
    $showRes = $this->getJson("/api/v1/suppliers/{$supplier->id}");
    $showRes->assertOk()
        ->assertJsonPath('supplier.id', $supplier->id);

    // Record payment via API
    $payRes = $this->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
        'amount_minor' => 50000,
        'payment_method' => 'raast',
        'notes' => 'Settled via Raast instant transfer',
    ]);
    $payRes->assertOk();

    $supplier->refresh();
    expect($supplier->current_balance_minor)->toBe(0);
});
