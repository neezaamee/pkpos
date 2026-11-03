<?php

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Plan;
use App\Models\PosTerminal;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Retail Mart',
        'province' => 'punjab',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);

    // Base location
    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Gulberg Store',
        'province' => 'punjab',
    ]);

    // Ensure Starter and Enterprise plans exist
    $this->starterPlan = Plan::firstOrCreate(
        ['code' => 'starter'],
        [
            'name' => 'Starter Tier',
            'price_minor' => 350000,
            'billing_interval' => 'month',
            'max_locations' => 1,
            'max_terminals' => 1,
            'is_active' => true,
        ]
    );

    $this->growthPlan = Plan::firstOrCreate(
        ['code' => 'growth'],
        [
            'name' => 'Retail Growth Tier',
            'price_minor' => 850000,
            'billing_interval' => 'month',
            'max_locations' => 3,
            'max_terminals' => 5,
            'is_active' => true,
        ]
    );
});

test('non-super admin cannot access plans management', function () {
    $this->actingAs($this->owner)
        ->get(route('superadmin.plans.index'))
        ->assertForbidden();
});

test('super admin can view plans and subscriptions dashboard with MRR metrics', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->starterPlan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(10),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.plans.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Plans/Index')
            ->has('plans')
            ->has('subscriptions.data', 1)
            ->where('metrics.active_subscribers_count', 1)
            ->where('metrics.mrr_pkr', 3500)
        );
});

test('super admin can create a new pricing plan tier', function () {
    $payload = [
        'code' => 'kiosk_tier',
        'name' => 'Kiosk Express',
        'description' => 'Single terminal kiosk without inventory tracking.',
        'price_minor' => 200000,
        'billing_interval' => 'month',
        'max_locations' => 1,
        'max_terminals' => 1,
        'features' => ['fbr_integration', 'offline_sales'],
        'is_active' => true,
    ];

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.plans.store'), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('plans', [
        'code' => 'kiosk_tier',
        'price_minor' => 200000,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'plan_created',
        'auditable_type' => Plan::class,
    ]);
});

test('super admin can update an existing pricing plan tier', function () {
    $this->actingAs($this->superAdmin)
        ->put(route('superadmin.plans.update', $this->starterPlan->id), [
            'code' => 'starter',
            'name' => 'Starter Tier Plus',
            'description' => 'Updated starter description',
            'price_minor' => 400000,
            'billing_interval' => 'month',
            'max_locations' => 1,
            'max_terminals' => 1,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($this->starterPlan->fresh()->name)->toBe('Starter Tier Plus')
        ->and($this->starterPlan->fresh()->price_minor)->toBe(400000);
});

test('super admin can assign and upgrade a tenant subscription', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.subscriptions.assign'), [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'notes' => 'Contract signed for 1 year',
        ])
        ->assertRedirect();

    $activeSub = $this->tenant->currentSubscription();
    expect($activeSub)->not->toBeNull();
    expect($activeSub->plan_id)->toBe($this->growthPlan->id);
    expect($activeSub->notes)->toBe('Contract signed for 1 year');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'subscription_assigned',
        'auditable_type' => Subscription::class,
    ]);
});

test('super admin can cancel a subscription', function () {
    $sub = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->starterPlan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(5),
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.subscriptions.cancel', $sub->id))
        ->assertRedirect();

    expect($sub->fresh()->status)->toBe('canceled')
        ->and($sub->fresh()->canceled_at)->not->toBeNull();
});

test('quota enforcement: tenant on starter plan cannot add a second branch location', function () {
    // Assign Starter Plan (max_locations = 1)
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->starterPlan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    expect($this->tenant->canAddLocation())->toBeFalse();

    // Attempting to add 2nd branch via controller should be blocked
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/locations", [
            'name' => 'Second DHA Branch',
            'address' => 'Phase 5 DHA, Lahore',
            'province' => 'punjab',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    expect(Location::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('quota enforcement: tenant on starter plan cannot generate pairing code or pair extra terminal', function () {
    // Assign Starter Plan (max_terminals = 1)
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->starterPlan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    // Create 1 active paired terminal
    PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-01',
        'name' => 'Counter 1',
        'pairing_status' => 'paired',
        'is_active' => true,
    ]);

    expect($this->tenant->canAddTerminal())->toBeFalse();

    // Generating code for 2nd terminal is blocked
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.terminals.pairing-code'), [
            'tenant_id' => $this->tenant->id,
            'terminal_code' => 'POS-02',
            'name' => 'Counter 2',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    // Client pairing request for 2nd terminal is rejected with 403
    $pendingTerminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-03',
        'name' => 'Counter 3',
        'pairing_token' => 'PENDING1',
        'pairing_status' => 'pending_pairing',
        'is_active' => true,
    ]);

    $pairResponse = $this->postJson('/api/v1/terminals/pair', [
        'pairing_token' => 'PENDING1',
        'hardware_uuid' => 'HW-EXCEEDED-01',
    ]);

    $pairResponse->assertStatus(403)
        ->assertJsonFragment(['message' => "Terminal quota exceeded. The merchant's plan allows a maximum of 1 active terminals."]);
});

test('quota upgrade: upgrading tenant to growth plan unlocks additional branches and terminals', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->starterPlan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    expect($this->tenant->canAddLocation())->toBeFalse();

    // Upgrade to Growth Plan (max_locations = 3)
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.subscriptions.assign'), [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $this->tenant->refresh();
    expect($this->tenant->canAddLocation())->toBeTrue()
        ->and($this->tenant->canAddTerminal())->toBeTrue();

    // Now adding 2nd branch succeeds
    $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/locations", [
            'name' => 'Apex Johar Town Branch',
            'address' => 'G1 Market Johar Town, Lahore',
            'province' => 'punjab',
        ])
        ->assertSessionHas('success');

    expect(Location::where('tenant_id', $this->tenant->id)->count())->toBe(2);
});

