<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'name' => 'Primary Super Admin',
        'email' => 'superadmin@pkpos.pk',
        'staff_role' => 'super_admin',
        'is_active' => true,
    ]);

    $this->supportAgent = User::factory()->supportAgent()->create([
        'name' => 'Helpdesk Agent',
        'email' => 'support@pkpos.pk',
        'staff_role' => 'support_agent',
        'is_active' => true,
    ]);

    $this->complianceAuditor = User::factory()->complianceAuditor()->create([
        'name' => 'FBR Auditor',
        'email' => 'auditor@pkpos.pk',
        'staff_role' => 'compliance_auditor',
        'is_active' => true,
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Al-Madina Store',
        'province' => 'punjab',
    ]);

    $this->tenantOwner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);
});

test('non-staff tenant user receives 403 on staff directory', function () {
    $this->actingAs($this->tenantOwner)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();
});

test('support agent and compliance auditor cannot access staff directory (403)', function () {
    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();

    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();
});

test('super admin can view staff directory with role metrics', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.staff.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Staff/Index')
            ->has('staff_members.data', 3)
            ->where('metrics.total_staff', 3)
            ->where('metrics.super_admins', 1)
            ->where('metrics.support_agents', 1)
            ->where('metrics.compliance_auditors', 1)
        );
});

test('super admin can create a new support agent staff member', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.staff.store'), [
            'name' => 'Zeeshan Ali',
            'email' => 'zeeshan@pkpos.pk',
            'password' => 'SecurePass123!',
            'staff_role' => 'support_agent',
        ]);

    $response->assertRedirect();

    $newStaff = User::where('email', 'zeeshan@pkpos.pk')->first();
    expect($newStaff)->not->toBeNull()
        ->and($newStaff->super_admin)->toBeTrue()
        ->and($newStaff->staff_role)->toBe('support_agent')
        ->and($newStaff->is_active)->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'staff_user_created',
        'auditable_type' => User::class,
        'auditable_id' => $newStaff->id,
    ]);
});

test('super admin can update a staff member role and details', function () {
    $response = $this->actingAs($this->superAdmin)
        ->put(route('superadmin.staff.update', $this->supportAgent->id), [
            'name' => 'Senior Helpdesk Specialist',
            'email' => 'support.senior@pkpos.pk',
            'staff_role' => 'compliance_auditor',
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $this->supportAgent->refresh();
    expect($this->supportAgent->name)->toBe('Senior Helpdesk Specialist')
        ->and($this->supportAgent->email)->toBe('support.senior@pkpos.pk')
        ->and($this->supportAgent->staff_role)->toBe('compliance_auditor');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'staff_user_updated',
        'auditable_type' => User::class,
        'auditable_id' => $this->supportAgent->id,
    ]);
});

test('super admin cannot downgrade their own role or deactivate themselves', function () {
    // Attempt to downgrade self to support_agent
    $response1 = $this->actingAs($this->superAdmin)
        ->put(route('superadmin.staff.update', $this->superAdmin->id), [
            'name' => 'Primary Super Admin',
            'email' => $this->superAdmin->email,
            'staff_role' => 'support_agent',
            'is_active' => true,
        ]);

    $response1->assertRedirect();
    $this->superAdmin->refresh();
    expect($this->superAdmin->staff_role)->toBe('super_admin');

    // Attempt to deactivate self
    $response2 = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.staff.toggle-status', $this->superAdmin->id));

    $response2->assertRedirect();
    $this->superAdmin->refresh();
    expect($this->superAdmin->is_active)->toBeTrue();
});

test('cannot deactivate or delete the only active super admin', function () {
    // Only 1 super admin exists
    $response = $this->actingAs($this->superAdmin)
        ->delete(route('superadmin.staff.destroy', $this->superAdmin->id));

    $response->assertRedirect();
    expect(User::where('id', $this->superAdmin->id)->exists())->toBeTrue();
});

test('super admin can reset a staff member password', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.staff.reset-password', $this->supportAgent->id), [
            'password' => 'BrandNewPassword99!',
            'password_confirmation' => 'BrandNewPassword99!',
        ]);

    $response->assertRedirect();

    $this->supportAgent->refresh();
    expect(Hash::check('BrandNewPassword99!', $this->supportAgent->password))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'staff_password_reset',
        'auditable_type' => User::class,
        'auditable_id' => $this->supportAgent->id,
    ]);
});

test('deactivated staff member receives 403 on super admin portal', function () {
    $this->supportAgent->update(['is_active' => false]);

    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.dashboard'))
        ->assertForbidden();
});

test('support agent role access permissions are properly enforced', function () {
    // Support Agent CAN access support tickets & tenant list
    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.support.tickets.index'))
        ->assertOk();

    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.tenants.index'))
        ->assertOk();

    // Support Agent CANNOT access plans, queue jobs, or staff directory
    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.plans.index'))
        ->assertForbidden();

    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.system.jobs'))
        ->assertForbidden();

    $this->actingAs($this->supportAgent)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();
});

test('compliance auditor role access permissions are properly enforced', function () {
    // Compliance Auditor CAN access FBR compliance overview, audit logs, and master HS codes
    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.compliance.fbr.overview'))
        ->assertOk();

    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.audit.index'))
        ->assertOk();

    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.master-data.hs-codes.index'))
        ->assertOk();

    // Compliance Auditor CANNOT access support tickets, plans, or staff directory
    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.support.tickets.index'))
        ->assertForbidden();

    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.plans.index'))
        ->assertForbidden();

    $this->actingAs($this->complianceAuditor)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();
});

