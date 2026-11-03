<?php

use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'name' => 'CEO Admin',
        'email' => 'ceo@pkpos.pk',
        'staff_role' => 'super_admin',
        'is_active' => true,
    ]);

    $this->tenantPunjab = Tenant::factory()->create([
        'name' => 'Lahore Hypermarket',
        'province' => 'punjab',
    ]);

    $this->tenantSindh = Tenant::factory()->create([
        'name' => 'Karachi Mega Store',
        'province' => 'sindh',
    ]);

    $this->ownerPunjab = User::factory()->create([
        'tenant_id' => $this->tenantPunjab->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->ownerSindh = User::factory()->create([
        'tenant_id' => $this->tenantSindh->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('non-super admin cannot access executive analytics dashboard', function () {
    $this->actingAs($this->ownerPunjab)
        ->get(route('superadmin.dashboard'))
        ->assertForbidden();
});

test('super admin can view executive analytics dashboard with full telemetry and GMV metrics', function () {
    $locationPunjab = Location::factory()->create(['tenant_id' => $this->tenantPunjab->id]);
    $locationSindh = Location::factory()->create(['tenant_id' => $this->tenantSindh->id]);

    // Create sales for Punjab
    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantPunjab->id,
        'location_id' => $locationPunjab->id,
        'user_id' => $this->ownerPunjab->id,
        'sale_number' => 'SAL-LHR-001',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 100000,
        'tax_minor' => 18000,
        'total_minor' => 118000,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    // Create sales for Sindh
    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantSindh->id,
        'location_id' => $locationSindh->id,
        'user_id' => $this->ownerSindh->id,
        'sale_number' => 'SAL-KHI-001',
        'payment_method' => 'card',
        'currency' => 'PKR',
        'subtotal_minor' => 50000,
        'tax_minor' => 7500,
        'total_minor' => 57500,
        'status' => 'completed',
        'fbr_status' => 'failed',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('superadmin.dashboard'));

    $response->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('SuperAdmin/Dashboard')
                // Backward-compatible metrics expected by SuperAdminTest
                ->where('metrics.total_tenants', 2)
                ->where('metrics.active_tenants', 2)
                ->where('metrics.total_sales_volume', 175500)
                ->where('metrics.fbr_submissions_today', 1)
                // Advanced Module 11 executive metrics
                ->where('metrics.all_time_gmv', 175500)
                ->where('metrics.total_sales_count', 2)
                ->where('metrics.failed_invoices_count', 1)
                ->where('metrics.fbr_compliance_rate', 50)
                ->has('daily_gmv', 14)
                ->has('province_distribution')
                ->has('top_merchants')
                ->has('recent_tenants')
        );
});

test('fbr compliance rate visualizer accurately computes clean submission percentage', function () {
    $location = Location::factory()->create(['tenant_id' => $this->tenantPunjab->id]);

    // 3 submitted sales, 1 failed sale = 75.0% compliance
    for ($i = 1; $i <= 3; $i++) {
        Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantPunjab->id,
            'location_id' => $location->id,
            'user_id' => $this->ownerPunjab->id,
            'sale_number' => "SUB-00{$i}",
            'payment_method' => 'cash',
            'currency' => 'PKR',
            'subtotal_minor' => 10000,
            'tax_minor' => 1800,
            'total_minor' => 11800,
            'status' => 'completed',
            'fbr_status' => 'submitted',
        ]);
    }

    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantPunjab->id,
        'location_id' => $location->id,
        'user_id' => $this->ownerPunjab->id,
        'sale_number' => "FAIL-001",
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 10000,
        'tax_minor' => 1800,
        'total_minor' => 11800,
        'status' => 'completed',
        'fbr_status' => 'failed',
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.dashboard'))
        ->assertInertia(
            fn(Assert $page) => $page
                ->where('fbr_breakdown.submitted', 3)
                ->where('fbr_breakdown.failed', 1)
                ->where('fbr_breakdown.compliance_rate', 75)
        );
});

test('top merchants ranking correctly prioritizes highest GMV retail organizations', function () {
    $locationPunjab = Location::factory()->create(['tenant_id' => $this->tenantPunjab->id]);
    $locationSindh = Location::factory()->create(['tenant_id' => $this->tenantSindh->id]);

    // Punjab: 50,000 minor
    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantPunjab->id,
        'location_id' => $locationPunjab->id,
        'user_id' => $this->ownerPunjab->id,
        'sale_number' => 'SAL-LOW',
        'payment_method' => 'cash',
        'currency' => 'PKR',
        'subtotal_minor' => 50000,
        'tax_minor' => 9000,
        'total_minor' => 59000,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    // Sindh: 200,000 minor (Higher volume)
    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantSindh->id,
        'location_id' => $locationSindh->id,
        'user_id' => $this->ownerSindh->id,
        'sale_number' => 'SAL-HIGH',
        'payment_method' => 'card',
        'currency' => 'PKR',
        'subtotal_minor' => 200000,
        'tax_minor' => 30000,
        'total_minor' => 230000,
        'status' => 'completed',
        'fbr_status' => 'submitted',
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.dashboard'))
        ->assertInertia(
            fn(Assert $page) => $page
                ->where('top_merchants.0.name', 'Karachi Mega Store')
                ->where('top_merchants.0.total_volume', 230000)
                ->where('top_merchants.1.name', 'Lahore Hypermarket')
                ->where('top_merchants.1.total_volume', 59000)
        );
});
