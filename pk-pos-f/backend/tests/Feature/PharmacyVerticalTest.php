<?php

namespace Tests\Feature;

use App\Actions\CreateSaleAction;
use App\Models\Customer;
use App\Models\Location;
use App\Models\NarcoticRegisterEntry;
use App\Models\PosTerminal;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUomConversion;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\Pharmacy\BatchService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Fazal Din & Sons Pharmacy Lahore',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'The Mall Branch Pharmacy',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Pharmacist Bilal',
        'email' => 'bilal@fazaldin.pk',
        'role' => 'manager',
    ]);

    $this->terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'RX-01',
        'name' => 'Prescription Counter Till 1',
        'device_id' => 'DEV-RX-01',
        'is_active' => true,
    ]);

    // Provision Chart of Accounts
    app(AccountingService::class)->provisionDefaultChartOfAccounts($this->tenant);
    $this->batchService = app(BatchService::class);

    // Open Cash Register Session
    $this->registerSession = RegisterSession::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'terminal_id' => $this->terminal->id,
        'opened_at' => now(),
        'opening_cash_minor' => 100000,
        'status' => 'open',
    ]);
});

test('fefo batch allocation selects the earliest expiring valid batch', function () {
    $medicine = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Panadol 500mg Tablets',
        'generic_name' => 'Paracetamol',
        'drap_registration_number' => '001234',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'unit' => 'tab',
        'price_minor' => 350, // Rs. 3.5 per tablet
        'cost_minor' => 200,
        'hs_code' => '30049099',
        'tax_category' => 'pharmaceuticals',
        'is_active' => true,
    ]);

    // Batch 1: Expiring in 90 days (Later)
    $batchLater = ProductBatch::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $medicine->id,
        'batch_number' => 'PAN-BATCH-LATER',
        'manufacturing_date' => now()->subMonths(3)->toDateString(),
        'expiry_date' => now()->addDays(90)->toDateString(),
        'quantity_on_hand' => 100,
        'cost_minor' => 200,
        'mrp_minor' => 350,
        'status' => 'active',
    ]);

    // Batch 2: Expiring in 20 days (Earliest - should be picked first by FEFO)
    $batchEarliest = ProductBatch::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $medicine->id,
        'batch_number' => 'PAN-BATCH-SOON',
        'manufacturing_date' => now()->subMonths(6)->toDateString(),
        'expiry_date' => now()->addDays(20)->toDateString(),
        'quantity_on_hand' => 50,
        'cost_minor' => 200,
        'mrp_minor' => 350,
        'status' => 'active',
    ]);

    // Check FEFO query
    $fefo = $medicine->fefoBatch($this->location->id);
    expect($fefo)->not->toBeNull();
    expect($fefo->id)->toBe($batchEarliest->id);
    expect($fefo->batch_number)->toBe('PAN-BATCH-SOON');

    // Perform sale without explicit batch ID -> should automatically pick FEFO batch
    $sale = app(CreateSaleAction::class)->execute([
        'location_id' => $this->location->id,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $medicine->id,
                'quantity' => 10,
                'unit_price_minor' => 350,
                'tax_rate_basis_points' => 0,
            ],
        ],
    ], $this->user);

    $item = $sale->items->first();
    expect($item->batch_id)->toBe($batchEarliest->id);
    expect($item->batch_number)->toBe('PAN-BATCH-SOON');

    // Batch quantity should be reduced from 50 to 40
    $batchEarliest->refresh();
    expect((float) $batchEarliest->quantity_on_hand)->toBe(40.0);
});

test('pos rejects sale of expired batch with validation exception (Drug Act compliance)', function () {
    $medicine = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Augmentin 625mg Tablets',
        'generic_name' => 'Amoxicillin + Clavulanic Acid',
        'drap_registration_number' => '004567',
        'dosage_form' => 'tablet',
        'strength' => '625mg',
        'unit' => 'tab',
        'price_minor' => 2500, // Rs. 25 per tablet
        'cost_minor' => 1800,
        'hs_code' => '30049099',
        'tax_category' => 'pharmaceuticals',
        'is_active' => true,
    ]);

    // Expired Batch
    $expiredBatch = ProductBatch::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $medicine->id,
        'batch_number' => 'AUG-EXPIRED-99',
        'manufacturing_date' => now()->subYears(2)->toDateString(),
        'expiry_date' => now()->subDays(5)->toDateString(), // 5 days expired
        'quantity_on_hand' => 30,
        'cost_minor' => 1800,
        'mrp_minor' => 2500,
        'status' => 'active',
    ]);

    expect($expiredBatch->isExpired())->toBeTrue();

    // Selling an expired batch must throw ValidationException
    expect(function () use ($medicine, $expiredBatch) {
        app(CreateSaleAction::class)->execute([
            'location_id' => $this->location->id,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $medicine->id,
                    'batch_id' => $expiredBatch->id,
                    'quantity' => 5,
                    'unit_price_minor' => 2500,
                    'tax_rate_basis_points' => 0,
                ],
            ],
        ], $this->user);
    })->toThrow(ValidationException::class);
});

