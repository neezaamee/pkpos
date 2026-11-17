<?php

use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Retailers',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('super admin can access /superadmin/settings and default settings are automatically initialized', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/settings');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Settings/Index')
            ->has('grouped_settings.payment_gateways')
            ->has('grouped_settings.platform_defaults')
            ->has('grouped_settings.thermal_receipts')
            ->has('grouped_settings.support_contact')
        );

    // Verify database has initialized defaults
    expect(SystemSetting::where('key', 'gateway_stripe_enabled')->exists())->toBeTrue();
    expect(SystemSetting::where('key', 'gateway_jazzcash_merchant_id')->exists())->toBeTrue();
    expect(SystemSetting::where('key', 'platform_name')->exists())->toBeTrue();
    expect(SystemSetting::where('key', 'receipt_global_footer_note')->exists())->toBeTrue();
});

test('non-superadmin cannot access /superadmin/settings', function () {
    $response = $this->actingAs($this->owner)
        ->get('/superadmin/settings');

    $response->assertStatus(403);
});

test('unauthenticated visitor is redirected to login', function () {
    $response = $this->get('/superadmin/settings');

    $response->assertRedirect('/login');
});

test('super admin can update payment gateway credentials and verify secret keys are encrypted at rest', function () {
    // Prime settings
    $this->actingAs($this->superAdmin)->get('/superadmin/settings');

    $response = $this->actingAs($this->superAdmin)
        ->post('/superadmin/settings', [
            'settings' => [
                ['key' => 'gateway_stripe_enabled', 'value' => true],
                ['key' => 'gateway_stripe_publishable_key', 'value' => 'pk_test_51MzXYZ1234567890'],
                ['key' => 'gateway_stripe_secret_key', 'value' => 'sk_test_51MzSecretKey1234567890'],
                ['key' => 'gateway_jazzcash_merchant_id', 'value' => 'MC123456'],
                ['key' => 'gateway_jazzcash_password', 'value' => 'JazzPass9988'],
                ['key' => 'platform_name', 'value' => '01POS Enterprise Retail Cloud'],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify unencrypted / plain value
    $brandSetting = SystemSetting::where('key', 'platform_name')->first();
    expect($brandSetting->value)->toBe('01POS Enterprise Retail Cloud');

    // Verify encrypted secret in database column
    $secretRecord = SystemSetting::where('key', 'gateway_stripe_secret_key')->first();
    expect($secretRecord->is_secret)->toBeTrue();
    expect($secretRecord->value)->not->toBe('sk_test_51MzSecretKey1234567890'); // Must NOT be plaintext
    expect(Crypt::decryptString($secretRecord->value))->toBe('sk_test_51MzSecretKey1234567890');

    // Verify helper get() returns decrypted value
    expect(SystemSetting::get('gateway_stripe_secret_key'))->toBe('sk_test_51MzSecretKey1234567890');
    expect(SystemSetting::get('gateway_stripe_enabled'))->toBeTrue();
    expect(SystemSetting::get('gateway_jazzcash_merchant_id'))->toBe('MC123456');
    expect(SystemSetting::get('gateway_jazzcash_password'))->toBe('JazzPass9988');
});

test('sensitive secrets are masked in the inertia payload', function () {
    // Save a secret
    SystemSetting::set('gateway_stripe_secret_key', 'sk_test_51MzSecretKey1234567890', 'payment_gateways', 'string', true);

    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/settings');

    $response->assertStatus(200)
        ->assertInertia(function (Assert $page) {
            $gateways = $page->toArray()['props']['grouped_settings']['payment_gateways'];
            $stripeSecret = collect($gateways)->firstWhere('key', 'gateway_stripe_secret_key');

            expect($stripeSecret)->not->toBeNull();
            expect($stripeSecret['value'])->toBe(''); // Secret plaintext must NOT be exposed in value
            expect($stripeSecret['masked_value'])->toContain('••••••••');
            expect($stripeSecret['has_value'])->toBeTrue();
        });
});

test('gateway connectivity test validates input parameters', function () {
    $response = $this->actingAs($this->superAdmin)
        ->postJson('/superadmin/settings/test-gateway', [
            'gateway' => 'jazzcash',
        ]);

    // Since jazzcash credentials were not set, it should return 422 with missing parameters error
    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);

    // Now configure jazzcash
    SystemSetting::set('gateway_jazzcash_merchant_id', 'MC99999', 'payment_gateways', 'string', false);
    SystemSetting::set('gateway_jazzcash_password', 'secret_pwd', 'payment_gateways', 'string', true);
    SystemSetting::set('gateway_jazzcash_hash_key', 'secret_salt', 'payment_gateways', 'string', true);

    $validResponse = $this->actingAs($this->superAdmin)
        ->postJson('/superadmin/settings/test-gateway', [
            'gateway' => 'jazzcash',
        ]);

    $validResponse->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});
