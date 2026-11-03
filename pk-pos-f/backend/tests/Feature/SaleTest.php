<?php

use App\Models\Location;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->user);
});

test('a sale with items saves correctly', function () {
    $clientGeneratedUuid = (string) Str::uuid();

    $sale = Sale::create([
        'id' => $clientGeneratedUuid,
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'sale_number' => 'INV-2026-0001',
        'payment_method' => 'card',
        'currency' => 'PKR',
        'subtotal_minor' => 100000,
        'tax_minor' => 18000,
        'total_minor' => 118000,
        'status' => 'completed',
        'fbr_status' => 'pending',
    ]);

    expect($sale->id)->toBe($clientGeneratedUuid);

    $item1 = $sale->items()->create([
        'product_name' => 'Basmati Rice 5kg',
        'hs_code' => '1006.30.10',
        'quantity' => 1,
        'unit_price_minor' => 60000,
        'tax_rate_basis_points' => 1800,
        'tax_amount_minor' => 10800,
        'line_total_minor' => 70800,
    ]);

    $item2 = $sale->items()->create([
        'product_name' => 'Cooking Oil 1L',
        'hs_code' => '1515.29.00',
        'quantity' => 1,
        'unit_price_minor' => 40000,
        'tax_rate_basis_points' => 1800,
        'tax_amount_minor' => 7200,
        'line_total_minor' => 47200,
    ]);

    $this->assertDatabaseHas('sales', [
        'id' => $clientGeneratedUuid,
        'sale_number' => 'INV-2026-0001',
        'total_minor' => 118000,
    ]);

    $this->assertDatabaseHas('sale_items', [
        'id' => $item1->id,
        'sale_id' => $clientGeneratedUuid,
        'hs_code' => '1006.30.10',
    ]);

    $this->assertDatabaseHas('sale_items', [
        'id' => $item2->id,
        'sale_id' => $clientGeneratedUuid,
        'hs_code' => '1515.29.00',
    ]);

    expect($sale->items)->toHaveCount(2);
});

test('a sale without an hs_code on any item fails validation', function () {
    // 1. Model-level validation guard test
    $sale = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'sale_number' => 'INV-INVALID-HS',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 50000,
        'tax_minor' => 9000,
        'total_minor' => 59000,
        'status' => 'completed',
    ]);

    expect(function () use ($sale) {
        $sale->items()->create([
            'product_name' => 'Product without HS code',
            'hs_code' => '',
            'quantity' => 1,
            'unit_price_minor' => 50000,
            'tax_rate_basis_points' => 1800,
            'tax_amount_minor' => 9000,
            'line_total_minor' => 59000,
        ]);
    })->toThrow(InvalidArgumentException::class, 'The hs_code is required for all sale items.');

    // 2. API-level validation test
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/sales', [
        'id' => (string) Str::uuid(),
        'location_id' => $this->location->id,
        'sale_number' => 'INV-API-INVALID',
        'payment_method' => 'cash',
        'subtotal_minor' => 50000,
        'tax_minor' => 9000,
        'total_minor' => 59000,
        'status' => 'completed',
        'items' => [
            [
                'product_name' => 'Item 1 with HS',
                'hs_code' => '1001.99.00',
                'quantity' => 1,
                'unit_price_minor' => 25000,
                'tax_rate_basis_points' => 1800,
                'tax_amount_minor' => 4500,
                'line_total_minor' => 29500,
            ],
            [
                'product_name' => 'Item 2 missing HS',
                'hs_code' => '', // missing/empty
                'quantity' => 1,
                'unit_price_minor' => 25000,
                'tax_rate_basis_points' => 1800,
                'tax_amount_minor' => 4500,
                'line_total_minor' => 29500,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items.1.hs_code']);
});

test('a sale cannot be updated after status = completed', function () {
    $sale = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->user->id,
        'sale_number' => 'INV-COMPLETED-101',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 100000,
        'tax_minor' => 18000,
        'total_minor' => 118000,
        'status' => 'completed',
    ]);

    // Model-level update guard: should throw DomainException
    expect(function () use ($sale) {
        $sale->update(['total_minor' => 200000]);
    })->toThrow(DomainException::class, 'A completed sale cannot be updated.');

    // Assert that the sale in database remains unaltered
    $freshSale = Sale::find($sale->id);
    expect($freshSale->total_minor)->toBe(118000);

    // Deletion is also guarded
    expect(function () use ($sale) {
        $sale->delete();
    })->toThrow(DomainException::class, 'A completed sale cannot be deleted.');
});
