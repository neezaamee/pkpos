<?php

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Hypermarket',
        'province' => 'punjab',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Gulberg Branch',
        'province' => 'punjab',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('non-super admin cannot access superadmin terminals dashboard', function () {
    $this->actingAs($this->owner)
        ->get(route('superadmin.terminals.index'))
        ->assertForbidden();
});

test('super admin can view terminals fleet dashboard with metrics', function () {
    // 1 online terminal
    PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-001',
        'name' => 'Counter 1',
        'pairing_status' => 'paired',
        'is_active' => true,
        'last_heartbeat_at' => now()->subMinutes(2),
    ]);

    // 1 revoked terminal
    PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-002',
        'name' => 'Counter 2',
        'pairing_status' => 'revoked',
        'is_active' => false,
        'revocation_reason' => 'Damaged motherboard',
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.terminals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Terminals/Index')
            ->has('terminals.data', 2)
            ->where('metrics.total', 2)
            ->where('metrics.online', 1)
            ->where('metrics.revoked', 1)
        );
});

test('super admin can generate a pairing code for a tenant terminal', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.terminals.pairing-code'), [
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'terminal_code' => 'REG-MAIN-01',
            'name' => 'Main Express Checkout',
        ])
        ->assertRedirect();

    $terminal = PosTerminal::withoutGlobalScopes()
        ->where('terminal_code', 'REG-MAIN-01')
        ->first();

    expect($terminal)->not->toBeNull();
    expect($terminal->pairing_status)->toBe('pending_pairing');
    expect($terminal->pairing_token)->toHaveLength(8);
    expect($terminal->is_active)->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'terminal_pairing_code_generated',
        'auditable_type' => PosTerminal::class,
    ]);
});

test('super admin can update terminal metadata', function () {
    $terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-OLD-01',
        'name' => 'Old Counter',
        'pairing_status' => 'paired',
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->put(route('superadmin.terminals.update', $terminal->id), [
            'terminal_code' => 'POS-NEW-01',
            'name' => 'Updated Counter Name',
            'location_id' => $this->location->id,
        ])
        ->assertRedirect();

    expect($terminal->fresh()->terminal_code)->toBe('POS-NEW-01')
        ->and($terminal->fresh()->name)->toBe('Updated Counter Name');
});

test('super admin can remotely revoke a terminal with reason', function () {
    $terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-REVOKE-01',
        'name' => 'Compromised Counter',
        'pairing_status' => 'paired',
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.terminals.revoke', $terminal->id), [
            'reason' => 'Reported stolen from branch',
        ])
        ->assertRedirect();

    $fresh = $terminal->fresh();
    expect($fresh->is_active)->toBeFalse();
    expect($fresh->pairing_status)->toBe('revoked');
    expect($fresh->revocation_reason)->toBe('Reported stolen from branch');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'terminal_revoked',
        'auditable_type' => PosTerminal::class,
    ]);
});

test('super admin can reactivate a revoked terminal', function () {
    $terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-REACT-01',
        'name' => 'Replaced Register',
        'pairing_status' => 'revoked',
        'is_active' => false,
        'revocation_reason' => 'Hardware maintenance',
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.terminals.reactivate', $terminal->id))
        ->assertRedirect();

    $fresh = $terminal->fresh();
    expect($fresh->is_active)->toBeTrue();
    expect($fresh->pairing_status)->toBe('paired');
    expect($fresh->revocation_reason)->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'terminal_reactivated',
        'auditable_type' => PosTerminal::class,
    ]);
});

test('super admin can delete a terminal record', function () {
    $terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-DEL-01',
        'name' => 'Disposable Register',
        'pairing_status' => 'paired',
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.terminals.destroy', $terminal->id))
        ->assertRedirect();

    expect(PosTerminal::withoutGlobalScopes()->find($terminal->id))->toBeNull();
});