test('multi-unit conversion correctly deducts base units from batch inventory (Strip to Tablet)', function () {
    $medicine = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Disprin 300mg Soluble Tablets',
        'generic_name' => 'Aspirin',
        'drap_registration_number' => '009876',
        'dosage_form' => 'tablet',
        'unit' => 'tab',
        'price_minor' => 500, // Rs. 5 per tab
        'cost_minor' => 300,
        'hs_code' => '30049099',
        'tax_category' => 'pharmaceuticals',
        'is_active' => true,
    ]);

    // Pack conversion: 1 strip = 10 tablets
    ProductUomConversion::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $medicine->id,
        'unit_name' => 'Strip of 10 Tablets',
        'unit_code' => 'strip',
        'conversion_factor' => 10.0,
        'price_minor' => 5000, // Rs. 50 per strip
        'is_active' => true,
    ]);

    $batch = ProductBatch::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $medicine->id,
        'batch_number' => 'DIS-BATCH-01',
        'expiry_date' => now()->addYear()->toDateString(),
        'quantity_on_hand' => 100, // 100 base tablets
        'cost_minor' => 300,
        'mrp_minor' => 500,
        'status' => 'active',
    ]);

    // Dispense 2 strips (each strip = 10 tablets -> total 20 tablets deducted)
    $sale = app(CreateSaleAction::class)->execute([
        'location_id' => $this->location->id,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $medicine->id,
                'batch_id' => $batch->id,
                'unit_code' => 'strip',
                'unit_conversion_factor' => 10.0,
                'quantity' => 2, // 2 strips
                'unit_price_minor' => 5000,
                'tax_rate_basis_points' => 0,
            ],
        ],
    ], $this->user);

    expect($sale)->not->toBeNull();
    $batch->refresh();
    // 100 - 20 = 80 tablets remaining
    expect((float) $batch->quantity_on_hand)->toBe(80.0);
});

test('dispensing schedule d narcotic without doctor pmdc is blocked and with pmdc logs statutory register', function () {
    $narcotic = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Xanax 0.5mg Tablets (Alprazolam)',
        'generic_name' => 'Alprazolam',
        'drap_registration_number' => '007788',
        'dosage_form' => 'tablet',
        'strength' => '0.5mg',
        'unit' => 'tab',
        'price_minor' => 1200,
        'cost_minor' => 800,
        'hs_code' => '30049099',
        'tax_category' => 'pharmaceuticals',
        'is_prescription_required' => true,
        'is_narcotic' => true, // Schedule D Controlled Substance!
        'is_active' => true,
    ]);

    $batch = ProductBatch::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $narcotic->id,
        'batch_number' => 'XNX-SEC-09',
        'expiry_date' => now()->addMonths(18)->toDateString(),
        'quantity_on_hand' => 60,
        'status' => 'active',
    ]);

    // 1. Attempting to sell narcotic WITHOUT doctor PMDC must fail
    expect(function () use ($narcotic, $batch) {
        app(CreateSaleAction::class)->execute([
            'location_id' => $this->location->id,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $narcotic->id,
                    'batch_id' => $batch->id,
                    'quantity' => 10,
                    'unit_price_minor' => 1200,
                    'tax_rate_basis_points' => 0,
                ],
            ],
        ], $this->user);
    })->toThrow(ValidationException::class);

    // 2. Selling narcotic WITH verified doctor PMDC & patient details succeeds and logs to Form 7 register
    $sale = app(CreateSaleAction::class)->execute([
        'location_id' => $this->location->id,
        'payment_method' => 'cash',
        'prescription' => [
            'doctor_name' => 'Dr. Ayesha Malik',
            'doctor_pmdc_number' => '45892-P',
            'patient_name' => 'Rashid Mehmood',
            'patient_cnic' => '35201-9876543-1',
            'patient_phone' => '0300-1234567',
            'hospital_clinic_name' => 'Punjab Institute of Mental Health',
            'prescription_date' => now()->toDateString(),
            'diagnosis_notes' => 'Severe panic disorder',
        ],
        'items' => [
            [
                'product_id' => $narcotic->id,
                'batch_id' => $batch->id,
                'quantity' => 10,
                'unit_price_minor' => 1200,
                'tax_rate_basis_points' => 0,
            ],
        ],
    ], $this->user);

    expect($sale)->not->toBeNull();

    // Verify Prescription model record
    $prescription = Prescription::where('sale_id', $sale->id)->first();
    expect($prescription)->not->toBeNull();
    expect($prescription->doctor_name)->toBe('Dr. Ayesha Malik');
    expect($prescription->doctor_pmdc_number)->toBe('45892-P');
    expect($prescription->patient_name)->toBe('Rashid Mehmood');

    // Verify Form 7 Narcotic Register Entry
    $registerEntry = NarcoticRegisterEntry::where('sale_id', $sale->id)->first();
    expect($registerEntry)->not->toBeNull();
    expect($registerEntry->product_id)->toBe($narcotic->id);
    expect($registerEntry->batch_number)->toBe('XNX-SEC-09');
    expect((float) $registerEntry->quantity_dispensed)->toBe(10.0);
    expect($registerEntry->patient_cnic)->toBe('35201-9876543-1');
    expect($registerEntry->doctor_pmdc_number)->toBe('45892-P');
});

