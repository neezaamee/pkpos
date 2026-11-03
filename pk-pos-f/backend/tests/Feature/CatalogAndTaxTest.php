<?php

use App\Exceptions\TaxRuleNotFoundException;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\TaxRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tax\TaxResolver;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['province' => 'punjab']);
    $this->tenantB = Tenant::factory()->create(['province' => 'sindh']);

    $this->ownerA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'owner',
    ]);

    $this->managerA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'manager',
    ]);

    $this->cashierA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'cashier',
    ]);

    $this->locationA = Location::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'province' => 'punjab',
    ]);

    $this->taxResolver = app(TaxResolver::class);
});

test('tax resolver: federal 18% resolves for general_goods + cash', function () {
    $rule = TaxRule::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Federal Standard GST',
        'tax_category' => 'general_goods',
        'province' => 'federal',
        'payment_method' => 'any',
        'rate_basis_points' => 1800,
        'authority' => 'fbr',
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $resolved = $this->taxResolver->resolve(
        $this->tenantA,
        'general_goods',
        'punjab',
        'cash',
        Carbon::parse('2026-06-01')
    );

    expect($resolved->id)->toBe($rule->id);
    expect($resolved->rate_basis_points)->toBe(1800);
});

test('tax resolver: punjab restaurant rule with payment_method=card and rate 800 wins over payment_method=any rate 1600 for the same date', function () {
    $anyRule = TaxRule::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Punjab Restaurant Standard Rate',
        'tax_category' => 'restaurant',
        'province' => 'punjab',
        'payment_method' => 'any',
        'rate_basis_points' => 1600,
        'authority' => 'pra',
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $cardRule = TaxRule::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Punjab Restaurant Digital Payment Incentive',
        'tax_category' => 'restaurant',
        'province' => 'punjab',
        'payment_method' => 'card',
        'rate_basis_points' => 800,
        'authority' => 'pra',
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $resolved = $this->taxResolver->resolve(
        $this->tenantA,
        'restaurant',
        'punjab',
        'card',
        Carbon::parse('2026-06-01')
    );

    expect($resolved->id)->toBe($cardRule->id);
    expect($resolved->rate_basis_points)->toBe(800);
});

test('tax resolver: expired rule is not returned when a newer rule exists', function () {
    // Expired rule for 2025
    TaxRule::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Goods Rate 2025',
        'tax_category' => 'general_goods',
        'province' => 'federal',
        'payment_method' => 'any',
        'rate_basis_points' => 1700,
        'authority' => 'fbr',
        'effective_from' => '2025-01-01',
        'effective_to' => '2025-12-31',
        'is_active' => true,
    ]);

    // Active rule for 2026
    $activeRule = TaxRule::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Goods Rate 2026',
        'tax_category' => 'general_goods',
        'province' => 'federal',
        'payment_method' => 'any',
        'rate_basis_points' => 1800,
        'authority' => 'fbr',
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    $resolved = $this->taxResolver->resolve(
        $this->tenantA,
        'general_goods',
        'punjab',
        'cash',
        Carbon::parse('2026-05-15')
    );

    expect($resolved->id)->toBe($activeRule->id);
    expect($resolved->rate_basis_points)->toBe(1800);
});

test('tax resolver: no match throws TaxRuleNotFoundException', function () {
    expect(function () {
        $this->taxResolver->resolve(
            $this->tenantA,
            'unregistered_category',
            'balochistan',
            'wallet',
            Carbon::parse('2026-01-01')
        );
    })->toThrow(TaxRuleNotFoundException::class);
});

test('product API: create with invalid hs_code (6 digits or letters) returns 422', function () {
    Sanctum::actingAs($this->ownerA);

    // 6 digits
    $response1 = $this->postJson('/api/v1/products', [
        'name' => 'Invalid HS Product 1',
        'hs_code' => '123456',
        'unit' => 'piece',
        'price_minor' => 10000,
        'tax_category' => 'general_goods',
    ]);
    $response1->assertStatus(422)->assertJsonValidationErrors(['hs_code']);

    // letters
    $response2 = $this->postJson('/api/v1/products', [
        'name' => 'Invalid HS Product 2',
        'hs_code' => '1234ABCD',
        'unit' => 'piece',
        'price_minor' => 10000,
        'tax_category' => 'general_goods',
    ]);
    $response2->assertStatus(422)->assertJsonValidationErrors(['hs_code']);
});

test('product API: cashier role gets 403 on POST', function () {
    Sanctum::actingAs($this->cashierA);

    $response = $this->postJson('/api/v1/products', [
        'name' => 'Cashier Created Product',
        'hs_code' => '10063010',
        'unit' => 'piece',
        'price_minor' => 10000,
        'tax_category' => 'general_goods',
    ]);

    $response->assertStatus(403);
});

test('product API: editing hs_code when referenced by a sale_item returns 422', function () {
    Sanctum::actingAs($this->ownerA);

    $product = Product::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Sample Rice Bag',
        'hs_code' => '10063010',
        'unit' => 'piece',
        'price_minor' => 50000,
        'tax_category' => 'general_goods',
        'is_active' => true,
    ]);

    $sale = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantA->id,
        'location_id' => $this->locationA->id,
        'user_id' => $this->ownerA->id,
        'sale_number' => 'INV-HISTORICAL-001',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 50000,
        'tax_minor' => 9000,
        'total_minor' => 59000,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    // Create historical sale item referencing product
    SaleItem::create([
        'id' => (string) Str::uuid(),
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'product_name' => 'Sample Rice Bag',
        'hs_code' => '10063010',
        'quantity' => 1,
        'unit_price_minor' => 50000,
        'tax_rate_basis_points' => 1800,
        'tax_amount_minor' => 9000,
        'line_total_minor' => 59000,
    ]);

    // Attempting to update hs_code should fail with 422
    $response = $this->putJson("/api/v1/products/{$product->id}", [
        'name' => 'Sample Rice Bag Renamed',
        'hs_code' => '10063090', // changed HS code
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Cannot change HS code for a product referenced in completed sales.',
        ]);

    // Updating non-hs_code field (e.g. name or price) should succeed
    $okResponse = $this->putJson("/api/v1/products/{$product->id}", [
        'name' => 'Sample Rice Bag Renamed',
        'hs_code' => '10063010', // unchanged
        'price_minor' => 55000,
    ]);
    $okResponse->assertStatus(200);
});

test('tax rule API: PUT attempting to change rate_basis_points returns 422', function () {
    Sanctum::actingAs($this->ownerA);

    $rule = TaxRule::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Standard Rate',
        'tax_category' => 'general_goods',
        'province' => 'federal',
        'payment_method' => 'any',
        'rate_basis_points' => 1800,
        'authority' => 'fbr',
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $response = $this->putJson("/api/v1/tax-rules/{$rule->id}", [
        'rate_basis_points' => 2000,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Create a new rule instead.',
        ]);

    // Updating is_active or effective_to succeeds
    $okResponse = $this->putJson("/api/v1/tax-rules/{$rule->id}", [
        'is_active' => false,
        'effective_to' => '2026-12-31',
    ]);
    $okResponse->assertStatus(200);
    expect($rule->fresh()->is_active)->toBeFalse();
});

test('isolation: user from Tenant A cannot GET Tenant B product (404)', function () {
    Sanctum::actingAs($this->ownerA);

    $productB = Product::create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Tenant B Secret Product',
        'hs_code' => '84713010',
        'unit' => 'piece',
        'price_minor' => 15000000,
        'tax_category' => 'general_goods',
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/v1/products/{$productB->id}");

    $response->assertStatus(404);
});
