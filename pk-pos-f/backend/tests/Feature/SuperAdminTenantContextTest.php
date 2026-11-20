<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Al-Shifa Medicos & General Store',
        'suspended_at' => null,
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Branch',
    ]);

    // Super Admin has tenant_id = null and super_admin = true
    $this->superAdmin = User::factory()->create([
        'tenant_id' => null,
        'name' => 'Platform Super Admin',
        'email' => 'superadmin@pos.pk',
        'super_admin' => true,
        'role' => 'owner',
        'staff_role' => 'super_admin',
    ]);
});

test('super admin can access pharmacy vertical routes without null tenant errors', function () {
    // Pharmacy Batches
    $response = $this->actingAs($this->superAdmin)
        ->get('/pharmacy/batches');
    $response->assertStatus(200);

    // Pharmacy Controlled Substances & Narcotics Register
    $response = $this->actingAs($this->superAdmin)
        ->get('/pharmacy/narcotics');
    $response->assertStatus(200);

    // Pharmacy Prescriptions
    $response = $this->actingAs($this->superAdmin)
        ->get('/pharmacy/prescriptions');
    $response->assertStatus(200);
});

test('super admin can access restaurant vertical routes without null tenant errors', function () {
    // Restaurant Dining Tables & Floors
    $response = $this->actingAs($this->superAdmin)
        ->get('/restaurant/tables');
    $response->assertStatus(200);

    // Restaurant Kitchen Stations
    $response = $this->actingAs($this->superAdmin)
        ->get('/restaurant/stations');
    $response->assertStatus(200);

    // Restaurant Modifiers
    $response = $this->actingAs($this->superAdmin)
        ->get('/restaurant/modifiers');
    $response->assertStatus(200);

    // Restaurant Kitchen Display System (KDS)
    $response = $this->actingAs($this->superAdmin)
        ->get('/restaurant/kds');
    $response->assertStatus(200);
});

test('super admin can access hardware vertical routes and settings without null tenant errors', function () {
    // Hardware Contractor Projects
    $response = $this->actingAs($this->superAdmin)
        ->get('/hardware/projects');
    $response->assertStatus(200);

    // Hardware Wholesale Price Slabs
    $response = $this->actingAs($this->superAdmin)
        ->get('/hardware/tiers');
    $response->assertStatus(200);

    // Hardware Delivery Challans & Gate Passes
    $response = $this->actingAs($this->superAdmin)
        ->get('/hardware/challans');
    $response->assertStatus(200);

    // Receipt Template Customizer
    $response = $this->actingAs($this->superAdmin)
        ->get('/settings/receipt-template');
    $response->assertStatus(200);

    // Accounting Chart of Accounts
    $response = $this->actingAs($this->superAdmin)
        ->get('/accounts');
    $response->assertStatus(200);

    // Terminals Management
    $response = $this->actingAs($this->superAdmin)
        ->get('/terminals');
    $response->assertStatus(200);
});
