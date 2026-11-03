<?php

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Apex Retailers',
        'ntn' => '1234567-8',
        'strn' => '12-34-5678-901-23',
        'province' => 'punjab',
        'fbr_pos_id' => 'POS-1001',
        'notes' => 'Initial onboarding notes',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Apex Owner',
        'email' => 'owner@apex.test',
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Apex Main Branch',
        'province' => 'punjab',
    ]);
});

test('super admin can view tenant edit page', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get("/superadmin/tenants/{$this->tenant->id}/edit");

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Tenants/Edit')
            ->where('tenant.id', $this->tenant->id)
            ->where('tenant.name', 'Apex Retailers')
            ->where('tenant.notes', 'Initial onboarding notes')
        );
});

test('super admin can update tenant profile details and internal notes', function () {
    $response = $this->actingAs($this->superAdmin)
        ->put("/superadmin/tenants/{$this->tenant->id}", [
            'name' => 'Apex Retailers Pakistan Private Limited',
            'ntn' => '7654321-9',
            'strn' => '99-88-7766-554-32',
            'province' => 'sindh',
            'fbr_pos_id' => 'POS-9999',
            'notes' => 'Updated contract terms for 2026',
        ]);

    $response->assertRedirect("/superadmin/tenants/{$this->tenant->id}");
    $response->assertSessionHas('success');

    $this->tenant->refresh();
    expect($this->tenant->name)->toBe('Apex Retailers Pakistan Private Limited');
    expect($this->tenant->ntn)->toBe('7654321-9');
    expect($this->tenant->province)->toBe('sindh');
    expect($this->tenant->fbr_pos_id)->toBe('POS-9999');
    expect($this->tenant->notes)->toBe('Updated contract terms for 2026');
});

test('super admin can suspend tenant with a specific suspension reason and reactivate', function () {
    // Suspend with custom reason
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/suspend", [
            'suspension_reason' => 'Pending tax audit documentation',
        ]);

    $response->assertRedirect();
    $this->tenant->refresh();
    expect($this->tenant->isSuspended())->toBeTrue();
    expect($this->tenant->suspension_reason)->toBe('Pending tax audit documentation');

    // Reactivate clears suspension reason and timestamp
    $reactivateResponse = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/suspend");

    $reactivateResponse->assertRedirect();
    $this->tenant->refresh();
    expect($this->tenant->isSuspended())->toBeFalse();
    expect($this->tenant->suspension_reason)->toBeNull();
});

test('super admin can add a cashier staff user to the tenant', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/users", [
            'name' => 'Kashif Cashier',
            'email' => 'kashif@apex.test',
            'role' => 'cashier',
            'password' => 'CashierPass123!',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $cashier = User::where('tenant_id', $this->tenant->id)
        ->where('email', 'kashif@apex.test')
        ->first();

    expect($cashier)->not->toBeNull();
    expect($cashier->role)->toBe('cashier');
    expect($cashier->super_admin)->toBeFalse();
    expect(Hash::check('CashierPass123!', $cashier->password))->toBeTrue();
});

test('super admin can reset a tenant staff member password', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/users/{$cashier->id}/reset-password", [
            'password' => 'BrandNewPassword999!',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $cashier->refresh();
    expect(Hash::check('BrandNewPassword999!', $cashier->password))->toBeTrue();
});

test('super admin can remove a cashier staff user', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->delete("/superadmin/tenants/{$this->tenant->id}/users/{$cashier->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(User::find($cashier->id))->toBeNull();
});

test('super admin cannot remove the only owner of a tenant', function () {
    $response = $this->actingAs($this->superAdmin)
        ->delete("/superadmin/tenants/{$this->tenant->id}/users/{$this->owner->id}");

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Cannot remove the only owner account of this tenant.');

    expect(User::find($this->owner->id))->not->toBeNull();
});

test('super admin can add an additional branch location', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/locations", [
            'name' => 'Apex Gulberg Branch',
            'address' => 'Shop 4, Main Boulevard, Gulberg, Lahore',
            'province' => 'punjab',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $newLocation = Location::where('tenant_id', $this->tenant->id)
        ->where('name', 'Apex Gulberg Branch')
        ->first();

    expect($newLocation)->not->toBeNull();
    expect($newLocation->address)->toBe('Shop 4, Main Boulevard, Gulberg, Lahore');
});

