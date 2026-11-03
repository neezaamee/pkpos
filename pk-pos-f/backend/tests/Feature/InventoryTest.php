<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->ownerA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'owner',
    ]);

    $this->cashierA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'cashier',
    ]);

    $this->locationA = Location::factory()->create([
        'tenant_id' => $this->tenantA->id,
    ]);

    $this->locationB = Location::factory()->create([
        'tenant_id' => $this->tenantB->id,
    ]);

    $this->productA = Product::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Basmati Rice 5kg',
        'hs_code' => '10063010',
        'unit' => 'kg',
        'price_minor' => 185000,
        'tax_category' => 'general_goods',
        'is_active' => true,
    ]);

    $this->productA2 = Product::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Cooking Oil 1L',
        'hs_code' => '15121100',
        'unit' => 'litre',
        'price_minor' => 55000,
        'tax_category' => 'general_goods',
        'is_active' => true,
    ]);

    $this->productB = Product::create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Tenant B Item',
        'hs_code' => '09023000',
        'unit' => 'piece',
        'price_minor' => 42000,
        'tax_category' => 'general_goods',
        'is_active' => true,
    ]);

    $this->stockService = app(StockService::class);
});

test('stock service: recordMovement with negative delta decreases stock_levels', function () {
    // Initial opening stock of 50
    $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'opening',
        50
    );

    expect($this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id))->toBe(50);

    // Deduct 15 units
    $movement = $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'sale',
        -15
    );

    expect($movement->quantity_delta)->toBe(-15);
    expect($movement->movement_type)->toBe('sale');
    expect($this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id))->toBe(35);
});

test('stock service: recordMovement with negative delta beyond available throws InsufficientStockException when allow_negative_stock = false', function () {
    Config::set('inventory.allow_negative_stock', false);

    // Initial stock 10
    $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'restock',
        10
    );

    // Attempting adjustment of -15 (exceeding 10)
    expect(function () {
        $this->stockService->recordMovement(
            $this->tenantA,
            $this->productA->id,
            $this->locationA->id,
            'adjustment',
            -15
        );
    })->toThrow(InsufficientStockException::class);

    // Assert level remains untouched at 10
    expect($this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id))->toBe(10);
});

test('stock service: concurrent simulation — sequential calls with lockForUpdate compute correct final quantity', function () {
    // Start with 100 units
    $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'opening',
        100
    );

    // Simulation of sequential locked calls
    $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'sale',
        -30
    );

    $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'sale',
        -25
    );

    $finalStock = $this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id);
    expect($finalStock)->toBe(45); // 100 - 30 - 25 = 45
});

test('stock service: movement is appended to stock_movements even when stock goes negative (strict_sale_sync = false)', function () {
    Config::set('inventory.strict_sale_sync', false);
    Config::set('inventory.allow_negative_stock', false);

    // Stock starts at 5
    $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'opening',
        5
    );

    // Offline sale completes with 8 units -> stock becomes -3
    $movement = $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'sale',
        -8
    );

    expect($movement)->not->toBeNull();
    expect($movement->quantity_delta)->toBe(-8);

    $stock = $this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id);
    expect($stock)->toBe(-3);
});

