<?php

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Apex Retailers',
        'ntn' => '1234567-8',
        'strn' => '12-34-5678-901-23',
        'province' => 'punjab',
        'fbr_pos_id' => 'POS-1001',
        'notes' => 'Initial onboarding notes',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Apex Owner',
        'email' => 'owner@apex.test',
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Apex Main Branch',
        'province' => 'punjab',
    ]);
});

test('super admin can view tenant edit page', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get("/superadmin/tenants/{$this->tenant->id}/edit");

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Tenants/Edit')
            ->where('tenant.id', $this->tenant->id)
            ->where('tenant.name', 'Apex Retailers')
            ->where('tenant.notes', 'Initial onboarding notes')
        );
});

test('super admin can update tenant profile details and internal notes', function () {
    $response = $this->actingAs($this->superAdmin)
        ->put("/superadmin/tenants/{$this->tenant->id}", [
            'name' => 'Apex Retailers Pakistan Private Limited',
            'ntn' => '7654321-9',
            'strn' => '99-88-7766-554-32',
            'province' => 'sindh',
            'fbr_pos_id' => 'POS-9999',
            'notes' => 'Updated contract terms for 2026',
        ]);

    $response->assertRedirect("/superadmin/tenants/{$this->tenant->id}");
    $response->assertSessionHas('success');

    $this->tenant->refresh();
    expect($this->tenant->name)->toBe('Apex Retailers Pakistan Private Limited');
    expect($this->tenant->ntn)->toBe('7654321-9');
    expect($this->tenant->province)->toBe('sindh');
    expect($this->tenant->fbr_pos_id)->toBe('POS-9999');
    expect($this->tenant->notes)->toBe('Updated contract terms for 2026');
});

test('super admin can suspend tenant with a specific suspension reason and reactivate', function () {
    // Suspend with custom reason
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/suspend", [
            'suspension_reason' => 'Pending tax audit documentation',
        ]);

    $response->assertRedirect();
    $this->tenant->refresh();
    expect($this->tenant->isSuspended())->toBeTrue();
    expect($this->tenant->suspension_reason)->toBe('Pending tax audit documentation');

    // Reactivate clears suspension reason and timestamp
    $reactivateResponse = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/suspend");

    $reactivateResponse->assertRedirect();
    $this->tenant->refresh();
    expect($this->tenant->isSuspended())->toBeFalse();
    expect($this->tenant->suspension_reason)->toBeNull();
});

test('super admin can add a cashier staff user to the tenant', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/users", [
            'name' => 'Kashif Cashier',
            'email' => 'kashif@apex.test',
            'role' => 'cashier',
            'password' => 'CashierPass123!',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $cashier = User::where('tenant_id', $this->tenant->id)
        ->where('email', 'kashif@apex.test')
        ->first();

    expect($cashier)->not->toBeNull();
    expect($cashier->role)->toBe('cashier');
    expect($cashier->super_admin)->toBeFalse();
    expect(Hash::check('CashierPass123!', $cashier->password))->toBeTrue();
});

test('super admin can reset a tenant staff member password', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/users/{$cashier->id}/reset-password", [
            'password' => 'BrandNewPassword999!',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $cashier->refresh();
    expect(Hash::check('BrandNewPassword999!', $cashier->password))->toBeTrue();
});

test('super admin can remove a cashier staff user', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->delete("/superadmin/tenants/{$this->tenant->id}/users/{$cashier->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(User::find($cashier->id))->toBeNull();
});

test('super admin cannot remove the only owner of a tenant', function () {
    $response = $this->actingAs($this->superAdmin)
        ->delete("/superadmin/tenants/{$this->tenant->id}/users/{$this->owner->id}");

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Cannot remove the only owner account of this tenant.');

    expect(User::find($this->owner->id))->not->toBeNull();
});

test('super admin can view tenant creation page', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/tenants/create');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Tenants/Create')
        );
});

test('super admin can register and provision a new tenant organization', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post('/superadmin/tenants', [
            'tenant_name' => 'Grand Hypermarket',
            'ntn' => '9876543-2',
            'strn' => '12-34-5678-001-99',
            'province' => 'punjab',
            'fbr_pos_id' => 'POS-9988',
            'notes' => 'Flagship store in Lahore',
            'owner_name' => 'Tariq Mehmood',
            'owner_email' => 'tariq@hypermarket.test',
            'owner_password' => 'SecurePass123!',
        ]);

    $newTenant = Tenant::where('ntn', '9876543-2')->first();
    expect($newTenant)->not->toBeNull();
    expect($newTenant->name)->toBe('Grand Hypermarket');

    $response->assertRedirect("/superadmin/tenants/{$newTenant->id}");
    $response->assertSessionHas('success');

    $owner = User::withoutGlobalScopes()->where('email', 'tariq@hypermarket.test')->first();
    expect($owner)->not->toBeNull();
    expect($owner->tenant_id)->toBe($newTenant->id);
    expect($owner->role)->toBe('owner');

    $location = Location::where('tenant_id', $newTenant->id)->first();
    expect($location)->not->toBeNull();
    expect($location->name)->toBe('Grand Hypermarket Main Branch');
});

