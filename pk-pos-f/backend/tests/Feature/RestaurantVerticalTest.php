<?php

namespace Tests\Feature;

use App\Actions\CreateSaleAction;
use App\Models\KitchenOrder;
use App\Models\KitchenStation;
use App\Models\Location;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\RegisterSession;
use App\Models\RestaurantFloor;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\Restaurant\KitchenOrderService;
use Carbon\Carbon;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Salt\'n Pepper Village BBQ & Restaurant Lahore',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Gulberg III Main Restaurant',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Captain Tariq POS Operator',
        'email' => 'tariq@saltnpepper.pk',
        'role' => 'manager',
    ]);

    $this->terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'REST-01',
        'name' => 'Main Dine-In Billing Counter',
        'device_id' => 'DEV-REST-01',
        'is_active' => true,
    ]);

    // Provision Chart of Accounts
    app(AccountingService::class)->provisionDefaultChartOfAccounts($this->tenant);
    $this->kitchenService = app(KitchenOrderService::class);

    // Open active cash register session
    $this->registerSession = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'terminal_id' => $this->terminal->id,
        'opened_at' => now(),
        'opening_cash_minor' => 1500000,
        'status' => 'open',
    ]);
});

test('floor and table management allows occupancy, billing, and release lifecycle', function () {
    $floor = RestaurantFloor::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'name' => 'Family Hall 1st Floor',
        'floor_number' => 1,
    ]);

    $table = RestaurantTable::create([
        'tenant_id' => $this->tenant->id,
        'floor_id' => $floor->id,
        'table_number' => 'T-101',
        'seating_capacity' => 6,
        'status' => 'available',
        'shape' => 'round',
    ]);

    expect($table->status)->toBe('available');

    // Create a dummy sale to occupy
    $sale = Sale::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'sale_number' => 'INV-REST-001',
        'subtotal_minor' => 450000,
        'tax_minor' => 72000,
        'total_minor' => 522000,
        'payment_method' => 'cash',
        'status' => 'completed',
        'order_type' => 'dine_in',
        'restaurant_table_id' => $table->id,
        'table_number' => 'T-101',
        'covers' => 4,
    ]);

    // Occupy table
    $table->occupy($sale);
    expect($table->fresh()->status)->toBe('occupied')
        ->and($table->fresh()->current_sale_id)->toBe($sale->id);

    // Mark as billed
    $table->bill();
    expect($table->fresh()->status)->toBe('billed');

    // Release table
    $table->release();
    expect($table->fresh()->status)->toBe('available')
        ->and($table->fresh()->current_sale_id)->toBeNull();
});

test('dine-in orders calculate service charge and auto-release table upon settlement', function () {
    $floor = RestaurantFloor::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'name' => 'Ground Floor Courtyard',
        'floor_number' => 0,
    ]);

    $table = RestaurantTable::create([
        'tenant_id' => $this->tenant->id,
        'floor_id' => $floor->id,
        'table_number' => 'T-05',
        'seating_capacity' => 4,
        'status' => 'available',
    ]);

    $karahi = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Desi Murgh Shinwari Karahi 1KG',
        'price_minor' => 280000, // Rs 2,800
    ]);

    $naan = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Roghni Naan with Kalonji',
        'price_minor' => 12000, // Rs 120
    ]);

    // Subtotal: 2,800 + (4 * 120) = Rs 3,280 = 328,000 minor
    // 5% Service Charge: 5% of 328,000 = 16,400 minor
    // Dine-In sale settlement
    $sale = app(CreateSaleAction::class)->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'payment_method' => 'card',
        'order_type' => 'dine_in',
        'restaurant_table_id' => $table->id,
        'table_number' => 'T-05',
        'covers' => 4,
        'server_name' => 'Capt. Tariq',
        'service_charge_basis_points' => 500, // 5%
        'status' => 'completed',
        'items' => [
            [
                'product_id' => $karahi->id,
                'product_name' => $karahi->name,
                'quantity' => 1,
                'unit_price_minor' => 280000,
                'tax_rate_basis_points' => 1600,
                'tax_amount_minor' => (int) round(280000 * 0.16),
                'line_total_minor' => 280000 + (int) round(280000 * 0.16),
                'cooking_notes' => 'Less black pepper, medium gravy',
            ],
            [
                'product_id' => $naan->id,
                'product_name' => $naan->name,
                'quantity' => 4,
                'unit_price_minor' => 12000,
                'tax_rate_basis_points' => 1600,
                'tax_amount_minor' => (int) round(48000 * 0.16),
                'line_total_minor' => 48000 + (int) round(48000 * 0.16),
                'cooking_notes' => 'Crispy and hot',
            ],
        ],
    ], $this->user->id);

    expect($sale->order_type)->toBe('dine_in')
        ->and($sale->covers)->toBe(4)
        ->and($sale->server_name)->toBe('Capt. Tariq')
        ->and($sale->service_charge_basis_points)->toBe(500)
        ->and($sale->service_charge_minor)->toBe(16400); // 5% of 328,000

    // Table should be released automatically since status is 'completed'
    expect($table->fresh()->status)->toBe('available')
        ->and($table->fresh()->current_sale_id)->toBeNull();
});

