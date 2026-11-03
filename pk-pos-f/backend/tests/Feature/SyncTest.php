<?php

use App\Models\Location;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->otherTenant = Tenant::factory()->create();

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

test('ping returns 200 without a token', function () {
    $response = $this->getJson('/api/v1/ping');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
        ])
        ->assertJsonStructure(['status', 'server_time']);
});

test('duplicate idempotency key returns cached response', function () {
    Sanctum::actingAs($this->user);

    $idempotencyKey = (string) Str::uuid();
    $saleId = (string) Str::uuid();

    $payload = [
        [
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'sale_number' => 'INV-IDEM-001',
            'payment_method' => 'cash',
            'currency' => 'PKR',
            'subtotal_minor' => 10000,
            'tax_minor' => 1800,
            'total_minor' => 11800,
            'status' => 'completed',
            'items' => [
                [
                    'product_name' => 'Tea 500g',
                    'hs_code' => '0902.30.00',
                    'quantity' => 1,
                    'unit_price_minor' => 10000,
                    'tax_rate_basis_points' => 1800,
                    'tax_amount_minor' => 1800,
                    'line_total_minor' => 11800,
                ],
            ],
        ],
    ];

    // First request
    $firstResponse = $this->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/sales/sync', $payload);

    $firstResponse->assertStatus(200)
        ->assertJson(['synced' => 1, 'errors' => []])
        ->assertHeaderMissing('Idempotency-Replayed');

    // Second request with identical Idempotency-Key
    $secondResponse = $this->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/sales/sync', $payload);

    $secondResponse->assertStatus(200)
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertJson(['synced' => 1, 'errors' => []]);

    // Ensure only one sale was created in the database
    expect(Sale::where('id', $saleId)->count())->toBe(1);
});

test('sale sync with 100 records succeeds', function () {
    Sanctum::actingAs($this->user);

    $records = [];
    for ($i = 1; $i <= 100; $i++) {
        $records[] = [
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'sale_number' => sprintf('INV-BULK-%04d', $i),
            'payment_method' => 'cash',
            'currency' => 'PKR',
            'subtotal_minor' => 5000,
            'tax_minor' => 900,
            'total_minor' => 5900,
            'status' => 'completed',
            'items' => [
                [
                    'product_name' => "Item $i",
                    'hs_code' => '1001.99.00',
                    'quantity' => 1,
                    'unit_price_minor' => 5000,
                    'tax_rate_basis_points' => 1800,
                    'tax_amount_minor' => 900,
                    'line_total_minor' => 5900,
                ],
            ],
        ];
    }

    $response = $this->postJson('/api/v1/sales/sync', $records);

    $response->assertStatus(200)
        ->assertJson([
            'synced' => 100,
            'errors' => [],
        ]);

    expect(Sale::where('tenant_id', $this->tenant->id)->count())->toBe(100);
});

test('sale sync batch exceeding 100 records is rejected with 422', function () {
    Sanctum::actingAs($this->user);

    $records = [];
    for ($i = 1; $i <= 101; $i++) {
        $records[] = [
            'id' => (string) Str::uuid(),
            'sale_number' => "INV-OVER-$i",
            'payment_method' => 'cash',
            'subtotal_minor' => 1000,
            'tax_minor' => 180,
            'total_minor' => 1180,
            'status' => 'completed',
            'items' => [],
        ];
    }

    $response = $this->postJson('/api/v1/sales/sync', $records);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'The sales batch cannot exceed 100 records.',
        ]);
});

test('sale sync with a record from another tenant returns 403 for that record', function () {
    Sanctum::actingAs($this->user);

    $payload = [
        [
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'sale_number' => 'INV-TENANT-OK',
            'payment_method' => 'cash',
            'currency' => 'PKR',
            'subtotal_minor' => 5000,
            'tax_minor' => 900,
            'total_minor' => 5900,
            'status' => 'completed',
            'items' => [
                [
                    'product_name' => 'Valid Item',
                    'hs_code' => '1001.99.00',
                    'quantity' => 1,
                    'unit_price_minor' => 5000,
                    'line_total_minor' => 5900,
                ],
            ],
        ],
        [
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->otherTenant->id, // Malicious or misconfigured cross-tenant record
            'location_id' => $this->location->id,
            'sale_number' => 'INV-TENANT-CROSS',
            'payment_method' => 'cash',
            'currency' => 'PKR',
            'subtotal_minor' => 5000,
            'tax_minor' => 900,
            'total_minor' => 5900,
            'status' => 'completed',
            'items' => [],
        ],
    ];

    $response = $this->postJson('/api/v1/sales/sync', $payload);

    $response->assertStatus(403);
    // Ensure no records were persisted from this batch
    expect(Sale::where('sale_number', 'INV-TENANT-CROSS')->count())->toBe(0);
});

test('sale sync with existing UUID is idempotent and returns 200', function () {
    Sanctum::actingAs($this->user);

    $saleId = (string) Str::uuid();

    $saleData = [
        'id' => $saleId,
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'sale_number' => 'INV-EXISTING-001',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 5000,
        'tax_minor' => 900,
        'total_minor' => 5900,
        'status' => 'completed',
        'items' => [
            [
                'product_name' => 'Item',
                'hs_code' => '1001.99.00',
                'quantity' => 1,
                'unit_price_minor' => 5000,
                'line_total_minor' => 5900,
            ],
        ],
    ];

    // First sync creates the record
    $res1 = $this->postJson('/api/v1/sales/sync', [$saleData]);
    $res1->assertStatus(200)->assertJson(['synced' => 1]);

    // Second sync with different request / no idempotency key has the same UUID
    $res2 = $this->postJson('/api/v1/sales/sync', [$saleData]);
    $res2->assertStatus(200)->assertJson(['synced' => 0, 'errors' => []]);
});

test('failed-fbr endpoint updates fbr_status and fbr_error', function () {
    Sanctum::actingAs($this->user);

    $saleId = (string) Str::uuid();

    $sale = Sale::create([
        'id' => $saleId,
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'sale_number' => 'INV-FBR-FAIL',
        'payment_method' => 'card',
        'currency' => 'PKR',
        'subtotal_minor' => 20000,
        'tax_minor' => 3600,
        'total_minor' => 23600,
        'status' => 'completed',
        'fbr_status' => 'pending',
    ]);

    $response = $this->postJson('/api/v1/sales/failed-fbr', [
        'id' => $saleId,
        'fbr_error' => 'FBR Gateway Timeout: 504 Gateway Time-out',
    ]);

    $response->assertStatus(200)
        ->assertJson(['status' => 'ok', 'updated' => 1]);

    $freshSale = Sale::find($saleId);
    expect($freshSale->fbr_status)->toBe('failed');
    expect($freshSale->fbr_error)->toBe('FBR Gateway Timeout: 504 Gateway Time-out');
});

test('stock-movements sync inserts records idempotently', function () {
    Sanctum::actingAs($this->user);

    $movementId = (string) Str::uuid();

    $payload = [
        [
            'id' => $movementId,
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'movement_type' => 'in',
            'quantity' => 50,
            'reference' => 'PO-10029',
        ],
    ];

    $response = $this->postJson('/api/v1/stock-movements/sync', $payload);
    $response->assertStatus(200)->assertJson(['synced' => 1]);

    expect(StockMovement::where('id', $movementId)->count())->toBe(1);

    // Re-sending should be idempotent
    $response2 = $this->postJson('/api/v1/stock-movements/sync', $payload);
    $response2->assertStatus(200)->assertJson(['synced' => 0]);
    expect(StockMovement::where('id', $movementId)->count())->toBe(1);
});