test('batch expiry quarantine automatically marks expired batches as quarantined', function () {
    $medicine = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cough Syrup 120ml',
        'generic_name' => 'Dextromethorphan',
        'unit' => 'bottle',
        'price_minor' => 15000,
        'hs_code' => '30049099',
        'tax_category' => 'pharmaceuticals',
        'is_active' => true,
    ]);

    $expiredBatch = ProductBatch::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $medicine->id,
        'batch_number' => 'EXP-QUAR-01',
        'expiry_date' => now()->subDay()->toDateString(),
        'quantity_on_hand' => 15,
        'status' => 'active',
    ]);

    $validBatch = ProductBatch::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $medicine->id,
        'batch_number' => 'VALID-01',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity_on_hand' => 25,
        'status' => 'active',
    ]);

    $quarantinedCount = $this->batchService->quarantineExpiredBatches($this->tenant);
    expect($quarantinedCount)->toBe(1);

    $expiredBatch->refresh();
    expect($expiredBatch->status)->toBe('quarantined');

    $validBatch->refresh();
    expect($validBatch->status)->toBe('active');
});

test('pharmacy api provides medicine search by brand or generic name and fefo batches', function () {
    $medicine = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Calpol Pediatric Suspension',
        'generic_name' => 'Paracetamol Paediatric',
        'drap_registration_number' => '008899',
        'dosage_form' => 'syrup',
        'strength' => '120mg/5ml',
        'unit' => 'bottle',
        'price_minor' => 12500, // Rs. 125
        'hs_code' => '30049099',
        'tax_category' => 'pharmaceuticals',
        'is_active' => true,
    ]);

    $batch = ProductBatch::create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'product_id' => $medicine->id,
        'batch_number' => 'CAL-001',
        'expiry_date' => now()->addMonths(12)->toDateString(),
        'quantity_on_hand' => 50,
        'status' => 'active',
    ]);

    // Search by generic name: "Paracetamol"
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/pharmacy/medicines?q=Paracetamol');

    $response->assertStatus(200);
    $data = $response->json();
    expect(count($data))->toBeGreaterThanOrEqual(1);
    expect($data[0]['name'])->toBe('Calpol Pediatric Suspension');
    expect($data[0]['generic_name'])->toBe('Paracetamol Paediatric');
    expect($data[0]['fefo_batch']['batch_number'])->toBe('CAL-001');

    // Get batches endpoint
    $batchResponse = $this->actingAs($this->user)
        ->getJson("/api/v1/pos/pharmacy/batches/{$medicine->id}");

    $batchResponse->assertStatus(200);
    $batchData = $batchResponse->json();
    expect(count($batchData))->toBe(1);
    expect($batchData[0]['batch_number'])->toBe('CAL-001');
});

test('pharmacy backoffice web controllers render inertia views and process actions', function () {
    $this->actingAs($this->user);

    $medicine = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Brufen 400mg Tablets',
        'generic_name' => 'Ibuprofen',
        'drap_registration_number' => '003322',
        'unit' => 'tab',
        'price_minor' => 400,
        'cost_minor' => 250,
        'hs_code' => '30049099',
        'tax_category' => 'pharmaceuticals',
        'is_prescription_required' => true,
        'is_active' => true,
    ]);

    // 1. GET /pharmacy/batches
    $res = $this->get('/pharmacy/batches');
    $res->assertOk();

    // 2. POST /pharmacy/batches (create new inventory batch)
    $createBatchRes = $this->from('/pharmacy/batches')->post('/pharmacy/batches', [
        'product_id' => $medicine->id,
        'location_id' => $this->location->id,
        'batch_number' => 'BRU-BATCH-2026',
        'manufacturing_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(24)->toDateString(),
        'quantity_on_hand' => 100,
        'cost_minor' => 250,
        'mrp_minor' => 400,
        'status' => 'active',
    ]);
    $createBatchRes->assertRedirect('/pharmacy/batches');

    $batch = ProductBatch::where('batch_number', 'BRU-BATCH-2026')->first();
    expect($batch)->not->toBeNull();
    expect((float) $batch->quantity_on_hand)->toBe(100.0);

    // 3. POST /pharmacy/batches/quarantine-expired
    $quarantineRes = $this->from('/pharmacy/batches')->post('/pharmacy/batches/quarantine-expired');
    $quarantineRes->assertRedirect('/pharmacy/batches');

    // 4. GET /pharmacy/narcotics
    $narcoticsRes = $this->get('/pharmacy/narcotics');
    $narcoticsRes->assertOk();

    // 5. GET /pharmacy/prescriptions
    $rxListRes = $this->get('/pharmacy/prescriptions');
    $rxListRes->assertOk();
});
