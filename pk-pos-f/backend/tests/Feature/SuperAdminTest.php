<?php

use App\Mail\TenantWelcomeMail;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenantA = Tenant::factory()->create([
        'name' => 'Alpha Supermarket',
        'ntn' => '1111111-1',
        'province' => 'punjab',
    ]);

    $this->ownerA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->tenantB = Tenant::factory()->create([
        'name' => 'Beta Mart',
        'ntn' => '2222222-2',
        'province' => 'sindh',
    ]);

    $this->ownerB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('a super admin can access /superadmin/tenants and sees all tenants', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/tenants');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Tenants/Index')
            ->has('tenants.data', 2)
            ->where('tenants.data.0.name', fn ($name) => in_array($name, ['Alpha Supermarket', 'Beta Mart']))
        );
});

test('a tenant owner gets 403 on /superadmin/tenants and /superadmin/dashboard', function () {
    $this->actingAs($this->ownerA)
        ->get('/superadmin/tenants')
        ->assertStatus(403);

    $this->actingAs($this->ownerA)
        ->get('/superadmin/dashboard')
        ->assertStatus(403);
});

test('an unauthenticated user is redirected to login for superadmin routes', function () {
    $this->get('/superadmin/tenants')
        ->assertRedirect('/login');

    $this->get('/superadmin/dashboard')
        ->assertRedirect('/login');
});

test('super admin dashboard renders metrics across all tenants bypassing tenant scope', function () {
    $locationA = Location::factory()->create(['tenant_id' => $this->tenantA->id]);
    $locationB = Location::factory()->create(['tenant_id' => $this->tenantB->id]);

    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantA->id,
        'location_id' => $locationA->id,
        'user_id' => $this->ownerA->id,
        'sale_number' => 'SAL-001',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 10000,
        'tax_minor' => 1800,
        'total_minor' => 11800,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantB->id,
        'location_id' => $locationB->id,
        'user_id' => $this->ownerB->id,
        'sale_number' => 'SAL-002',
        'payment_method' => 'card',
        'currency' => 'PKR',
        'subtotal_minor' => 20000,
        'tax_minor' => 3600,
        'total_minor' => 23600,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    $this->actingAs($this->superAdmin)
        ->get('/superadmin/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Dashboard')
            ->where('metrics.total_tenants', 2)
            ->where('metrics.active_tenants', 2)
            ->where('metrics.total_sales_volume', 35400)
            ->where('metrics.fbr_submissions_today', 2)
        );
});

test('suspending a tenant sets suspended_at timestamp and toggles back', function () {
    expect($this->tenantA->suspended_at)->toBeNull();

    // Suspend
    $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenantA->id}/suspend")
        ->assertSessionHas('success');

    $this->tenantA->refresh();
    expect($this->tenantA->suspended_at)->not->toBeNull();
    expect($this->tenantA->isSuspended())->toBeTrue();

    // Reactivate
    $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenantA->id}/suspend")
        ->assertSessionHas('success');

    $this->tenantA->refresh();
    expect($this->tenantA->suspended_at)->toBeNull();
    expect($this->tenantA->isSuspended())->toBeFalse();
});

test('suspending a tenant prevents that tenants owner from logging in', function () {
    // Suspend Tenant A
    $this->tenantA->update(['suspended_at' => now()]);

    // Try login
    $response = $this->post('/login', [
        'email' => $this->ownerA->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
});

test('suspending a tenant causes Tauri client sync endpoints to return 403 with message Tenant suspended', function () {
    $token = $this->ownerA->createToken('pos-client')->plainTextToken;

    // Active tenant can access sync ping / endpoints
    $activeResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/sales/sync', [
            'sales' => [],
        ]);
    expect($activeResponse->status())->not->toBe(403);

    // Suspend Tenant A
    $this->tenantA->update(['suspended_at' => now()]);

    // Suspended tenant gets 403
    $suspendedResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/sales/sync', [
            'sales' => [],
        ]);

    $suspendedResponse->assertStatus(403)
        ->assertJson([
            'message' => 'Tenant suspended',
        ]);
});

test('impersonation logs in as the target user and return restores super admin', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/impersonate/{$this->ownerA->id}");

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->ownerA);
    expect(session('impersonated_by'))->toBe($this->superAdmin->id);

    // Leaving impersonation
    $leaveResponse = $this->get('/superadmin/impersonate/leave');
    $leaveResponse->assertRedirect('/superadmin/tenants');
    $this->assertAuthenticatedAs($this->superAdmin);
    expect(session('impersonated_by'))->toBeNull();
});

test('tenant creation creates tenant + location + owner in one transaction and queues welcome email', function () {
    Mail::fake();

    $payload = [
        'tenant_name' => 'Horizon Retailers',
        'ntn' => '7778889-9',
        'strn' => '77-88-9999-001-11',
        'province' => 'punjab',
        'fbr_pos_id' => 'POS-7788',
        'owner_name' => 'Tariq Mehmood',
        'owner_email' => 'tariq@horizon.test',
        'owner_password' => 'SecurePass123!',
    ];

    $response = $this->actingAs($this->superAdmin)
        ->post('/superadmin/tenants', $payload);

    $createdTenant = Tenant::where('name', 'Horizon Retailers')->first();
    expect($createdTenant)->not->toBeNull();

    $response->assertRedirect("/superadmin/tenants/{$createdTenant->id}");

    // Assert Location created in same transaction
    $location = Location::where('tenant_id', $createdTenant->id)->first();
    expect($location)->not->toBeNull();
    expect($location->province)->toBe('punjab');

    // Assert Owner User created in same transaction
    $owner = User::where('tenant_id', $createdTenant->id)->where('email', 'tariq@horizon.test')->first();
    expect($owner)->not->toBeNull();
    expect($owner->role)->toBe('owner');
    expect($owner->super_admin)->toBeFalse();

    // Assert Welcome Email queued
    Mail::assertQueued(TenantWelcomeMail::class, function ($mail) use ($owner, $createdTenant) {
        return $mail->hasTo('tariq@horizon.test') &&
            $mail->tenant->id === $createdTenant->id &&
            $mail->owner->id === $owner->id;
    });
});
