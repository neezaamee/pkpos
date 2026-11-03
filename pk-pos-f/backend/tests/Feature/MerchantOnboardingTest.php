<?php

use App\Models\AuditLog;
use App\Models\FbrCredential;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
        'staff_role' => 'super_admin',
    ]);

    $this->complianceAuditor = User::factory()->superAdmin()->create([
        'email' => 'auditor@example.com',
        'staff_role' => 'compliance_auditor',
    ]);

    $this->regularUser = User::factory()->create([
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->plan = Plan::create([
        'code' => 'retail-pro',
        'name' => 'Retail Pro Tier',
        'price_minor' => 800000,
        'billing_interval' => 'monthly',
        'max_locations' => 3,
        'max_terminals' => 6,
        'is_active' => true,
    ]);
});

test('public visitor can view merchant onboarding registration page', function () {
    $this->get(route('apply'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Apply')
            ->has('plans')
        );
});

test('public merchant can submit self-registration application which auto-checks ATL', function () {
    $payload = [
        'business_name' => 'Shaheen Pharmacy Network',
        'trade_name' => 'Shaheen Chemist',
        'sector' => 'pharmacy',
        'province' => 'punjab',
        'city' => 'Rawalpindi',
        'address' => 'Saddar Commercial Market',
        'contact_name' => 'Dr. Shaheen Akhtar',
        'contact_email' => 'contact@shaheenchemist.pk',
        'contact_phone' => '0300-5551234',
        'ntn' => '7281934-2',
        'strn' => '07-01-7281-934-11',
        'pos_terminal_count' => 4,
        'requested_plan_id' => $this->plan->id,
    ];

    $response = $this->post(route('apply.store'), $payload);

    $response->assertRedirect(route('apply'));

    $this->assertDatabaseHas('tenant_applications', [
        'business_name' => 'Shaheen Pharmacy Network',
        'contact_email' => 'contact@shaheenchemist.pk',
        'province' => 'punjab',
        'is_atl_active' => true,
        'status' => 'pending_review',
    ]);

    $app = TenantApplication::where('contact_email', 'contact@shaheenchemist.pk')->first();
    expect($app->application_number)->toStartWith('APP-');
    expect($app->atl_taxpayer_name)->toContain('Shaheen Pharmacy Network');
});

test('validation fails when required application fields are missing', function () {
    $response = $this->post(route('apply.store'), []);

    $response->assertSessionHasErrors([
        'business_name', 'sector', 'province', 'city', 'contact_name', 'contact_email', 'contact_phone',
    ]);
});

test('non-staff user cannot access onboarding queue', function () {
    $this->actingAs($this->regularUser)
        ->get(route('superadmin.onboarding.index'))
        ->assertForbidden();
});

test('super admin and compliance auditor can view onboarding queue with metrics', function () {
    TenantApplication::create([
        'application_number' => 'APP-2026-00001',
        'business_name' => 'Chase Up Shopping Mall',
        'sector' => 'supermarket',
        'province' => 'sindh',
        'city' => 'Karachi',
        'contact_name' => 'Farhan Qureshi',
        'contact_email' => 'farhan@chaseup.com.pk',
        'contact_phone' => '0321-9876543',
        'is_atl_active' => true,
        'status' => 'pending_review',
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.onboarding.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Onboarding/Index')
            ->has('applications.data', 1)
            ->where('metrics.pending_count', 1)
            ->where('metrics.atl_active_count', 1)
        );

    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.onboarding.index'))
        ->assertOk();
});

test('super admin can 1-click approve and provision complete multi-tenant environment with audit log', function () {
    $app = TenantApplication::create([
        'application_number' => 'APP-2026-00002',
        'business_name' => 'Jalal Sons Supermarket',
        'sector' => 'supermarket',
        'province' => 'punjab',
        'city' => 'Lahore',
        'address' => 'Gulberg Main Boulevard',
        'contact_name' => 'Mian Jalal',
        'contact_email' => 'jalal@jalalsons.com.pk',
        'contact_phone' => '0300-8451122',
        'ntn' => '3928172-5',
        'strn' => '03-02-3928-172-19',
        'requested_plan_id' => $this->plan->id,
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.onboarding.approve', $app->id), [
            'plan_id' => $this->plan->id,
            'temp_password' => 'SecurePass123!',
            'internal_notes' => 'Verified with SECP and FBR ATL list.',
        ]);

    $response->assertRedirect(route('superadmin.onboarding.show', $app->id));

    // Verify application state
    $app->refresh();
    expect($app->status)->toBe('approved');
    expect($app->provisioned_tenant_id)->not->toBeNull();
    expect($app->reviewed_by)->toBe($this->superAdmin->id);

    // Verify Tenant created
    $tenant = Tenant::find($app->provisioned_tenant_id);
    expect($tenant)->not->toBeNull();
    expect($tenant->name)->toBe('Jalal Sons Supermarket');
    expect($tenant->province)->toBe('punjab');
    expect($tenant->ntn)->toBe('3928172-5');

    // Verify Owner User created
    $user = User::where('tenant_id', $tenant->id)->where('role', 'owner')->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('jalal@jalalsons.com.pk');

    // Verify Default Location created
    $location = Location::where('tenant_id', $tenant->id)->first();
    expect($location)->not->toBeNull();
    expect($location->name)->toBe('Main Head Branch');

    // Verify Default FBR Credentials created
    $fbr = FbrCredential::where('tenant_id', $tenant->id)->first();
    expect($fbr)->not->toBeNull();
    expect($fbr->pos_id)->toBe($tenant->fbr_pos_id);

    // Verify Subscription created
    $subscription = Subscription::where('tenant_id', $tenant->id)->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe('active');
    expect($subscription->plan_id)->toBe($this->plan->id);

    // Verify AuditLog
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'merchant_application_approved_and_provisioned',
        'auditable_type' => TenantApplication::class,
        'auditable_id' => $app->id,
    ]);
});

test('super admin can reject application with reason and audit log', function () {
    $app = TenantApplication::create([
        'application_number' => 'APP-2026-00003',
        'business_name' => 'Suspicious Retail Traders',
        'sector' => 'general_retail',
        'province' => 'punjab',
        'city' => 'Multan',
        'contact_name' => 'Unknown Person',
        'contact_email' => 'fake@example.com',
        'contact_phone' => '0300-0000000',
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.onboarding.reject', $app->id), [
            'rejection_reason' => 'Invalid identity documents and fraudulent contact details.',
        ]);

    $response->assertRedirect();

    $app->refresh();
    expect($app->status)->toBe('rejected');
    expect($app->rejection_reason)->toBe('Invalid identity documents and fraudulent contact details.');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'merchant_application_rejected',
        'auditable_type' => TenantApplication::class,
        'auditable_id' => $app->id,
    ]);
});

