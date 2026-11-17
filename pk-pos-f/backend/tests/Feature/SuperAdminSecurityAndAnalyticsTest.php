<?php

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SuperAdminIpWhitelist;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin.sec@pkpos.test',
        'staff_role' => 'super_admin',
    ]);

    // Ensure clean state for whitelist table
    SuperAdminIpWhitelist::query()->delete();
});

test('it computes saas analytics metrics accurately on dashboard', function () {
    $planBasic = Plan::create([
        'name' => 'Basic Tier',
        'code' => 'basic-' . uniqid(),
        'price_minor' => 500000, // PKR 5,000 / month
        'billing_interval' => 'monthly',
        'max_locations' => 1,
        'max_terminals' => 1,
        'max_staff' => 3,
        'max_skus' => 500,
        'is_active' => true,
    ]);

    $planPro = Plan::create([
        'name' => 'Pro Tier',
        'code' => 'pro-' . uniqid(),
        'price_minor' => 1000000, // PKR 10,000 / month
        'billing_interval' => 'monthly',
        'max_locations' => 3,
        'max_terminals' => 5,
        'max_staff' => 10,
        'max_skus' => 2000,
        'is_active' => true,
    ]);

    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $tenant3 = Tenant::factory()->create();
    $tenant4 = Tenant::factory()->create();
    $tenant5 = Tenant::factory()->create();
    $tenant6 = Tenant::factory()->create();

    // 2 active on Basic, 1 active on Pro => MRR = 2 * 500,000 + 1,000,000 = 2,000,000 minor units
    Subscription::create([
        'tenant_id' => $tenant1->id,
        'plan_id' => $planBasic->id,
        'status' => 'active',
        'starts_at' => Carbon::now()->subMonths(2),
    ]);

    Subscription::create([
        'tenant_id' => $tenant2->id,
        'plan_id' => $planBasic->id,
        'status' => 'active',
        'starts_at' => Carbon::now()->subMonths(1),
    ]);

    Subscription::create([
        'tenant_id' => $tenant3->id,
        'plan_id' => $planPro->id,
        'status' => 'active',
        'starts_at' => Carbon::now()->subMonths(3),
    ]);

    // 1 trialing
    Subscription::create([
        'tenant_id' => $tenant4->id,
        'plan_id' => $planBasic->id,
        'status' => 'trialing',
        'starts_at' => Carbon::now()->subDays(5),
        'trial_ends_at' => Carbon::now()->addDays(9),
    ]);

    // 1 past_due
    Subscription::create([
        'tenant_id' => $tenant5->id,
        'plan_id' => $planPro->id,
        'status' => 'past_due',
        'starts_at' => Carbon::now()->subMonths(1),
    ]);

    // 1 canceled this month => Churn calculation base: 3 active + 1 canceled = 4 => 1/4 = 25.0%
    $subCanceled = Subscription::create([
        'tenant_id' => $tenant6->id,
        'plan_id' => $planBasic->id,
        'status' => 'canceled',
        'starts_at' => Carbon::now()->subMonths(4),
    ]);
    $subCanceled->updated_at = Carbon::now()->startOfMonth()->addDays(2);
    $subCanceled->save();

    $response = $this->actingAs($this->superAdmin)->get('/superadmin/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Dashboard')
            ->has('saas_metrics')
            ->where('saas_metrics.mrr_minor', 2000000)
            ->where('saas_metrics.arr_minor', 24000000)
            ->where('saas_metrics.active_subscriptions_count', 3)
            ->where('saas_metrics.trialing_subscriptions_count', 1)
            ->where('saas_metrics.past_due_subscriptions_count', 1)
            ->where('saas_metrics.churn_rate_pct', fn ($rate) => (float) $rate === 25.0)
            ->where('saas_metrics.arpa_minor', 666667)
        );
});

test('super admin can view security terminal with ip whitelist rules and detected client ip', function () {
    SuperAdminIpWhitelist::create([
        'ip_address' => '110.38.10.12',
        'label' => 'Lahore NOC Office',
        'notes' => 'Dedicated static fiber line',
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin)->get('/superadmin/security');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Security/Index')
            ->has('ip_whitelists', 1)
            ->where('ip_whitelists.0.ip_address', '110.38.10.12')
            ->where('ip_whitelists.0.label', 'Lahore NOC Office')
            ->has('current_ip')
        );
});

