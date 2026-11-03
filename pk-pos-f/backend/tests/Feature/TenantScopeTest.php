<?php

use App\Models\Location;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Setup Tenant A
    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A Corp', 'province' => 'punjab']);
    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'owner',
    ]);
    $this->locationA = Location::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Branch A Lahore',
        'address' => 'Gulberg, Lahore',
        'province' => 'punjab',
    ]);

    // Setup Tenant B
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B Ltd', 'province' => 'sindh']);
    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'owner',
    ]);
    $this->locationB = Location::create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Branch B Karachi',
        'address' => 'Clifton, Karachi',
        'province' => 'sindh',
    ]);
});

test('a user from Tenant A cannot query a location from Tenant B', function () {
    // Authenticate as User from Tenant A
    $this->actingAs($this->userA);

    $locations = Location::all();

    expect($locations)->toHaveCount(1)
        ->and($locations->first()->id)->toBe($this->locationA->id)
        ->and($locations->pluck('id'))->not->toContain($this->locationB->id);

    // Direct find by Tenant B's ID must return null
    $foundLocationB = Location::find($this->locationB->id);
    expect($foundLocationB)->toBeNull();

    // Direct where clause for Tenant B's ID must return null
    $whereLocationB = Location::where('id', $this->locationB->id)->first();
    expect($whereLocationB)->toBeNull();
});

test('a user from Tenant B cannot query a location from Tenant A', function () {
    // Authenticate as User from Tenant B
    $this->actingAs($this->userB);

    $locations = Location::all();

    expect($locations)->toHaveCount(1)
        ->and($locations->first()->id)->toBe($this->locationB->id)
        ->and($locations->pluck('id'))->not->toContain($this->locationA->id);

    $foundLocationA = Location::find($this->locationA->id);
    expect($foundLocationA)->toBeNull();
});

test('locations API endpoint applies tenant scope via Sanctum authentication', function () {
    Sanctum::actingAs($this->userA);

    $response = $this->getJson('/api/v1/locations');

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['name' => 'Branch A Lahore'])
        ->assertJsonMissing(['name' => 'Branch B Karachi']);
});

test('models can bypass tenant scope when explicitly instructed with withoutGlobalScope', function () {
    $this->actingAs($this->userA);

    $allLocations = Location::withoutGlobalScope(TenantScope::class)->get();

    expect($allLocations->count())->toBeGreaterThanOrEqual(2)
        ->and($allLocations->pluck('id'))->toContain($this->locationA->id)
        ->and($allLocations->pluck('id'))->toContain($this->locationB->id);
});

test('creating a location automatically assigns tenant_id of authenticated user if omitted', function () {
    $this->actingAs($this->userA);

    $newLocation = Location::create([
        'name' => 'Automatic Branch',
        'address' => 'DHA Phase 5, Lahore',
        'province' => 'punjab',
    ]);

    expect($newLocation->tenant_id)->toBe($this->userA->tenant_id);
});
