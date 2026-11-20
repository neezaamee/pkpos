<?php

namespace Tests\Feature;

use App\Actions\CreateSaleAction;
use App\Models\ContractorProject;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\Hardware\HardwareBuildingService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Bismillah Steel, Cement & Sanitary Traders Lahore',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Badami Bagh Steel & Cement Warehouse',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Munshi Aslam Hardware Operator',
        'email' => 'aslam@bismillahhardware.pk',
        'role' => 'manager',
    ]);

    $this->terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'HW-01',
        'name' => 'Weighbridge & Dispatch Counter 1',
        'device_id' => 'DEV-HW-01',
        'is_active' => true,
    ]);

    // Provision Chart of Accounts
    app(AccountingService::class)->provisionDefaultChartOfAccounts($this->tenant);
    $this->hardwareService = app(HardwareBuildingService::class);

    // Open active cash register session
    $this->registerSession = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'terminal_id' => $this->terminal->id,
        'opened_at' => now(),
        'opening_cash_minor' => 2000000,
        'status' => 'open',
    ]);
});

test('dimensional weight and unit conversions calculate correctly for hardware materials', function () {
    // 1. Standard 50 KG Cement Bag
    $cement = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Fauji Cement 50kg Bag',
        'unit' => 'bag',
        'dimensional_unit' => 'bag_50kg',
        'weight_kg_per_unit' => 50.0,
        'price_minor' => 140000, // Rs. 1400
    ]);

    $cementCalc = $this->hardwareService->calculateDimensionalDetails($cement, 600, 'bag_50kg');
    expect($cementCalc['gross_weight_kg'])->toBe(30000.0) // 600 bags * 50 kg = 30,000 kg
        ->and($cementCalc['weight_tons'])->toBe(30.0);

    // 2. Steel Rebar length feet to weight conversion (e.g. #4 bar = ~0.668 lbs/ft = 0.30 kg/ft)
    $rebar = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mughal Steel Grade 60 Rebar 1/2 inch',
        'unit' => 'feet',
        'dimensional_unit' => 'rebar_feet',
        'length_feet_per_piece' => 40.0,
        'weight_kg_per_unit' => 0.303,
        'price_minor' => 26000, // Rs. 260 per kg/length unit
    ]);

    $rebarCalc = $this->hardwareService->calculateDimensionalDetails($rebar, 1000, 'rebar_feet');
    expect($rebarCalc['gross_weight_kg'])->toBe(303.0); // 1,000 feet * 0.303 kg/ft = 303 KG

    // 3. Tile sq.ft to discrete boxes conversion (1 box = 16.5 sq.ft)
    $tiles = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Master Porcelain Floor Tiles 24x24',
        'unit' => 'sqft',
        'dimensional_unit' => 'tile_box',
        'sqft_per_box' => 16.5,
        'price_minor' => 22000, // Rs. 220 per sq.ft
    ]);

    $tileCalc = $this->hardwareService->calculateDimensionalDetails($tiles, 165, 'sqft');
    expect($tileCalc['boxes_count'])->toBe(10); // 165 sq.ft / 16.5 = 10 boxes
});

test('wholesale quantity tier pricing slabs resolve automatically by quantity', function () {
    $cement = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Bestway Cement 50kg Bag',
        'price_minor' => 140000, // Standard retail Rs. 1400
    ]);

    // Slab 1: 1 to 20 bags -> Standard Rs. 1400 (140000 minor)
    ProductPriceTier::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $cement->id,
        'min_quantity' => 1,
        'max_quantity' => 20,
        'unit_price_minor' => 140000,
        'tier_label' => 'Retail Slab (1-20 Bags)',
    ]);

    // Slab 2: 21 to 100 bags -> Wholesale Rs. 1350 (135000 minor)
    ProductPriceTier::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $cement->id,
        'min_quantity' => 21,
        'max_quantity' => 100,
        'unit_price_minor' => 135000,
        'tier_label' => 'Wholesale Slab (21-100 Bags)',
    ]);

    // Slab 3: 101+ bags -> Bulk Slab Rs. 1310 (131000 minor)
    ProductPriceTier::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $cement->id,
        'min_quantity' => 101,
        'max_quantity' => null,
        'unit_price_minor' => 131000,
        'tier_label' => 'Bulk Contractor Slab (101+ Bags)',
    ]);

    // Test tier price resolution for 10 bags (retail)
    expect($cement->getTieredPriceForQuantity(10))->toBe(140000);

    // Test tier price resolution for 50 bags (wholesale)
    expect($cement->getTieredPriceForQuantity(50))->toBe(135000);

    // Test tier price resolution for 600 bags (bulk contractor)
    expect($cement->getTieredPriceForQuantity(600))->toBe(131000);
});