test('kitchen orders route items to designated stations with modifiers and cooking notes', function () {
    // 1. Setup Kitchen Stations
    $bbqStation = KitchenStation::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'name' => 'BBQ & Charcoal Grill',
        'code' => 'GRILL',
    ]);

    $tandoorStation = KitchenStation::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'name' => 'Tandoor & Breads',
        'code' => 'TANDOOR',
    ]);

    $mainKitchen = KitchenStation::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'name' => 'Main Karahi & Gravy Section',
        'code' => 'MAIN',
    ]);

    // 2. Setup Products attached to Stations
    $tikka = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Chicken Tikka Boti Platter',
        'price_minor' => 95000,
        'kitchen_station_id' => $bbqStation->id,
        'is_kitchen_item' => true,
    ]);

    $roti = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Garlic Butter Naan',
        'price_minor' => 15000,
        'kitchen_station_id' => $tandoorStation->id,
        'is_kitchen_item' => true,
    ]);

    $handi = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Boneless Chicken Makhni Handi',
        'price_minor' => 185000,
        'kitchen_station_id' => $mainKitchen->id,
        'is_kitchen_item' => true,
    ]);

    // 3. Modifier Group for Tikka
    $spiceGroup = ModifierGroup::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Spice Level',
        'min_selections' => 1,
        'max_selections' => 1,
        'is_required' => true,
    ]);
    $mild = ModifierOption::create([
        'tenant_id' => $this->tenant->id,
        'modifier_group_id' => $spiceGroup->id,
        'name' => 'Mild & Creamy',
        'price_surcharge_minor' => 0,
    ]);
    $extraSpicy = ModifierOption::create([
        'tenant_id' => $this->tenant->id,
        'modifier_group_id' => $spiceGroup->id,
        'name' => 'Extra Pakistani Spicy (Green Chillies)',
        'price_surcharge_minor' => 5000, // +Rs 50
    ]);
    $tikka->modifierGroups()->attach($spiceGroup->id);

    // 4. Create Sale with Fire KOT
    $sale = app(CreateSaleAction::class)->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'payment_method' => 'cash',
        'order_type' => 'dine_in',
        'table_number' => 'VIP-02',
        'covers' => 6,
        'server_name' => 'Waiter Asif',
        'fire_kot' => true,
        'items' => [
            [
                'product_id' => $tikka->id,
                'product_name' => $tikka->name,
                'quantity' => 2,
                'unit_price_minor' => 100000,
                'tax_rate_basis_points' => 1600,
                'tax_amount_minor' => 32000,
                'line_total_minor' => 232000,
                'kitchen_station_id' => $bbqStation->id,
                'cooking_notes' => 'Well-done char marks on skewers',
                'selected_modifiers' => [
                    [
                        'modifier_group_id' => $spiceGroup->id,
                        'modifier_option_id' => $extraSpicy->id,
                        'group_name' => 'Spice Level',
                        'option_name' => 'Extra Pakistani Spicy (Green Chillies)',
                        'price_surcharge_minor' => 5000,
                    ],
                ],
            ],
            [
                'product_id' => $roti->id,
                'product_name' => $roti->name,
                'quantity' => 6,
                'unit_price_minor' => 15000,
                'tax_rate_basis_points' => 1600,
                'tax_amount_minor' => 14400,
                'line_total_minor' => 104400,
                'kitchen_station_id' => $tandoorStation->id,
                'cooking_notes' => 'Serve 3 first, 3 after 10 mins',
            ],
            [
                'product_id' => $handi->id,
                'product_name' => $handi->name,
                'quantity' => 1,
                'unit_price_minor' => 185000,
                'tax_rate_basis_points' => 1600,
                'tax_amount_minor' => 29600,
                'line_total_minor' => 214600,
                'kitchen_station_id' => $mainKitchen->id,
                'cooking_notes' => 'Extra ginger juliennes garnish',
            ],
        ],
    ], $this->user->id);

    // Verify 3 distinct KOT tickets generated for 3 kitchen stations
    $kots = KitchenOrder::where('sale_id', $sale->id)->get();
    expect($kots->count())->toBe(3);

    $bbqKot = $kots->firstWhere('kitchen_station_id', $bbqStation->id);
    expect($bbqKot)->not->toBeNull()
        ->and($bbqKot->table_number)->toBe('VIP-02')
        ->and($bbqKot->server_name)->toBe('Waiter Asif')
        ->and($bbqKot->status)->toBe('pending')
        ->and($bbqKot->items->count())->toBe(1);

    $bbqItem = $bbqKot->items->first();
    expect($bbqItem->product_name)->toBe($tikka->name)
        ->and($bbqItem->cooking_notes)->toBe('Well-done char marks on skewers')
        ->and($bbqItem->selected_modifiers)->toHaveCount(1)
        ->and($bbqItem->selected_modifiers[0]['option_name'])->toBe('Extra Pakistani Spicy (Green Chillies)');

    $tandoorKot = $kots->firstWhere('kitchen_station_id', $tandoorStation->id);
    expect($tandoorKot)->not->toBeNull()
        ->and($tandoorKot->items->first()->quantity)->toEqual(6);

    $mainKot = $kots->firstWhere('kitchen_station_id', $mainKitchen->id);
    expect($mainKot)->not->toBeNull()
        ->and($mainKot->items->first()->product_name)->toBe($handi->name);
});