test('client API: pairing with valid code registers hardware and returns api token', function () {
    $terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-PAIR-01',
        'name' => 'Counter Register',
        'pairing_token' => 'ABC12345',
        'pairing_token_expires_at' => now()->addHours(24),
        'pairing_status' => 'pending_pairing',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/terminals/pair', [
        'pairing_token' => 'ABC12345',
        'hardware_uuid' => 'HW-UUID-TEST-9988',
        'hostname' => 'DESKTOP-POS-STORE1',
        'platform_os' => 'windows',
        'app_version' => '0.2.1',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'terminal_id',
            'terminal_code',
            'name',
            'tenant_id',
            'tenant_name',
            'token',
            'fbr_pos_id',
        ]);

    $fresh = $terminal->fresh();
    expect($fresh->pairing_status)->toBe('paired');
    expect($fresh->hardware_uuid)->toBe('HW-UUID-TEST-9988');
    expect($fresh->hostname)->toBe('DESKTOP-POS-STORE1');
    expect($fresh->pairing_token)->toBeNull();
    expect($fresh->last_heartbeat_at)->not->toBeNull();
});

test('client API: pairing with invalid or expired code returns 422', function () {
    $this->postJson('/api/v1/terminals/pair', [
        'pairing_token' => 'INVALID9',
        'hardware_uuid' => 'HW-UUID-999',
    ])->assertStatus(422);

    $expired = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-EXP-01',
        'name' => 'Expired Counter',
        'pairing_token' => 'EXPIRED1',
        'pairing_token_expires_at' => now()->subDay(),
        'pairing_status' => 'pending_pairing',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/terminals/pair', [
        'pairing_token' => 'EXPIRED1',
        'hardware_uuid' => 'HW-UUID-999',
    ])->assertStatus(422);
});

test('client API: heartbeat updates last_heartbeat_at and app_version', function () {
    $terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-HB-01',
        'name' => 'Heartbeat Register',
        'hardware_uuid' => 'HW-LIVE-01',
        'pairing_status' => 'paired',
        'is_active' => true,
        'app_version' => '0.1.0',
        'last_heartbeat_at' => now()->subHours(2),
    ]);

    $response = $this->postJson('/api/v1/terminals/heartbeat', [
        'hardware_uuid' => 'HW-LIVE-01',
        'app_version' => '0.2.1',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'healthy',
            'terminal_code' => 'POS-HB-01',
            'is_active' => true,
        ]);

    $fresh = $terminal->fresh();
    expect($fresh->app_version)->toBe('0.2.1')
        ->and($fresh->isOnline())->toBeTrue();
});

test('client API: heartbeat by terminal_code updates last_heartbeat_at', function () {
    $terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-CODE-99',
        'name' => 'Code Register',
        'hardware_uuid' => 'HW-CODE-99',
        'pairing_status' => 'paired',
        'is_active' => true,
        'app_version' => '0.1.0',
        'last_heartbeat_at' => now()->subHours(5),
    ]);

    $response = $this->postJson('/api/v1/terminals/heartbeat', [
        'terminal_code' => 'POS-CODE-99',
        'app_version' => '0.1.5',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'healthy',
            'terminal_code' => 'POS-CODE-99',
            'is_active' => true,
        ]);

    $fresh = $terminal->fresh();
    expect($fresh->isOnline())->toBeTrue();
});

test('client API: heartbeat on revoked terminal returns 403 with revocation reason', function () {
    $terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-HB-REVOKED',
        'name' => 'Revoked Register',
        'hardware_uuid' => 'HW-REVOKED-01',
        'pairing_status' => 'revoked',
        'is_active' => false,
        'revocation_reason' => 'Terminated franchise agreement',
    ]);

    $response = $this->postJson('/api/v1/terminals/heartbeat', [
        'hardware_uuid' => 'HW-REVOKED-01',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Terminal revoked',
            'reason' => 'Terminated franchise agreement',
        ]);
});

