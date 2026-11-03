<?php

use App\Models\AuditLog;
use App\Models\FbrHealthCheck;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Retail Mart',
        'ntn' => '4443332-1',
        'province' => 'punjab',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Metro Main Outlet',
        'province' => 'punjab',
    ]);
});

test('super admin can view compliance overview with fiscalization metrics', function () {
    // Create submitted, failed, and pending sales
    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->owner->id,
        'sale_number' => 'SAL-SUB-01',
        'payment_method' => 'cash',
        'subtotal_minor' => 10000,
        'tax_minor' => 1800,
        'total_minor' => 11800,
        'status' => 'completed',
        'fbr_status' => 'submitted',
        'fbr_usin' => 'USIN-1001-99',
    ]);

    Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->owner->id,
        'sale_number' => 'SAL-FAIL-01',
        'payment_method' => 'card',
        'subtotal_minor' => 5000,
        'tax_minor' => 900,
        'total_minor' => 5900,
        'status' => 'completed',
        'fbr_status' => 'failed',
        'fbr_error' => 'Invalid Bearer Token or Expired Certificate',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/compliance/fbr');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Compliance/Overview')
            ->where('metrics.submitted_count', 1)
            ->where('metrics.failed_count', 1)
            ->where('metrics.success_rate', fn ($v) => (float) $v === 50.0)
            ->has('error_breakdown')
        );
});

test('super admin can view failed invoices dead-letter queue and filter by tenant', function () {
    $failedSale = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->owner->id,
        'sale_number' => 'SAL-DLQ-100',
        'payment_method' => 'cash',
        'subtotal_minor' => 20000,
        'tax_minor' => 3600,
        'total_minor' => 23600,
        'status' => 'completed',
        'fbr_status' => 'failed',
        'fbr_error' => 'FBR Gateway Timeout after 5000ms',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get("/superadmin/compliance/fbr/failed?tenant_id={$this->tenant->id}");

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Compliance/FailedInvoices')
            ->has('sales.data', 1)
            ->where('sales.data.0.sale_number', 'SAL-DLQ-100')
            ->where('sales.data.0.fbr_error', 'FBR Gateway Timeout after 5000ms')
        );
});

test('super admin can queue a single failed invoice for retry which updates status and logs audit entry', function () {
    $failedSale = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->owner->id,
        'sale_number' => 'SAL-RETRY-01',
        'payment_method' => 'cash',
        'subtotal_minor' => 15000,
        'tax_minor' => 2700,
        'total_minor' => 17700,
        'status' => 'completed',
        'fbr_status' => 'failed',
        'fbr_error' => 'Temporary Network Interruption',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/compliance/fbr/retry/{$failedSale->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $failedSale->refresh();
    expect($failedSale->fbr_status)->toBe('pending');
    expect($failedSale->fbr_error)->toBeNull();

    // Verify audit log
    $audit = AuditLog::withoutGlobalScopes()
        ->where('event', 'fbr_retry_queued')
        ->where('auditable_id', $failedSale->id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->tenant_id)->toBe($this->tenant->id);
    expect($audit->old_values['fbr_status'])->toBe('failed');
    expect($audit->new_values['fbr_status'])->toBe('pending');
});

test('super admin can bulk retry all failed sales for a tenant', function () {
    for ($i = 1; $i <= 3; $i++) {
        Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'user_id' => $this->owner->id,
            'sale_number' => "SAL-BULK-0{$i}",
            'payment_method' => 'cash',
            'subtotal_minor' => 10000,
            'tax_minor' => 1800,
            'total_minor' => 11800,
            'status' => 'completed',
            'fbr_status' => 'failed',
            'fbr_error' => 'Batch connection drop',
        ]);
    }

    $response = $this->actingAs($this->superAdmin)
        ->post('/superadmin/compliance/fbr/bulk-retry', [
            'tenant_id' => $this->tenant->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $remainingFailed = Sale::withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', $this->tenant->id)
        ->where('fbr_status', 'failed')
        ->count();

    expect($remainingFailed)->toBe(0);

    $pendingCount = Sale::withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', $this->tenant->id)
        ->where('fbr_status', 'pending')
        ->count();

    expect($pendingCount)->toBe(3);
});

test('super admin can track 24h provisional receipts and filter by urgency', function () {
    // 1. Overdue sale (> 24 hours ago)
    $overdueSale = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->owner->id,
        'sale_number' => 'SAL-OVERDUE-01',
        'payment_method' => 'cash',
        'subtotal_minor' => 10000,
        'tax_minor' => 1800,
        'total_minor' => 11800,
        'status' => 'completed',
        'fbr_status' => 'offline',
    ]);
    \Illuminate\Support\Facades\DB::table('sales')
        ->where('id', $overdueSale->id)
        ->update(['created_at' => now()->subHours(26)]);

    // 2. Fresh sale (2 hours ago)
    $freshSale = Sale::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'user_id' => $this->owner->id,
        'sale_number' => 'SAL-FRESH-01',
        'payment_method' => 'cash',
        'subtotal_minor' => 10000,
        'tax_minor' => 1800,
        'total_minor' => 11800,
        'status' => 'completed',
        'fbr_status' => 'offline',
        'created_at' => now()->subHours(2),
    ]);

    // Filter by overdue
    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/compliance/fbr/provisional?urgency=overdue');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Compliance/ProvisionalReceipts')
            ->has('sales.data', 1)
            ->where('sales.data.0.sale_number', 'SAL-OVERDUE-01')
            ->where('sales.data.0.urgency_status', 'overdue')
        );
});

test('super admin can trigger FBR endpoint live ping and records health check entry', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post('/superadmin/compliance/fbr/ping');

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $check = FbrHealthCheck::latest('created_at')->first();
    expect($check)->not->toBeNull();
    expect($check->latency_ms)->toBeGreaterThanOrEqual(0);
    expect($check->status)->toBeIn(['operational', 'degraded', 'offline']);
});

test('tenant owner receives 403 on compliance center routes', function () {
    $this->actingAs($this->owner)
        ->get('/superadmin/compliance/fbr')
        ->assertStatus(403);

    $this->actingAs($this->owner)
        ->get('/superadmin/compliance/fbr/failed')
        ->assertStatus(403);

    $this->actingAs($this->owner)
        ->get('/superadmin/compliance/fbr/provisional')
        ->assertStatus(403);
});