test('sales sync: a sale with 2 items creates 2 stock_movements with negative deltas', function () {
    Sanctum::actingAs($this->ownerA);

    // Opening stock for both products
    $this->stockService->recordMovement($this->tenantA, $this->productA->id, $this->locationA->id, 'opening', 50);
    $this->stockService->recordMovement($this->tenantA, $this->productA2->id, $this->locationA->id, 'opening', 50);

    $saleId = (string) Str::uuid();

    $payload = [
        [
            'id' => $saleId,
            'tenant_id' => $this->tenantA->id,
            'location_id' => $this->locationA->id,
            'sale_number' => 'INV-SYNC-STOCK-01',
            'payment_method' => 'cash',
            'currency' => 'PKR',
            'subtotal_minor' => 240000,
            'tax_minor' => 43200,
            'total_minor' => 283200,
            'status' => 'completed',
            'items' => [
                [
                    'product_name' => 'Basmati Rice 5kg',
                    'hs_code' => '10063010',
                    'quantity' => 2,
                    'unit_price_minor' => 185000,
                    'line_total_minor' => 370000,
                ],
                [
                    'product_name' => 'Cooking Oil 1L',
                    'hs_code' => '15121100',
                    'quantity' => 3,
                    'unit_price_minor' => 55000,
                    'line_total_minor' => 165000,
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/sales/sync', $payload);
    $response->assertStatus(200)->assertJson(['synced' => 1]);

    // Check movements created for this sale
    $movements = StockMovement::where('reference_id', $saleId)->get();
    expect($movements)->toHaveCount(2);

    $deltas = $movements->pluck('quantity_delta')->all();
    expect($deltas)->toContain(-2);
    expect($deltas)->toContain(-3);

    // Verify stock levels decremented
    expect($this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id))->toBe(48);
    expect($this->stockService->getCurrentStock($this->tenantA, $this->productA2->id, $this->locationA->id))->toBe(47);
});

test('sales sync: idempotent replay does NOT create duplicate stock_movements', function () {
    Sanctum::actingAs($this->ownerA);

    $this->stockService->recordMovement($this->tenantA, $this->productA->id, $this->locationA->id, 'opening', 50);

    $saleId = (string) Str::uuid();

    $payload = [
        [
            'id' => $saleId,
            'tenant_id' => $this->tenantA->id,
            'location_id' => $this->locationA->id,
            'sale_number' => 'INV-IDEM-STOCK-02',
            'payment_method' => 'cash',
            'currency' => 'PKR',
            'subtotal_minor' => 185000,
            'tax_minor' => 33300,
            'total_minor' => 218300,
            'status' => 'completed',
            'items' => [
                [
                    'product_name' => 'Basmati Rice 5kg',
                    'hs_code' => '10063010',
                    'quantity' => 5,
                    'unit_price_minor' => 185000,
                    'line_total_minor' => 925000,
                ],
            ],
        ],
    ];

    // First request creates sale and deductions
    $res1 = $this->postJson('/api/v1/sales/sync', $payload);
    $res1->assertStatus(200)->assertJson(['synced' => 1]);

    expect(StockMovement::where('reference_id', $saleId)->count())->toBe(1);
    expect($this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id))->toBe(45);

    // Second request (idempotent replay)
    $res2 = $this->postJson('/api/v1/sales/sync', $payload);
    $res2->assertStatus(200)->assertJson(['synced' => 0]);

    // Assert movements count stays exactly 1, and stock remains 45
    expect(StockMovement::where('reference_id', $saleId)->count())->toBe(1);
    expect($this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id))->toBe(45);
});

test('adjust API: cashier role gets 403', function () {
    Sanctum::actingAs($this->cashierA);

    $response = $this->postJson('/api/v1/inventory/adjust', [
        'product_id' => $this->productA->id,
        'location_id' => $this->locationA->id,
        'new_quantity' => 120,
    ]);

    $response->assertStatus(403);
});

test('adjust API: new_quantity lower than current creates negative delta movement', function () {
    Sanctum::actingAs($this->ownerA);

    // Initial stock 50
    $this->stockService->recordMovement(
        $this->tenantA,
        $this->productA->id,
        $this->locationA->id,
        'opening',
        50
    );

    // Adjust down to 35
    $response = $this->postJson('/api/v1/inventory/adjust', [
        'product_id' => $this->productA->id,
        'location_id' => $this->locationA->id,
        'new_quantity' => 35,
        'note' => 'Physical count audit',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('movement.quantity_delta', -15)
        ->assertJsonPath('stock_level.quantity_on_hand', 35);

    expect($this->stockService->getCurrentStock($this->tenantA, $this->productA->id, $this->locationA->id))->toBe(35);
});

test('isolation: Tenant A cannot see Tenant B stock levels', function () {
    Sanctum::actingAs($this->ownerA);

    // Create stock level for Tenant B
    $this->stockService->recordMovement(
        $this->tenantB,
        $this->productB->id,
        $this->locationB->id,
        'opening',
        88
    );

    // Query levels as Tenant A
    $response = $this->getJson('/api/v1/inventory/levels');
    $response->assertStatus(200);

    $productIds = collect($response->json('data'))->pluck('product_id')->all();
    expect($productIds)->not->toContain($this->productB->id);
});
