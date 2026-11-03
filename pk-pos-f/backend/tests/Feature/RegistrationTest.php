<?php

use App\Models\Tenant;
use App\Models\User;

test('registration creates tenant and owner in a single transaction', function () {
    $payload = [
        'tenant_name' => 'Metro Supermarket',
        'ntn' => '9876543-2',
        'strn' => '11-22-3344-555-66',
        'province' => 'punjab',
        'fbr_pos_id' => 'POS-9999',
        'name' => 'Muhammad Ali',
        'email' => 'ali@metrosupermarket.pk',
        'password' => 'SecurePass123!',
    ];

    $response = $this->postJson('/api/v1/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'tenant' => ['id', 'name', 'ntn', 'strn', 'province', 'fbr_pos_id'],
            'user' => ['id', 'tenant_id', 'name', 'email', 'role'],
            'token',
        ]);

    $tenant = Tenant::where('ntn', '9876543-2')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Metro Supermarket')
        ->and($tenant->province)->toBe('punjab')
        ->and($tenant->fbr_pos_id)->toBe('POS-9999');

    $user = User::withoutGlobalScopes()->where('email', 'ali@metrosupermarket.pk')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->name)->toBe('Muhammad Ali')
        ->and($user->role)->toBe('owner');
});

test('registration validates required fields and province enum', function () {
    $response = $this->postJson('/api/v1/register', [
        'tenant_name' => '',
        'province' => 'invalid_province',
        'email' => 'not-an-email',
        'password' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_name', 'ntn', 'strn', 'province', 'email', 'password']);
});