test('contractor project sites track multi-site billing and generate dedicated project ledger', function () {
    $contractor = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Chaudhry Construction Co.',
        'phone' => '0300-8451122',
        'credit_limit_minor' => 500000000, // Rs. 5,000,000
        'current_balance_minor' => 0,
        'is_active' => true,
    ]);

    // Site 1: DHA Phase 6 Villa Plot 142
    $siteDha = ContractorProject::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $contractor->id,
        'name' => 'DHA Phase 6 Villa Plot 142',
        'plot_number' => 'Plot 142',
        'city' => 'Lahore',
        'supervisor_name' => 'Ustad Asghar Munshi',
        'supervisor_phone' => '0321-7788990',
        'status' => 'active',
    ]);

    // Site 2: Bahria Town Commercial Block C
    $siteBahria = ContractorProject::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $contractor->id,
        'name' => 'Bahria Town Commercial Block C',
        'plot_number' => 'Plot 88',
        'city' => 'Lahore',
        'supervisor_name' => 'Akram Mistri',
        'supervisor_phone' => '0333-4455667',
        'status' => 'active',
    ]);

    $cement = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Maple Leaf Cement 50kg',
        'price_minor' => 135000,
    ]);

    // Bill 200 bags of cement to Site 1 (DHA) on Credit
    $action = app(CreateSaleAction::class);
    $saleDha = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'terminal_id' => $this->terminal->id,
        'session_id' => $this->registerSession->id,
        'customer_id' => $contractor->id,
        'contractor_project_id' => $siteDha->id,
        'payment_method' => 'credit',
        'subtotal_minor' => 27000000, // 200 bags * Rs. 1350 = Rs. 270,000
        'tax_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 27000000,
        'status' => 'completed',
        'items' => [
            [
                'product_id' => $cement->id,
                'quantity' => 200,
                'unit_price_minor' => 135000,
                'subtotal_minor' => 27000000,
                'total_minor' => 27000000,
                'tax_rate_basis_points' => 0,
                'tax_minor' => 0,
                'contractor_project_id' => $siteDha->id,
            ],
        ],
    ]);

    // Bill 100 bags of cement to Site 2 (Bahria) on Credit
    $saleBahria = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'terminal_id' => $this->terminal->id,
        'session_id' => $this->registerSession->id,
        'customer_id' => $contractor->id,
        'contractor_project_id' => $siteBahria->id,
        'payment_method' => 'credit',
        'subtotal_minor' => 13500000, // 100 bags * Rs. 1350 = Rs. 135,000
        'tax_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 13500000,
        'status' => 'completed',
        'items' => [
            [
                'product_id' => $cement->id,
                'quantity' => 100,
                'unit_price_minor' => 135000,
                'subtotal_minor' => 13500000,
                'total_minor' => 13500000,
                'tax_rate_basis_points' => 0,
                'tax_minor' => 0,
                'contractor_project_id' => $siteBahria->id,
            ],
        ],
    ]);

    // Verify Site 1 Ledger
    $ledgerDha = $this->hardwareService->getProjectSiteLedger($siteDha);
    expect($ledgerDha['total_billed_minor'])->toBe(27000000)
        ->and($ledgerDha['total_receivable_minor'])->toBe(27000000)
        ->and($ledgerDha['sales_count'])->toBe(1);

    // Verify Site 2 Ledger
    $ledgerBahria = $this->hardwareService->getProjectSiteLedger($siteBahria);
    expect($ledgerBahria['total_billed_minor'])->toBe(13500000)
        ->and($ledgerBahria['total_receivable_minor'])->toBe(13500000)
        ->and($ledgerBahria['sales_count'])->toBe(1);
});