test('kds bump bar transitions kitchen order status from pending to served with urgency indicators', function () {
    $station = KitchenStation::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'name' => 'Karahi Station',
        'code' => 'KARAHI',
    ]);

    $kot = KitchenOrder::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'kitchen_station_id' => $station->id,
        'kot_number' => 'KOT-261128-099',
        'order_type' => 'dine_in',
        'table_number' => 'T-08',
        'server_name' => 'Capt. Farhan',
        'status' => 'pending',
        'fired_at' => Carbon::now()->subMinutes(12), // 12 minutes ago (warning urgency)
    ]);

    $kot->items()->create([
        'product_name' => 'Mutton Shinwari Dumba Karahi',
        'quantity' => 1,
        'status' => 'pending',
    ]);

    // Check Live KDS Orders query & urgency calculation
    $kdsOrders = $this->kitchenService->getLiveKdsOrders($this->tenant, $station->id, $this->location->id);
    expect($kdsOrders->count())->toBe(1);

    $first = $kdsOrders->first();
    expect($first['id'])->toBe($kot->id)
        ->and($first['urgency'])->toBe('warning'); // >= 10 and < 20 min = warning

    // Bump 1: pending -> preparing
    $bump1 = $this->kitchenService->bumpKot($kot);
    expect($bump1->status)->toBe('preparing');

    // Bump 2: preparing -> ready
    $bump2 = $this->kitchenService->bumpKot($bump1);
    expect($bump2->status)->toBe('ready')
        ->and($bump2->ready_at)->not->toBeNull();

    // Bump 3: ready -> served
    $bump3 = $this->kitchenService->bumpKot($bump2);
    expect($bump3->status)->toBe('served')
        ->and($bump3->served_at)->not->toBeNull();

    // Check KDS orders feed now filters out 'served' orders
    $kdsOrdersAfterServed = $this->kitchenService->getLiveKdsOrders($this->tenant, $station->id, $this->location->id);
    expect($kdsOrdersAfterServed->count())->toBe(0);
});