test('accessing invalid non-uuid tenant path returns 404 without database query exception', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/tenants/invalid-non-uuid-string');

    $response->assertStatus(404);
});

test('super admin can add an additional branch location', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/locations", [
            'name' => 'Apex Gulberg Branch',
            'address' => 'Shop 4, Main Boulevard, Gulberg, Lahore',
            'province' => 'punjab',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $newLocation = Location::where('tenant_id', $this->tenant->id)
        ->where('name', 'Apex Gulberg Branch')
        ->first();

    expect($newLocation)->not->toBeNull();
    expect($newLocation->address)->toBe('Shop 4, Main Boulevard, Gulberg, Lahore');
});

test('super admin can view comprehensive tenant details with subscription quotas, terminals, and paginated sales', function () {
    $plan = \App\Models\Plan::create([
        'code' => 'pro-tier',
        'name' => 'Professional Retailer',
        'price_minor' => 999900,
        'billing_interval' => 'monthly',
        'max_locations' => 5,
        'max_terminals' => 10,
        'is_active' => true,
    ]);

    \App\Models\Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonths(1),
    ]);

    $terminal = \App\Models\PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'REG-101',
        'name' => 'Main Cash Counter 1',
        'pairing_status' => 'paired',
        'is_active' => true,
        'last_heartbeat_at' => now()->subMinutes(2),
    ]);

    $sale = \App\Models\Sale::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->owner->id,
        'sale_number' => 'INV-TEST-001',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 500000,
        'tax_minor' => 90000,
        'total_minor' => 590000,
        'status' => 'completed',
        'fbr_status' => 'submitted',
        'fbr_usin' => 'USIN-12345',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get("/superadmin/tenants/{$this->tenant->id}");

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Tenants/Show')
            ->where('tenant.id', $this->tenant->id)
            ->where('subscription.plan_name', 'Professional Retailer')
            ->where('subscription.max_terminals', 10)
            ->where('subscription.active_terminals_count', 1)
            ->has('terminals', 1)
            ->where('terminals.0.terminal_code', 'REG-101')
            ->where('terminals.0.is_online', true)
            ->has('sales.data', 1)
            ->where('sales.data.0.sale_number', 'INV-TEST-001')
            ->where('sales.data.0.fbr_usin', 'USIN-12345')
        );
});

test('super admin can export tenant sales to csv with full fiscal headers', function () {
    \App\Models\Sale::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->owner->id,
        'sale_number' => 'INV-CSV-001',
        'payment_method' => 'card',
        'currency' => 'PKR',
        'subtotal_minor' => 100000,
        'tax_minor' => 18000,
        'total_minor' => 118000,
        'status' => 'completed',
        'fbr_status' => 'submitted',
        'fbr_usin' => 'USIN-CSV-999',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get("/superadmin/tenants/{$this->tenant->id}/sales/export");

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    
    // Capture stream
    ob_start();
    $response->sendContent();
    $csvContent = ob_get_clean();

    expect($csvContent)->toContain('Invoice #');
    expect($csvContent)->toContain('FBR Fiscal Status');
    expect($csvContent)->toContain('INV-CSV-001');
    expect($csvContent)->toContain('USIN-CSV-999');
    expect($csvContent)->toContain('1180.00');
});

test('super admin can assign and update subscription plan for tenant', function () {
    $plan = \App\Models\Plan::create([
        'code' => 'enterprise-tier',
        'name' => 'Enterprise Fleet Tier',
        'price_minor' => 2999900,
        'billing_interval' => 'annual',
        'max_locations' => 20,
        'max_terminals' => 50,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/plan", [
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'notes' => 'Contract agreement signed for 2027',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $activeSub = $this->tenant->currentSubscription();
    expect($activeSub)->not->toBeNull();
    expect($activeSub->plan_id)->toBe($plan->id);
    expect($activeSub->notes)->toBe('Contract agreement signed for 2027');
});

test('super admin can generate terminal pairing code from tenant page', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/terminals/pairing-code", [
            'terminal_code' => 'REG-NEW-01',
            'name' => 'Cosmetics Counter',
            'location_id' => $this->location->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $terminal = \App\Models\PosTerminal::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('terminal_code', 'REG-NEW-01')
        ->first();

    expect($terminal)->not->toBeNull();
    expect($terminal->name)->toBe('Cosmetics Counter');
    expect($terminal->pairing_status)->toBe('pending_pairing');
    expect(strlen($terminal->pairing_token))->toBe(8);
});

test('super admin can revoke and reactivate terminal from tenant page', function () {
    $terminal = \App\Models\PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'REG-REV-01',
        'name' => 'Back Store Counter',
        'pairing_status' => 'paired',
        'is_active' => true,
    ]);

    // Revoke
    $revokeResponse = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/terminals/{$terminal->id}/revoke", [
            'reason' => 'Suspected unauthorized access',
        ]);

    $revokeResponse->assertRedirect();
    $terminal->refresh();
    expect($terminal->is_active)->toBeFalse();
    expect($terminal->pairing_status)->toBe('revoked');
    expect($terminal->revocation_reason)->toBe('Suspected unauthorized access');

    // Reactivate
    $reactivateResponse = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/terminals/{$terminal->id}/reactivate");

    $reactivateResponse->assertRedirect();
    $terminal->refresh();
    expect($terminal->is_active)->toBeTrue();
    expect($terminal->pairing_status)->toBe('paired');
    expect($terminal->revocation_reason)->toBeNull();
});

