<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Seed standard platform permissions and roles
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->superAdmin()->create([
        'name' => 'Chief Admin',
        'email' => 'chief@pkpos.pk',
        'staff_role' => 'super_admin',
        'is_active' => true,
    ]);

    $this->supportAgent = User::factory()->supportAgent()->create([
        'name' => 'Helpdesk Officer',
        'email' => 'helpdesk@pkpos.pk',
        'staff_role' => 'support_agent',
        'is_active' => true,
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Green Valley Hypermarket',
        'province' => 'punjab',
    ]);

    $this->tenantOwner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('non-staff tenant user receives 403 on roles directory', function () {
    $this->actingAs($this->tenantOwner)
        ->get(route('superadmin.roles.index'))
        ->assertForbidden();
});

test('support agent cannot access roles directory (403)', function () {
    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.roles.index'))
        ->assertForbidden();
});

test('super admin can view roles directory with permissions grouped by module', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get(route('superadmin.roles.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Roles/Index')
            ->has('roles')
            ->has('permissions_by_module.tenants')
            ->has('permissions_by_module.fbr')
            ->has('permissions_by_module.support')
            ->where('metrics.total_roles', fn ($count) => $count >= 5)
            ->where('metrics.total_permissions', fn ($count) => $count >= 25)
        );
});

test('super admin can create a custom operational role with selected permissions', function () {
    $fbrPerms = Permission::where('module', 'fbr')->pluck('id')->toArray();

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.roles.store'), [
            'name' => 'Provincial Tax Specialist',
            'slug' => 'provincial_tax_specialist',
            'description' => 'Dedicated staff role for handling provincial revenue authority compliance.',
            'color' => '#10b981',
            'permissions' => $fbrPerms,
        ]);

    $response->assertRedirect();

    $createdRole = Role::where('slug', 'provincial_tax_specialist')->first();
    expect($createdRole)->not->toBeNull()
        ->and($createdRole->name)->toBe('Provincial Tax Specialist')
        ->and($createdRole->color)->toBe('#10b981')
        ->and($createdRole->is_system)->toBeFalse()
        ->and($createdRole->permissions)->toHaveCount(count($fbrPerms));

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'role_created',
        'auditable_type' => Role::class,
        'auditable_id' => $createdRole->id,
    ]);
});

test('super admin can update a custom role and toggle its permissions', function () {
    $role = Role::create([
        'name' => 'Junior Helpdesk',
        'slug' => 'junior_helpdesk',
        'description' => 'Level 1 support ticket responder.',
        'color' => '#64748b',
        'is_system' => false,
    ]);

    $supportPerm = Permission::where('slug', 'support.view')->first();
    $role->permissions()->sync([$supportPerm->id]);

    $newPerms = Permission::whereIn('slug', ['support.view', 'support.reply'])->pluck('id')->toArray();

    $response = $this->actingAs($this->superAdmin)
        ->put(route('superadmin.roles.update', $role->id), [
            'name' => 'Senior Support Specialist',
            'description' => 'Level 2 ticket escalation specialist.',
            'color' => '#3b82f6',
            'permissions' => $newPerms,
        ]);

    $response->assertRedirect();

    $role->refresh();
    expect($role->name)->toBe('Senior Support Specialist')
        ->and($role->color)->toBe('#3b82f6')
        ->and($role->permissions)->toHaveCount(2);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'role_updated',
        'auditable_type' => Role::class,
        'auditable_id' => $role->id,
    ]);
});

test('super admin cannot delete system core role', function () {
    $systemRole = Role::where('slug', 'super_admin')->first();

    $response = $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.roles.destroy', $systemRole->id));

    $response->assertRedirect();
    expect(Role::where('id', $systemRole->id)->exists())->toBeTrue();
});

test('cannot delete custom role if staff members are assigned to it', function () {
    $customRole = Role::create([
        'name' => 'Terminal Inspector',
        'slug' => 'terminal_inspector',
        'is_system' => false,
    ]);

    // Assign to support agent
    $this->supportAgent->roles()->attach($customRole->id);

    $response = $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.roles.destroy', $customRole->id));

    $response->assertRedirect();
    expect(Role::where('id', $customRole->id)->exists())->toBeTrue();
});

test('super admin can delete an unused custom role', function () {
    $customRole = Role::create([
        'name' => 'Temporary Observer',
        'slug' => 'temp_observer',
        'is_system' => false,
    ]);

    $roleId = $customRole->id;

    $response = $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.roles.destroy', $roleId));

    $response->assertRedirect();
    expect(Role::where('id', $roleId)->exists())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'role_deleted',
        'auditable_type' => Role::class,
        'auditable_id' => $roleId,
    ]);
});

test('user hasPermission helper accurately reflects assigned capabilities and super admin bypass', function () {
    // 1. Super Admin has all permissions automatically
    expect($this->superAdmin->hasPermission('fbr.retry_single'))->toBeTrue()
        ->and($this->superAdmin->hasPermission('system.manage_jobs'))->toBeTrue()
        ->and($this->superAdmin->hasPermission('roles.manage'))->toBeTrue();

    // 2. Support Agent has support.reply, but NOT system.manage_jobs
    expect($this->supportAgent->hasPermission('support.reply'))->toBeTrue()
        ->and($this->supportAgent->hasPermission('support.impersonate'))->toBeTrue()
        ->and($this->supportAgent->hasPermission('system.manage_jobs'))->toBeFalse()
        ->and($this->supportAgent->hasPermission('roles.manage'))->toBeFalse();

    // 3. User with custom role
    $customRole = Role::create([
        'name' => 'Queue Dispatcher',
        'slug' => 'queue_dispatcher',
        'is_system' => false,
    ]);
    $jobPerm = Permission::where('slug', 'system.manage_jobs')->first();
    $customRole->permissions()->attach($jobPerm->id);

    $staffMember = User::factory()->create([
        'super_admin' => true,
        'staff_role' => 'queue_dispatcher',
        'is_active' => true,
    ]);
    $staffMember->roles()->attach($customRole->id);

    expect($staffMember->hasPermission('system.manage_jobs'))->toBeTrue()
        ->and($staffMember->hasPermission('fbr.retry_single'))->toBeFalse();
});

