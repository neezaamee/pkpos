<?php

use App\Models\FbrCredential;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

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
});

test('unauthenticated user is redirected to login', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('login page renders with Auth/Login component', function () {
    $this->get('/login')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
        );
});

test('user can authenticate and log out', function () {
    $response = $this->post('/login', [
        'email' => $this->ownerA->email,
        'password' => 'password', // default factory password
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->ownerA);

    $logoutResponse = $this->post('/logout');
    $logoutResponse->assertRedirect('/login');
    $this->assertGuest();
});

test('a non-owner user cannot access /fbr-credentials (403)', function () {
    $this->actingAs($this->cashierA)
        ->get('/fbr-credentials')
        ->assertStatus(403);
});

test('an owner user can access /fbr-credentials and bearer_token is never displayed', function () {
    // Setup existing credentials with a secret bearer token
    FbrCredential::create([
        'tenant_id' => $this->tenantA->id,
        'pos_id' => '1029384',
        'ntn' => '1234567-8',
        'strn' => '0123456789012',
        'bearer_token' => 'super-secret-fbr-token-12345',
        'token_expires_at' => now()->addDays(90),
    ]);

    $response = $this->actingAs($this->ownerA)
        ->get('/fbr-credentials');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('FbrCredentials')
            ->has('credentials.pos_id')
            ->has('credentials.ntn')
            ->where('credentials.pos_id', '1029384')
            ->where('hasExistingToken', true)
            // Assert bearer_token is NEVER exposed in Inertia props
            ->missing('credentials.bearer_token')
        );
});

test('owner can store and update fbr credentials via useForm post', function () {
    $response = $this->actingAs($this->ownerA)
        ->post('/fbr-credentials', [
            'pos_id' => '7788990',
            'ntn' => '9876543-2',
            'strn' => '5544332211009',
            'bearer_token' => 'brand-new-fbr-bearer-token',
            'token_expires_at' => '2027-12-31',
        ]);

    $response->assertRedirect();

    $credential = FbrCredential::where('tenant_id', $this->tenantA->id)->first();
    expect($credential)->not->toBeNull();
    expect($credential->pos_id)->toBe('7788990');
    expect($credential->ntn)->toBe('9876543-2');
    expect($credential->bearer_token)->toBe('brand-new-fbr-bearer-token');
});

test('the sales table includes only the authenticated users tenant sales', function () {
    // Create sales for Tenant A
    $saleA1 = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantA->id,
        'location_id' => $this->locationA->id,
        'user_id' => $this->ownerA->id,
        'sale_number' => 'INV-TENANT-A-001',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 10000,
        'tax_minor' => 1800,
        'total_minor' => 11800,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    $saleA2 = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantA->id,
        'location_id' => $this->locationA->id,
        'user_id' => $this->ownerA->id,
        'sale_number' => 'INV-TENANT-A-002',
        'payment_method' => 'card',
        'currency' => 'PKR',
        'subtotal_minor' => 20000,
        'tax_minor' => 3600,
        'total_minor' => 23600,
        'status' => 'completed',
        'fbr_status' => 'offline',
    ]);

    $userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
    ]);

    // Create sales for Tenant B
    $saleB1 = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantB->id,
        'location_id' => $this->locationB->id,
        'user_id' => $userB->id,
        'sale_number' => 'INV-TENANT-B-999',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 99000,
        'tax_minor' => 17820,
        'total_minor' => 116820,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    // Request as user from Tenant A
    $response = $this->actingAs($this->ownerA)
        ->get('/sales');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales')
            ->has('sales.data', 2)
            ->where('sales.data.0.sale_number', fn ($val) => in_array($val, ['INV-TENANT-A-001', 'INV-TENANT-A-002']))
            ->where('sales.data.1.sale_number', fn ($val) => in_array($val, ['INV-TENANT-A-001', 'INV-TENANT-A-002']))
        );

    // Ensure Tenant B's sale number never appears in the props
    $salesData = $response->viewData('page')['props']['sales']['data'];
    $saleNumbers = collect($salesData)->pluck('sale_number')->all();

    expect($saleNumbers)->toContain('INV-TENANT-A-001');
    expect($saleNumbers)->toContain('INV-TENANT-A-002');
    expect($saleNumbers)->not->toContain('INV-TENANT-B-999');
});

test('dashboard page displays correct tenant summary cards', function () {
    // Today's sale
    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantA->id,
        'location_id' => $this->locationA->id,
        'user_id' => $this->ownerA->id,
        'sale_number' => 'INV-DASH-001',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 50000,
        'tax_minor' => 9000,
        'total_minor' => 59000,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    $response = $this->actingAs($this->ownerA)
        ->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('stats')
            ->where('stats.today_sales_count', 1)
            ->where('stats.total_revenue_minor', 59000)
            ->where('stats.formatted_revenue', 'PKR 590.00')
            ->where('stats.fbr_submitted_count', 1)
        );
});