test('truck delivery challan and gate pass are generated with weight aggregation and dispatch workflow', function () {
    $contractor = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Habib Rafiq Builders',
        'phone' => '0322-9988776',
        'credit_limit_minor' => 100000000,
        'is_active' => true,
    ]);

    $project = ContractorProject::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $contractor->id,
        'name' => 'Model Town Villa 56',
        'site_address' => '56-C, Model Town, Lahore',
        'supervisor_name' => 'Ustad Tariq',
        'supervisor_phone' => '0311-2233445',
        'status' => 'active',
    ]);

    $cement = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Fauji Cement 50kg',
        'price_minor' => 135000,
        'weight_kg_per_unit' => 50.0,
    ]);

    // Create Sale with auto-delivery challan
    $action = app(CreateSaleAction::class);
    $sale = $action->execute([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'terminal_id' => $this->terminal->id,
        'session_id' => $this->registerSession->id,
        'customer_id' => $contractor->id,
        'contractor_project_id' => $project->id,
        'create_delivery_challan' => true,
        'delivery_challan' => [
            'vehicle_number' => 'LES-8921',
            'driver_name' => 'Gul Sher Khan',
            'driver_phone' => '0301-4455667',
            'transporter_name' => 'Khyber Goods Forwarding',
            'freight_terms' => 'to_pay_customer',
            'freight_amount_minor' => 500000, // Rs. 5,000 freight
            'site_destination' => '56-C, Model Town, Lahore',
            'notes' => 'Call Ustad Tariq before unloading',
        ],
        'payment_method' => 'credit',
        'subtotal_minor' => 13500000,
        'tax_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 13500000,
        'status' => 'completed',
        'items' => [
            [
                'product_id' => $cement->id,
                'quantity' => 100, // 100 bags * 50 kg = 5,000 KG
                'unit_price_minor' => 135000,
                'subtotal_minor' => 13500000,
                'total_minor' => 13500000,
                'tax_rate_basis_points' => 0,
                'tax_minor' => 0,
                'dimensional_unit' => 'bag_50kg',
            ],
        ],
    ]);

    $challan = DeliveryChallan::where('sale_id', $sale->id)->first();
    expect($challan)->not->toBeNull()
        ->and($challan->vehicle_number)->toBe('LES-8921')
        ->and($challan->driver_name)->toBe('Gul Sher Khan')
        ->and($challan->freight_terms)->toBe('to_pay_customer')
        ->and($challan->status)->toBe('dispatched');

    // Assert Challan and Gate Pass numbers follow sequential format
    expect($challan->challan_number)->toStartWith('CHL-')
        ->and($challan->gate_pass_number)->toStartWith('GP-');

    // Assert challan items gross weight
    $item = $challan->items()->first();
    expect($item)->not->toBeNull()
        ->and($item->quantity_dispatched)->toEqual(100)
        ->and((float) $item->weight_kg)->toBe(5000.0); // 100 bags * 50 kg

    // Dispatch -> Delivered workflow
    $this->hardwareService->updateChallanStatus($challan, 'delivered');
    expect($challan->fresh()->status)->toBe('delivered')
        ->and($challan->fresh()->delivered_at)->not->toBeNull();
});

test('hardware POS API endpoints support contractor project sites, price tiers, and delivery challans', function () {
    $contractor = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siddique & Sons Contractors',
        'phone' => '0300-1122334',
        'credit_limit_minor' => 200000000,
        'is_active' => true,
    ]);

    // 1. Create project via API
    $createResponse = $this->actingAs($this->user)
        ->postJson("/api/v1/pos/hardware/contractors/{$contractor->id}/projects", [
            'name' => 'Askari 10 Sector B Villa 12',
            'plot_number' => 'Villa 12',
            'city' => 'Lahore',
            'supervisor_name' => 'Munshi Rafeeq',
            'supervisor_phone' => '0321-9988776',
        ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('project.name', 'Askari 10 Sector B Villa 12');

    $projectId = $createResponse->json('project.id');

    // 2. Fetch projects for contractor
    $listResponse = $this->actingAs($this->user)
        ->getJson("/api/v1/pos/hardware/contractors/{$contractor->id}/projects");

    $listResponse->assertStatus(200)
        ->assertJsonCount(1, 'projects');

    // 3. Fetch project ledger
    $ledgerResponse = $this->actingAs($this->user)
        ->getJson("/api/v1/pos/hardware/projects/{$projectId}/ledger");

    $ledgerResponse->assertStatus(200)
        ->assertJsonPath('ledger.project.id', $projectId)
        ->assertJsonPath('ledger.total_billed_minor', 0);
});

test('hardware backoffice web routes render correctly', function () {
    $contractor = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Premier Builders Pvt Ltd',
        'phone' => '0300-5566778',
        'is_active' => true,
    ]);

    $project = ContractorProject::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $contractor->id,
        'name' => 'Lake City Sector M Villa 44',
        'status' => 'active',
    ]);

    $cement = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'DG Khan Cement 50kg',
        'price_minor' => 135000,
    ]);

    $tier = ProductPriceTier::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $cement->id,
        'min_quantity' => 50,
        'max_quantity' => 200,
        'unit_price_minor' => 132000,
        'tier_label' => '50-200 Bags Slab',
    ]);

    $challan = DeliveryChallan::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'contractor_project_id' => $project->id,
        'challan_number' => 'CHL-TEST-001',
        'gate_pass_number' => 'GP-TEST-001',
        'vehicle_number' => 'LXO-5511',
        'driver_name' => 'Bashir Ahmed',
        'status' => 'dispatched',
    ]);

    // Test Projects Index
    $this->actingAs($this->user)
        ->get('/hardware/projects')
        ->assertStatus(200);

    // Test Project Show / Site Ledger
    $this->actingAs($this->user)
        ->get("/hardware/projects/{$project->id}")
        ->assertStatus(200);

    // Test Wholesale Price Slabs Index
    $this->actingAs($this->user)
        ->get('/hardware/tiers')
        ->assertStatus(200);

    // Test Challans Index
    $this->actingAs($this->user)
        ->get('/hardware/challans')
        ->assertStatus(200);

    // Test Challan Printable Show (Biltee)
    $this->actingAs($this->user)
        ->get("/hardware/challans/{$challan->id}")
        ->assertStatus(200);
});
