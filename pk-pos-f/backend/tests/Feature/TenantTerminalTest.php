<?php

use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Al-Madina Hypermarket',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Gulberg Branch',
    ]);
});

test('tenant owner can view own terminal counters directory', function () {
    PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-001',
        'name' => 'Counter 1',
        'pairing_status' => 'paired',
        'last_heartbeat_at' => now(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->get('/terminals');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Terminals/Index')
            ->has('terminals', 1)
            ->where('terminals.0.terminal_code', 'POS-001')
            ->where('terminals.0.is_online', true)
        );
});

test('tenant owner can generate a new pairing code for counter', function () {
    $response = $this->actingAs($this->owner)
        ->post('/terminals/pairing-code', [
            'terminal_code' => 'POS-002',
            'name' => 'Counter 2 - Express',
            'location_id' => $this->location->id,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('pos_terminals', [
        'tenant_id' => $this->tenant->id,
        'terminal_code' => 'POS-002',
        'pairing_status' => 'pending_pairing',
    ]);
});

test('tenant owner cannot view terminals of another merchant', function () {
    $otherTenant = Tenant::factory()->create([
        'name' => 'Other Merchant',
    ]);

    PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'terminal_code' => 'POS-OTHER',
        'name' => 'Other Counter',
        'pairing_status' => 'paired',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->get('/terminals');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Terminals/Index')
            ->has('terminals', 0)
        );
});
