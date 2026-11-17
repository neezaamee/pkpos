<?php

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Cash & Carry Lahore',
        'province' => 'punjab',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Gulberg Store',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('super admin can access /superadmin/master-data/catalogs', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/master-data/catalogs');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/MasterData/Catalogs')
            ->has('catalogs', 2)
            ->has('tenants')
        );
});

test('non-superadmin cannot access starter catalogs page', function () {
    $response = $this->actingAs($this->owner)
        ->get('/superadmin/master-data/catalogs');

    $response->assertStatus(403);
});

test('super admin can seed grocery fmcg catalog into tenant store', function () {
    expect(Product::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);

    $response = $this->actingAs($this->superAdmin)
        ->post('/superadmin/master-data/catalogs/seed', [
            'tenant_id' => $this->tenant->id,
            'catalog_key' => 'grocery_fmcg',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify 25 products seeded
    $products = Product::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->get();
    expect($products->count())->toBe(25);

    // Verify Dalda Oil seeded with correct barcode and HS code
    $dalda = $products->firstWhere('sku', 'FMCG-OIL-001');
    expect($dalda)->not->toBeNull();
    expect($dalda->barcode)->toBe('8961011100012');
    expect($dalda->hs_code)->toBe('15121900');
    expect($dalda->price_minor)->toBe(58000);

    // Verify stock level was initialized to 50
    $stock = DB::table('stock_levels')
        ->where('tenant_id', $this->tenant->id)
        ->where('product_id', $dalda->id)
        ->where('location_id', $this->location->id)
        ->first();

    expect($stock)->not->toBeNull();
    expect($stock->quantity_on_hand)->toBe(50);

    // Verify audit log
    $audit = AuditLog::where('event', 'catalog_seeded_to_tenant')
        ->where('tenant_id', $this->tenant->id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->new_values['catalog_key'])->toBe('grocery_fmcg');
});

test('super admin can seed pharmacy catalog directly from tenant show page', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/seed-catalog", [
            'catalog_key' => 'pharmacy',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $panadol = Product::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('sku', 'PHRM-OTC-001')
        ->first();

    expect($panadol)->not->toBeNull();
    expect($panadol->name)->toContain('Panadol 500mg');
    expect($panadol->hs_code)->toBe('30049099');
    expect($panadol->price_minor)->toBe(3500);
});

test('seeding catalog is blocked if tenant subscription quota does not allow sufficient SKUs', function () {
    $restrictivePlan = Plan::create([
        'name' => 'Mini Tier',
        'code' => 'mini-tier',
        'price_monthly' => 100000,
        'price_yearly' => 1000000,
        'max_locations' => 1,
        'max_terminals' => 1,
        'max_staff' => 3,
        'max_skus' => 10, // Only 10 SKUs allowed, but grocery has 25
        'is_active' => true,
    ]);

    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $restrictivePlan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post('/superadmin/master-data/catalogs/seed', [
            'tenant_id' => $this->tenant->id,
            'catalog_key' => 'grocery_fmcg',
        ]);

    $response->assertSessionHas('error');
    expect(Product::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});