test('super admin cannot add staff beyond plan max_staff quota', function () {
    $plan = \App\Models\Plan::create([
        'name' => 'Single User Micro Plan',
        'code' => 'micro-plan',
        'price_monthly' => 100000,
        'price_yearly' => 1000000,
        'max_locations' => 1,
        'max_terminals' => 1,
        'max_staff' => 1, // Only 1 staff allowed, and $this->owner already exists
        'max_skus' => 10,
        'is_active' => true,
    ]);

    \App\Models\Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/users", [
            'name' => 'Second Cashier',
            'email' => 'second@apex.test',
            'password' => 'secret12345',
            'role' => 'cashier',
        ]);

    $response->assertSessionHas('error');
    expect(\App\Models\User::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('tenant cannot add product beyond plan max_skus quota', function () {
    $plan = \App\Models\Plan::create([
        'name' => 'Micro SKU Plan',
        'code' => 'sku-micro-plan',
        'price_monthly' => 100000,
        'price_yearly' => 1000000,
        'max_locations' => 1,
        'max_terminals' => 1,
        'max_staff' => 5,
        'max_skus' => 1, // Only 1 SKU allowed
        'is_active' => true,
    ]);

    \App\Models\Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    // Create 1 product (allowed)
    \App\Models\Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'First Product',
        'sku' => 'SKU-001',
        'hs_code' => '12345678',
        'unit' => 'piece',
        'price_minor' => 5000,
        'cost_minor' => 3000,
        'tax_category' => 'standard',
        'is_active' => true,
    ]);

    // Try to add 2nd product via web
    $webResponse = $this->actingAs($this->owner)
        ->post('/products', [
            'name' => 'Second Product',
            'sku' => 'SKU-002',
            'hs_code' => '87654321',
            'unit' => 'piece',
            'price_minor' => 6000,
            'cost_minor' => 4000,
            'tax_category' => 'standard',
        ]);

    $webResponse->assertSessionHas('error');

    // Try to add 2nd product via API
    $token = $this->owner->createToken('test-token')->plainTextToken;
    $apiResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/products', [
            'name' => 'Second Product API',
            'sku' => 'SKU-002-API',
            'hs_code' => '87654321',
            'unit' => 'piece',
            'price_minor' => 6000,
            'cost_minor' => 4000,
            'tax_category' => 'standard',
        ]);

    $apiResponse->assertStatus(422);
    expect(\App\Models\Product::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('super admin can safely purge test tenant', function () {
    $testTenant = Tenant::factory()->create([
        'name' => 'Temporary Test Shop',
        'ntn' => '9999999-9',
        'province' => 'punjab',
    ]);

    Location::factory()->create(['tenant_id' => $testTenant->id]);

    $response = $this->actingAs($this->superAdmin)
        ->delete("/superadmin/tenants/{$testTenant->id}");

    $response->assertRedirect('/superadmin/tenants');
    $response->assertSessionHas('success');

    expect(Tenant::find($testTenant->id))->toBeNull();
});

test('super admin cannot purge tenant with submitted FBR sales unless force_purge_fiscal_archive is true', function () {
    $fbrTenant = Tenant::factory()->create([
        'name' => 'FBR Live Shop',
        'ntn' => '8888888-8',
        'province' => 'punjab',
    ]);

    $loc = Location::factory()->create(['tenant_id' => $fbrTenant->id]);
    $fbrCashier = User::factory()->create([
        'tenant_id' => $fbrTenant->id,
        'role' => 'cashier',
    ]);

    \App\Models\Sale::withoutGlobalScopes()->create([
        'tenant_id' => $fbrTenant->id,
        'location_id' => $loc->id,
        'user_id' => $fbrCashier->id,
        'sale_number' => 'SALE-FBR-TEST-001',
        'fbr_usin' => 'FBR-USIN-999999',
        'fbr_status' => 'submitted',
        'subtotal_minor' => 100000,
        'tax_minor' => 18000,
        'total_minor' => 118000,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    // Attempt standard delete without override -> blocked
    $blockedResponse = $this->actingAs($this->superAdmin)
        ->delete("/superadmin/tenants/{$fbrTenant->id}");

    $blockedResponse->assertSessionHas('error');
    expect(Tenant::find($fbrTenant->id))->not->toBeNull();

    // Attempt delete with force_purge_fiscal_archive -> succeeds
    $forcedResponse = $this->actingAs($this->superAdmin)
        ->delete("/superadmin/tenants/{$fbrTenant->id}", [
            'force_purge_fiscal_archive' => true,
        ]);

    $forcedResponse->assertRedirect('/superadmin/tenants');
    $forcedResponse->assertSessionHas('success');
    expect(Tenant::find($fbrTenant->id))->toBeNull();
});