test('super admin can add a new whitelisted IP address and CIDR subnet', function () {
    $response = $this->actingAs($this->superAdmin)->post('/superadmin/security/ip-whitelist', [
        'ip_address' => '202.163.110.5',
        'label' => 'Karachi Executive Branch',
        'notes' => 'Head of operations static IP',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('super_admin_ip_whitelists', [
        'ip_address' => '202.163.110.5',
        'label' => 'Karachi Executive Branch',
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    // Check CIDR subnet support
    $responseSubnet = $this->actingAs($this->superAdmin)->post('/superadmin/security/ip-whitelist', [
        'ip_address' => '10.0.0.0/24',
        'label' => 'Internal Corporate VPN',
        'notes' => 'WireGuard subnet',
    ]);

    $responseSubnet->assertRedirect();
    $this->assertDatabaseHas('super_admin_ip_whitelists', [
        'ip_address' => '10.0.0.0/24',
        'label' => 'Internal Corporate VPN',
    ]);
});

test('super admin cannot add duplicate ip address to whitelist', function () {
    SuperAdminIpWhitelist::create([
        'ip_address' => '115.186.150.1',
        'label' => 'Islamabad Office',
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin)->post('/superadmin/security/ip-whitelist', [
        'ip_address' => '115.186.150.1',
        'label' => 'Duplicate Entry',
    ]);

    $response->assertSessionHasErrors(['ip_address']);
});

test('super admin can toggle whitelist rule active status', function () {
    $rule = SuperAdminIpWhitelist::create([
        'ip_address' => '110.38.10.12',
        'label' => 'Lahore NOC',
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    // Toggle to inactive
    $response = $this->actingAs($this->superAdmin)->post("/superadmin/security/ip-whitelist/{$rule->id}/toggle");
    $response->assertRedirect();
    expect($rule->fresh()->is_active)->toBeFalse();

    // Toggle back to active
    $this->actingAs($this->superAdmin)->post("/superadmin/security/ip-whitelist/{$rule->id}/toggle");
    expect($rule->fresh()->is_active)->toBeTrue();
});

test('super admin can delete a whitelist rule', function () {
    $rule = SuperAdminIpWhitelist::create([
        'ip_address' => '110.38.10.12',
        'label' => 'Decommissioned Office',
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin)->delete("/superadmin/security/ip-whitelist/{$rule->id}");
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('super_admin_ip_whitelists', [
        'id' => $rule->id,
    ]);
});

test('middleware allows access in open mode when no active rules exist', function () {
    expect(SuperAdminIpWhitelist::where('is_active', true)->count())->toBe(0);

    $response = $this->actingAs($this->superAdmin)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.195'])
        ->get('/superadmin/dashboard');

    $response->assertStatus(200);
});

test('middleware blocks non-whitelisted IP when active whitelist rules exist', function () {
    SuperAdminIpWhitelist::create([
        'ip_address' => '198.51.100.10',
        'label' => 'Designated Security NOC',
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    $unauthorizedIp = '203.0.113.50';

    $response = $this->actingAs($this->superAdmin)
        ->withServerVariables(['REMOTE_ADDR' => $unauthorizedIp])
        ->get('/superadmin/dashboard');

    $response->assertStatus(403);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'superadmin_ip_blocked',
        'ip_address' => $unauthorizedIp,
    ]);
});

test('middleware allows access when client IP matches whitelisted entry', function () {
    SuperAdminIpWhitelist::create([
        'ip_address' => '203.0.113.50',
        'label' => 'Approved SecOps Laptop',
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->get('/superadmin/dashboard');

    $response->assertStatus(200);
});

test('middleware allows access when client IP falls within whitelisted CIDR range', function () {
    SuperAdminIpWhitelist::create([
        'ip_address' => '10.50.0.0/16',
        'label' => 'Private Corporate Subnet',
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    // IP inside subnet: allowed
    $responseAllowed = $this->actingAs($this->superAdmin)
        ->withServerVariables(['REMOTE_ADDR' => '10.50.12.34'])
        ->get('/superadmin/dashboard');

    $responseAllowed->assertStatus(200);

    // IP outside subnet: blocked
    $responseBlocked = $this->actingAs($this->superAdmin)
        ->withServerVariables(['REMOTE_ADDR' => '10.51.1.1'])
        ->get('/superadmin/dashboard');

    $responseBlocked->assertStatus(403);
});

test('middleware ignores disabled rules and returns to open mode if all rules are inactive', function () {
    SuperAdminIpWhitelist::create([
        'ip_address' => '198.51.100.10',
        'label' => 'Temporarily Deactivated Rule',
        'is_active' => false,
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
        ->get('/superadmin/dashboard');

    $response->assertStatus(200);
});