test('restaurant api endpoints provide floors, tables, modifiers, and bump bar actions', function () {
    $floor = RestaurantFloor::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'name' => 'Rooftop Open Air Terrace',
        'floor_number' => 2,
    ]);

    $table = RestaurantTable::create([
        'tenant_id' => $this->tenant->id,
        'floor_id' => $floor->id,
        'table_number' => 'RT-01',
        'seating_capacity' => 8,
        'status' => 'available',
    ]);

    $modGroup = ModifierGroup::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Toppings & Cheese',
        'min_selections' => 0,
        'max_selections' => 3,
    ]);
    ModifierOption::create([
        'tenant_id' => $this->tenant->id,
        'modifier_group_id' => $modGroup->id,
        'name' => 'Extra Mozzarella Cheese',
        'price_surcharge_minor' => 15000,
    ]);

    $station = KitchenStation::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'name' => 'Pizza & Oven Station',
        'code' => 'OVEN',
    ]);

    $kot = KitchenOrder::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'kitchen_station_id' => $station->id,
        'kot_number' => 'KOT-261128-050',
        'order_type' => 'dine_in',
        'table_number' => 'RT-01',
        'status' => 'pending',
        'fired_at' => now(),
    ]);

    // 1. GET /api/v1/pos/restaurant/floors-tables
    $resFloors = $this->actingAs($this->user)->getJson('/api/v1/pos/restaurant/floors-tables');
    $resFloors->assertOk()
        ->assertJsonStructure(['floors', 'unassigned_tables']);
    expect(count($resFloors->json('floors')))->toBeGreaterThanOrEqual(1);

    // 2. GET /api/v1/pos/restaurant/modifiers
    $resMods = $this->actingAs($this->user)->getJson('/api/v1/pos/restaurant/modifiers');
    $resMods->assertOk()
        ->assertJsonStructure(['data']);
    expect(count($resMods->json('data')))->toBeGreaterThanOrEqual(1);

    // 3. GET /api/v1/pos/restaurant/kds/orders
    $resKds = $this->actingAs($this->user)->getJson('/api/v1/pos/restaurant/kds/orders');
    $resKds->assertOk()
        ->assertJsonStructure(['orders']);
    expect(count($resKds->json('orders')))->toBeGreaterThanOrEqual(1);

    // 4. POST /api/v1/pos/restaurant/kds/orders/{id}/bump
    $resBump = $this->actingAs($this->user)->postJson("/api/v1/pos/restaurant/kds/orders/{$kot->id}/bump");
    $resBump->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'preparing',
        ]);
    expect($kot->fresh()->status)->toBe('preparing');

    // 5. POST /api/v1/pos/restaurant/tables/{id}/occupy & release
    $resOccupy = $this->actingAs($this->user)->postJson("/api/v1/pos/restaurant/tables/{$table->id}/occupy");
    $resOccupy->assertOk()->assertJson(['status' => 'occupied']);

    $resRelease = $this->actingAs($this->user)->postJson("/api/v1/pos/restaurant/tables/{$table->id}/release");
    $resRelease->assertOk()->assertJson(['status' => 'available']);
});

test('backoffice web controllers render inertia views for restaurant administration', function () {
    // 1. Floors & Tables Index
    $resTables = $this->actingAs($this->user)->get('/restaurant/tables');
    $resTables->assertOk();

    // 2. Kitchen Stations Index
    $resStations = $this->actingAs($this->user)->get('/restaurant/stations');
    $resStations->assertOk();

    // 3. Modifiers Index
    $resMods = $this->actingAs($this->user)->get('/restaurant/modifiers');
    $resMods->assertOk();

    // 4. Kitchen Display System (KDS) Screen
    $resKds = $this->actingAs($this->user)->get('/restaurant/kds');
    $resKds->assertOk();
});
