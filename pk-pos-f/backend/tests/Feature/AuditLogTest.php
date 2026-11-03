<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Cash and Carry',
        'ntn' => '5556667-1',
        'province' => 'punjab',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Tahir Khan',
        'email' => 'tahir@metro.test',
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('creating a tenant automatically records a created audit log entry', function () {
    $tenant = Tenant::create([
        'name' => 'Lahore Retail Mart',
        'ntn' => '9988776-5',
        'strn' => '99-88-7766-554-11',
        'province' => 'punjab',
        'fbr_pos_id' => 'POS-5566',
    ]);

    $log = AuditLog::withoutGlobalScopes()
        ->where('auditable_type', Tenant::class)
        ->where('auditable_id', $tenant->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->tenant_id)->toBe($tenant->id);
    expect($log->new_values)->toHaveKey('name', 'Lahore Retail Mart');
    expect($log->new_values)->toHaveKey('ntn', '9988776-5');
});

test('updating a model records an updated audit log with old and new values only for changed fields', function () {
    $this->tenant->update([
        'name' => 'Metro Hypermarket Pakistan',
        'fbr_pos_id' => 'POS-9000',
    ]);

    $log = AuditLog::withoutGlobalScopes()
        ->where('auditable_type', Tenant::class)
        ->where('auditable_id', $this->tenant->id)
        ->where('event', 'updated')
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->old_values)->toHaveKey('name', 'Metro Cash and Carry');
    expect($log->new_values)->toHaveKey('name', 'Metro Hypermarket Pakistan');
    expect($log->new_values)->toHaveKey('fbr_pos_id', 'POS-9000');
    // Unchanged fields like 'ntn' should not be in the diff
    expect($log->new_values)->not->toHaveKey('ntn');
});

test('sensitive attributes like password are stripped from user audit logs', function () {
    $user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Asad Staff',
        'email' => 'asad@metro.test',
        'password' => Hash::make('SuperSecretPassword123!'),
        'role' => 'cashier',
        'super_admin' => false,
    ]);

    $log = AuditLog::withoutGlobalScopes()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->new_values)->not->toHaveKey('password');
    expect($log->new_values)->not->toHaveKey('remember_token');
    expect($log->new_values)->toHaveKey('email', 'asad@metro.test');
});

test('impersonating a tenant user creates an impersonated audit log with actor and target context', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post("/superadmin/impersonate/{$this->owner->id}");

    $response->assertRedirect('/dashboard');

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'impersonated')
        ->where('user_id', $this->superAdmin->id)
        ->where('auditable_id', $this->owner->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->tenant_id)->toBe($this->tenant->id);
    expect($log->new_values['target_user_email'])->toBe('tahir@metro.test');
});

test('super admin can access /superadmin/audit-logs and filter by event', function () {
    AuditLog::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->superAdmin->id,
        'event' => 'suspended',
        'auditable_type' => Tenant::class,
        'auditable_id' => $this->tenant->id,
        'new_values' => ['reason' => 'Audit review'],
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get('/superadmin/audit-logs?event=suspended');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/AuditLogs/Index')
            ->has('logs.data')
            ->where('filters.event', 'suspended')
        );
});

test('tenant owner receives 403 on /superadmin/audit-logs', function () {
    $this->actingAs($this->owner)
        ->get('/superadmin/audit-logs')
        ->assertStatus(403);
});

