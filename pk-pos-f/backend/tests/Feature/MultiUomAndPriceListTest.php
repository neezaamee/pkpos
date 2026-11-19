<?php

use App\Actions\CreateSaleAction;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductUomConversion;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
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
        'name' => 'Lahore Model Town Branch',
        'province' => 'punjab',
    ]);

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cashier Bilal',
        'email' => 'bilal@metro.test',
        'role' => 'cashier',
    ]);

    // Product sold by piece with carton conversion
    $this->biscuitProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Lu Prince Biscuits 50g',
        'sku' => 'PRINCE-50G',
        'barcode' => '896400010011',
        'unit' => 'pcs',
        'price_minor' => 10000, // Rs. 100 per piece
    ]);

    // Fractional product sold by weight
    $this->riceProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Super Kernel Basmati Rice (Loose)',
        'sku' => 'RICE-LOOSE',
        'barcode' => '896400020022',
        'unit' => 'kg',
        'price_minor' => 38000, // Rs. 380 per kg
    ]);
});

test('tenant can register custom Units of Measure with decimal flag', function () {
    $kg = UnitOfMeasure::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Kilogram',
        'code' => 'kg',
        'allows_decimals' => true,
    ]);

    $ctn = UnitOfMeasure::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Carton of 24',
        'code' => 'ctn',
        'allows_decimals' => false,
    ]);

    expect($kg->allows_decimals)->toBeTrue();
    expect($ctn->allows_decimals)->toBeFalse();
    $this->assertDatabaseHas('units_of_measure', ['tenant_id' => $this->tenant->id, 'code' => 'kg']);
});

test('product packaging conversion links pack price and barcode', function () {
    $conversion = ProductUomConversion::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->biscuitProduct->id,
        'unit_name' => 'Carton of 24',
        'unit_code' => 'ctn',
        'barcode' => '896400099999',
        'conversion_factor' => 24.000,
        'price_minor' => 220000, // Rs. 2,200 (discounted bulk price vs 24 x 100 = Rs. 2,400)
    ]);

    expect($conversion->conversion_factor)->toBe(24.0);
    expect($this->biscuitProduct->getPriceForUnit('ctn'))->toBe(220000);
    expect($this->biscuitProduct->getPriceForUnit('pcs'))->toBe(10000);
});

test('sale of fractional decimal quantity updates inventory with exact fractions', function () {
    // Initial stock: 100.000 kg
    StockLevel::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $this->riceProduct->id,
        'quantity_on_hand' => 100.000,
    ]);

    $action = app(CreateSaleAction::class);
    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'sale_number' => 'INV-20261122-0001',
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $this->riceProduct->id,
                'product_name' => $this->riceProduct->name,
                'hs_code' => '1006.3010',
                'unit_code' => 'kg',
                'unit_conversion_factor' => 1.0,
                'quantity' => 1.750, // 1.750 kg
                'unit_price_minor' => 38000,
                'tax_rate_basis_points' => 0,
                'tax_minor' => 0,
                'total_minor' => 66500, // Rs. 665.00
            ],
        ],
    ], $this->cashier->id);

    $item = $sale->items->first();
    expect($item->quantity)->toBe(1.750);
    expect($item->base_quantity)->toBe(1.750);

    // Stock level must be 100 - 1.75 = 98.250 kg
    $stock = StockLevel::where('tenant_id', $this->tenant->id)
        ->where('product_id', $this->riceProduct->id)
        ->where('location_id', $this->location->id)
        ->first();

    expect((float) $stock->quantity_on_hand)->toBe(98.25);

    // Stock movement record must match -1.750
    $movement = StockMovement::where('reference_id', $sale->id)->first();
    expect((float) $movement->quantity_delta)->toBe(-1.75);
});

test('sale of multi-pack conversion deducts base pieces according to conversion factor', function () {
    // Initial stock: 240 pieces (10 cartons)
    StockLevel::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $this->biscuitProduct->id,
        'quantity_on_hand' => 240.000,
    ]);

    $conversion = ProductUomConversion::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->biscuitProduct->id,
        'unit_name' => 'Carton of 24',
        'unit_code' => 'ctn',
        'barcode' => '896400099999',
        'conversion_factor' => 24.000,
        'price_minor' => 220000,
    ]);

    $action = app(CreateSaleAction::class);

    // Sell 2 Cartons (2 x 24 = 48 base pieces)
    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->cashier->id,
        'sale_number' => 'INV-20261122-0002',
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $this->biscuitProduct->id,
                'product_name' => 'Lu Prince Biscuits [Carton of 24]',
                'hs_code' => '1905.3100',
                'unit_code' => 'ctn',
                'unit_conversion_factor' => 24.000,
                'quantity' => 2,
                'unit_price_minor' => 220000,
                'tax_rate_basis_points' => 0,
                'tax_minor' => 0,
                'total_minor' => 440000, // Rs. 4,400
            ],
        ],
    ], $this->cashier->id);

    $item = $sale->items->first();
    expect($item->unit_code)->toBe('ctn');
    expect($item->unit_conversion_factor)->toBe(24.0);
    expect($item->base_quantity)->toBe(48.0);

    // Stock must have deducted 48 base pieces: 240 - 48 = 192 pieces
    $stock = StockLevel::where('tenant_id', $this->tenant->id)
        ->where('product_id', $this->biscuitProduct->id)
        ->where('location_id', $this->location->id)
        ->first();

    expect((float) $stock->quantity_on_hand)->toBe(192.0);
});

test('price lists dynamically resolve volume tiered pricing', function () {
    $wholesaleList = PriceList::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Wholesale Tier',
        'code' => 'wholesale',
        'is_default' => false,
    ]);

    // Base price is Rs. 100 (10,000 minor)
    // Tier 1: min 10 units => Rs. 90 (9,000 minor)
    PriceListItem::create([
        'tenant_id' => $this->tenant->id,
        'price_list_id' => $wholesaleList->id,
        'product_id' => $this->biscuitProduct->id,
        'min_quantity' => 10.000,
        'price_minor' => 9000,
    ]);

    // Tier 2: min 50 units => Rs. 85 (8,500 minor)
    PriceListItem::create([
        'tenant_id' => $this->tenant->id,
        'price_list_id' => $wholesaleList->id,
        'product_id' => $this->biscuitProduct->id,
        'min_quantity' => 50.000,
        'price_minor' => 8500,
    ]);

    // 1 unit = base retail price Rs. 100
    expect($wholesaleList->resolveProductPrice($this->biscuitProduct, 1))->toBe(10000);

    // 15 units = Tier 1 Rs. 90
    expect($wholesaleList->resolveProductPrice($this->biscuitProduct, 15))->toBe(9000);

    // 60 units = Tier 2 Rs. 85
    expect($wholesaleList->resolveProductPrice($this->biscuitProduct, 60))->toBe(8500);
});

test('customer profile can link to custom price list', function () {
    $dealerList = PriceList::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Authorized Dealer',
        'code' => 'dealer',
    ]);

    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Al-Hameed Traders',
        'phone' => '03001239988',
        'price_list_id' => $dealerList->id,
    ]);

    expect($customer->priceList->id)->toBe($dealerList->id);
    expect($customer->priceList->name)->toBe('Authorized Dealer');
});
