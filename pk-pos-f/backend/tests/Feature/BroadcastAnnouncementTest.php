<?php

use App\Models\AuditLog;
use App\Models\BroadcastAnnouncement;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
        'staff_role' => 'super_admin',
    ]);

    $this->supportAgent = User::factory()->superAdmin()->create([
        'email' => 'support@example.com',
        'staff_role' => 'support_agent',
    ]);

    $this->regularUser = User::factory()->create([
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Cash & Carry',
        'province' => 'punjab',
    ]);
});

test('non-staff user cannot access superadmin broadcasts index', function () {
    $this->actingAs($this->regularUser)
        ->get(route('superadmin.broadcasts.index'))
        ->assertForbidden();
});

test('super admin and staff can view broadcasts index with metrics', function () {
    BroadcastAnnouncement::create([
        'title' => 'FBR Iris Gateway Scheduled Downtime',
        'message' => 'FBR Iris scheduled maintenance window between 02:00 AM and 04:00 AM.',
        'level' => 'warning',
        'target_audience' => 'all',
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.broadcasts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Broadcasts/Index')
            ->has('broadcasts.data', 1)
            ->where('metrics.total_broadcasts', 1)
            ->where('metrics.active_live', 1)
            ->where('metrics.critical_emergency', 0)
        );

    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.broadcasts.index'))
        ->assertOk();
});

test('super admin can publish a critical emergency broadcast and it writes audit log', function () {
    $payload = [
        'title' => 'Critical: SRO Tax Rate Emergency Advisory',
        'message' => 'Please sync tax templates immediately to comply with newly notified provincial sales tax amendments.',
        'level' => 'critical_emergency',
        'target_audience' => 'province',
        'target_province' => 'punjab',
        'dismissible' => false,
        'is_active' => true,
    ];

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.broadcasts.store'), $payload);

    $response->assertRedirect();
    $this->assertDatabaseHas('broadcast_announcements', [
        'title' => 'Critical: SRO Tax Rate Emergency Advisory',
        'level' => 'critical_emergency',
        'target_audience' => 'province',
        'target_province' => 'punjab',
        'dismissible' => false,
        'is_active' => true,
    ]);

    $broadcast = BroadcastAnnouncement::where('title', 'Critical: SRO Tax Rate Emergency Advisory')->first();

    // Verify audit log
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => BroadcastAnnouncement::class,
        'auditable_id' => $broadcast->id,
        'event' => 'created',
    ]);
});

test('validation fails when required fields are missing on broadcast creation', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.broadcasts.store'), []);

    $response->assertSessionHasErrors(['title', 'message', 'level', 'target_audience']);
});

test('super admin can update an existing broadcast announcement', function () {
    $broadcast = BroadcastAnnouncement::create([
        'title' => 'Old Title',
        'message' => 'Old message text',
        'level' => 'info',
        'target_audience' => 'all',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->put(route('superadmin.broadcasts.update', $broadcast->id), [
            'title' => 'Updated Title',
            'message' => 'Updated message text here',
            'level' => 'warning',
            'target_audience' => 'desktop_terminals_only',
            'dismissible' => true,
            'is_active' => true,
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('broadcast_announcements', [
        'id' => $broadcast->id,
        'title' => 'Updated Title',
        'level' => 'warning',
        'target_audience' => 'desktop_terminals_only',
    ]);
});

test('super admin can toggle broadcast active status', function () {
    $broadcast = BroadcastAnnouncement::create([
        'title' => 'Test Toggle',
        'message' => 'Testing toggle',
        'level' => 'info',
        'target_audience' => 'all',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.broadcasts.toggle-active', $broadcast->id));

    $response->assertRedirect();
    $this->assertDatabaseHas('broadcast_announcements', [
        'id' => $broadcast->id,
        'is_active' => false,
    ]);

    // Toggle back to true
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.broadcasts.toggle-active', $broadcast->id));

    $this->assertDatabaseHas('broadcast_announcements', [
        'id' => $broadcast->id,
        'is_active' => true,
    ]);
});

test('super admin can delete a broadcast announcement', function () {
    $broadcast = BroadcastAnnouncement::create([
        'title' => 'Temporary Notice',
        'message' => 'To be deleted',
        'level' => 'info',
        'target_audience' => 'all',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.broadcasts.destroy', $broadcast->id));

    $response->assertRedirect();
    $this->assertDatabaseMissing('broadcast_announcements', [
        'id' => $broadcast->id,
    ]);
});

test('client API endpoint returns active announcements matching audience', function () {
    // 1. All merchants active
    BroadcastAnnouncement::create([
        'title' => 'National Holiday Notice',
        'message' => 'Bank clearing holiday tomorrow.',
        'level' => 'info',
        'target_audience' => 'all',
        'is_active' => true,
    ]);

    // 2. Punjab only active
    BroadcastAnnouncement::create([
        'title' => 'PRA Tax Advisory',
        'message' => 'Punjab Revenue Authority portal updates.',
        'level' => 'warning',
        'target_audience' => 'province',
        'target_province' => 'punjab',
        'is_active' => true,
    ]);

    // 3. Sindh only active
    BroadcastAnnouncement::create([
        'title' => 'SRB Advisory',
        'message' => 'Sindh Revenue Board portal updates.',
        'level' => 'warning',
        'target_audience' => 'province',
        'target_province' => 'sindh',
        'is_active' => true,
    ]);

    // 4. Inactive broadcast (should never be returned)
    BroadcastAnnouncement::create([
        'title' => 'Inactive Notice',
        'message' => 'Should not appear.',
        'level' => 'info',
        'target_audience' => 'all',
        'is_active' => false,
    ]);

    // 5. Expired broadcast (should not appear)
    BroadcastAnnouncement::create([
        'title' => 'Expired Notice',
        'message' => 'Should not appear.',
        'level' => 'info',
        'target_audience' => 'all',
        'is_active' => true,
        'expires_at' => now()->subHour(),
    ]);

    // Query for Punjab
    $responsePunjab = $this->getJson('/api/v1/broadcasts/active?province=punjab');
    $responsePunjab->assertOk();
    $dataPunjab = $responsePunjab->json('broadcasts');
    expect(count($dataPunjab))->toBe(2);
    expect(collect($dataPunjab)->pluck('title'))->toContain('National Holiday Notice');
    expect(collect($dataPunjab)->pluck('title'))->toContain('PRA Tax Advisory');
    expect(collect($dataPunjab)->pluck('title'))->not->toContain('SRB Advisory');
    expect(collect($dataPunjab)->pluck('title'))->not->toContain('Inactive Notice');
    expect(collect($dataPunjab)->pluck('title'))->not->toContain('Expired Notice');

    // Query for Sindh
    $responseSindh = $this->getJson('/api/v1/broadcasts/active?province=sindh');
    $responseSindh->assertOk();
    $dataSindh = $responseSindh->json('broadcasts');
    expect(count($dataSindh))->toBe(2);
    expect(collect($dataSindh)->pluck('title'))->toContain('National Holiday Notice');
    expect(collect($dataSindh)->pluck('title'))->toContain('SRB Advisory');
});

