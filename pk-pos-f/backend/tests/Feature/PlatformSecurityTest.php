<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
});

test('non-staff user cannot access security terminal', function () {
    $this->actingAs($this->regularUser)
        ->get(route('superadmin.security.index'))
        ->assertForbidden();
});

test('staff member can view security dashboard with active sessions and 2FA telemetry', function () {
    DB::table('sessions')->insert([
        'id' => 'sess_test_12345678',
        'user_id' => $this->superAdmin->id,
        'ip_address' => '192.168.1.10',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        'payload' => 'dummy_payload',
        'last_activity' => time(),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.security.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Security/Index')
            ->has('sessions')
            ->has('metrics.active_sessions_count')
            ->has('user2fa')
        );

    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.security.index'))
        ->assertOk();
});

test('staff member can initiate 2FA setup and get a Base32 secret', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.security.2fa.setup'));

    $response->assertRedirect();
    $this->superAdmin->refresh();
    expect($this->superAdmin->two_factor_secret)->not->toBeNull();
    expect(strlen($this->superAdmin->two_factor_secret))->toBe(16);
});

test('staff member can confirm 2FA with valid TOTP code and it logs audit trail', function () {
    // Generate secret
    $secret = User::generateBase32Secret(16);
    $this->superAdmin->two_factor_secret = $secret;
    $this->superAdmin->save();

    // Calculate valid code for current time slice
    $timeSlice = (int) floor(time() / 30);
    $validCode = $this->superAdmin->calculateTotp($secret, $timeSlice);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.security.2fa.confirm'), [
            'code' => $validCode,
        ]);

    $response->assertRedirect();
    $this->superAdmin->refresh();

    expect($this->superAdmin->hasTwoFactorEnabled())->toBeTrue();
    expect($this->superAdmin->two_factor_recovery_codes)->toBeArray();
    expect(count($this->superAdmin->two_factor_recovery_codes))->toBe(8);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'two_factor_authentication_enabled',
        'auditable_type' => User::class,
        'auditable_id' => $this->superAdmin->id,
    ]);
});

test('confirmation fails when 2FA code is invalid', function () {
    $secret = User::generateBase32Secret(16);
    $this->superAdmin->two_factor_secret = $secret;
    $this->superAdmin->save();

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.security.2fa.confirm'), [
            'code' => '000000', // Invalid code
        ]);

    $response->assertSessionHasErrors(['code']);
    $this->superAdmin->refresh();
    expect($this->superAdmin->hasTwoFactorEnabled())->toBeFalse();
});

test('staff member can disable 2FA', function () {
    $this->superAdmin->two_factor_secret = 'TESTSECRET123456';
    $this->superAdmin->two_factor_confirmed_at = now();
    $this->superAdmin->two_factor_recovery_codes = ['RC1', 'RC2'];
    $this->superAdmin->save();

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.security.2fa.disable'));

    $response->assertRedirect();
    $this->superAdmin->refresh();

    expect($this->superAdmin->hasTwoFactorEnabled())->toBeFalse();
    expect($this->superAdmin->two_factor_secret)->toBeNull();
    expect($this->superAdmin->two_factor_recovery_codes)->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'two_factor_authentication_disabled',
        'auditable_type' => User::class,
        'auditable_id' => $this->superAdmin->id,
    ]);
});

test('super admin can revoke individual session with audit trail', function () {
    DB::table('sessions')->insert([
        'id' => 'sess_suspicious_1234',
        'user_id' => $this->regularUser->id,
        'ip_address' => '203.0.113.195',
        'user_agent' => 'Suspicious Bot',
        'payload' => 'payload_data',
        'last_activity' => time(),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.security.sessions.kill', 'sess_suspicious_1234'));

    $response->assertRedirect();

    $this->assertDatabaseMissing('sessions', [
        'id' => 'sess_suspicious_1234',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'user_session_terminated',
        'auditable_type' => User::class,
    ]);
});

test('super admin can execute platform-wide emergency session killswitch', function () {
    DB::table('sessions')->insert([
        [
            'id' => 'session_merchant_1',
            'user_id' => $this->regularUser->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Merchant POS Terminal',
            'payload' => 'payload_1',
            'last_activity' => time(),
        ],
        [
            'id' => 'session_merchant_2',
            'user_id' => $this->supportAgent->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'Support Desk',
            'payload' => 'payload_2',
            'last_activity' => time(),
        ],
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.security.sessions.kill-all'));

    $response->assertRedirect();

    $this->assertDatabaseMissing('sessions', [
        'id' => 'session_merchant_1',
    ]);
    $this->assertDatabaseMissing('sessions', [
        'id' => 'session_merchant_2',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'platform_emergency_killswitch_executed',
        'auditable_type' => User::class,
    ]);
});

test('super admin can toggle platform-wide mandatory 2FA enforcement policy', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.security.enforce-2fa'));

    $response->assertRedirect();
    $this->superAdmin->refresh();
    expect($this->superAdmin->two_factor_enforced)->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'platform_mandatory_2fa_policy_toggled',
        'auditable_type' => User::class,
    ]);
});

