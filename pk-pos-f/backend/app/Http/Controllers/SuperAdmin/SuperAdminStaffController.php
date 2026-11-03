<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminStaffController extends Controller
{
    /**
     * Display a listing of platform staff users.
     */
    public function index(Request $request): Response
    {
        $query = User::withoutGlobalScopes()
            ->where('super_admin', true);

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Filter by role
        if ($role = $request->input('role')) {
            if ($role === 'super_admin') {
                $query->where(function ($q) {
                    $q->where('staff_role', 'super_admin')
                        ->orWhereNull('staff_role');
                });
            } else {
                $query->where('staff_role', $role);
            }
        }

        // Filter by status
        if ($request->has('status') && $request->input('status') !== '') {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $staffUsers = $query->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'staff_role' => $u->staff_role ?: 'super_admin',
                'is_active' => (bool) $u->is_active,
                'is_current_user' => $u->id === Auth::id(),
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        // Aggregate statistics
        $allStaff = User::withoutGlobalScopes()->where('super_admin', true)->get();
        $metrics = [
            'total_staff' => $allStaff->count(),
            'super_admins' => $allStaff->filter(fn ($u) => in_array($u->staff_role, ['super_admin', null], true))->count(),
            'support_agents' => $allStaff->where('staff_role', 'support_agent')->count(),
            'compliance_auditors' => $allStaff->where('staff_role', 'compliance_auditor')->count(),
            'inactive_staff' => $allStaff->where('is_active', false)->count(),
        ];

        $availableRoles = Role::orderBy('name')->get(['id', 'name', 'slug', 'color', 'description']);

        return Inertia::render('SuperAdmin/Staff/Index', [
            'staff_members' => $staffUsers,
            'filters' => $request->only(['search', 'role', 'status']),
            'metrics' => $metrics,
            'available_roles' => $availableRoles,
        ]);
    }

    /**
     * Store a newly created platform staff member.
     */
    public function store(Request $request): RedirectResponse
    {
        $validRoleSlugs = Role::pluck('slug')->toArray();
        if (empty($validRoleSlugs)) {
            $validRoleSlugs = ['super_admin', 'support_agent', 'compliance_auditor'];
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'staff_role' => ['required', 'string', Rule::in($validRoleSlugs)],
        ]);

        $staff = User::create([
            'tenant_id' => null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'owner',
            'super_admin' => true,
            'staff_role' => $validated['staff_role'],
            'is_active' => true,
        ]);

        $roleModel = Role::where('slug', $staff->staff_role)->first();
        if ($roleModel) {
            $staff->roles()->sync([$roleModel->id]);
        }

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'staff_user_created',
            'auditable_type' => User::class,
            'auditable_id' => $staff->id,
            'new_values' => [
                'name' => $staff->name,
                'email' => $staff->email,
                'staff_role' => $staff->staff_role,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Staff member {$staff->name} created successfully.");
    }

    /**
     * Update an existing platform staff member.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $staff = User::withoutGlobalScopes()->where('super_admin', true)->findOrFail($id);

        $validRoleSlugs = Role::pluck('slug')->toArray();
        if (empty($validRoleSlugs)) {
            $validRoleSlugs = ['super_admin', 'support_agent', 'compliance_auditor'];
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($staff->id)],
            'staff_role' => ['required', 'string', Rule::in($validRoleSlugs)],
            'is_active' => ['required', 'boolean'],
        ]);

        // Safety Guard 1: Prevent user from downgrading their own super admin role
        if ($staff->id === Auth::id() && $validated['staff_role'] !== 'super_admin') {
            return back()->with('error', 'You cannot downgrade your own super admin role.');
        }

        // Safety Guard 2: Prevent user from deactivating themselves
        if ($staff->id === Auth::id() && ! $validated['is_active']) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        // Safety Guard 3: Prevent demoting or deactivating the last active super admin
        $isCurrentlySuperAdmin = in_array($staff->staff_role, ['super_admin', null], true);
        if ($isCurrentlySuperAdmin && ($validated['staff_role'] !== 'super_admin' || ! $validated['is_active'])) {
            $otherActiveSuperAdmins = User::withoutGlobalScopes()
                ->where('super_admin', true)
                ->where('is_active', true)
                ->where('id', '!=', $staff->id)
                ->where(fn ($q) => $q->where('staff_role', 'super_admin')->orWhereNull('staff_role'))
                ->count();

            if ($otherActiveSuperAdmins === 0) {
                return back()->with('error', 'Cannot demote or deactivate the only active Super Admin.');
            }
        }

        $oldValues = [
            'name' => $staff->name,
            'email' => $staff->email,
            'staff_role' => $staff->staff_role,
            'is_active' => $staff->is_active,
        ];

        $staff->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'staff_role' => $validated['staff_role'],
            'is_active' => $validated['is_active'],
        ]);

        $roleModel = Role::where('slug', $staff->staff_role)->first();
        if ($roleModel) {
            $staff->roles()->sync([$roleModel->id]);
        }

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'staff_user_updated',
            'auditable_type' => User::class,
            'auditable_id' => $staff->id,
            'old_values' => $oldValues,
            'new_values' => [
                'name' => $staff->name,
                'email' => $staff->email,
                'staff_role' => $staff->staff_role,
                'is_active' => $staff->is_active,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Staff member {$staff->name} updated successfully.");
    }

    /**
     * Reset a staff member's password.
     */
    public function resetPassword(Request $request, string $id): RedirectResponse
    {
        $staff = User::withoutGlobalScopes()->where('super_admin', true)->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $staff->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'staff_password_reset',
            'auditable_type' => User::class,
            'auditable_id' => $staff->id,
            'new_values' => ['reset_by' => Auth::id()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Password for {$staff->name} has been reset successfully.");
    }

    /**
     * Toggle active/deactivated status of a staff member.
     */
    public function toggleStatus(Request $request, string $id): RedirectResponse
    {
        $staff = User::withoutGlobalScopes()->where('super_admin', true)->findOrFail($id);

        if ($staff->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $newStatus = ! $staff->is_active;

        // If deactivating a super admin, make sure they are not the last active one
        $isCurrentlySuperAdmin = in_array($staff->staff_role, ['super_admin', null], true);
        if ($staff->is_active && $isCurrentlySuperAdmin) {
            $otherActiveSuperAdmins = User::withoutGlobalScopes()
                ->where('super_admin', true)
                ->where('is_active', true)
                ->where('id', '!=', $staff->id)
                ->where(fn ($q) => $q->where('staff_role', 'super_admin')->orWhereNull('staff_role'))
                ->count();

            if ($otherActiveSuperAdmins === 0) {
                return back()->with('error', 'Cannot deactivate the only active Super Admin.');
            }
        }

        $staff->update(['is_active' => $newStatus]);

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => $newStatus ? 'staff_user_activated' : 'staff_user_deactivated',
            'auditable_type' => User::class,
            'auditable_id' => $staff->id,
            'new_values' => ['is_active' => $newStatus],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $statusText = $newStatus ? 'activated' : 'deactivated';
        return back()->with('success', "Staff member {$staff->name} has been {$statusText}.");
    }

    /**
     * Delete a platform staff member.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $staff = User::withoutGlobalScopes()->where('super_admin', true)->findOrFail($id);

        if ($staff->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $isCurrentlySuperAdmin = in_array($staff->staff_role, ['super_admin', null], true);
        if ($isCurrentlySuperAdmin) {
            $otherActiveSuperAdmins = User::withoutGlobalScopes()
                ->where('super_admin', true)
                ->where('is_active', true)
                ->where('id', '!=', $staff->id)
                ->where(fn ($q) => $q->where('staff_role', 'super_admin')->orWhereNull('staff_role'))
                ->count();

            if ($otherActiveSuperAdmins === 0) {
                return back()->with('error', 'Cannot delete the only active Super Admin.');
            }
        }

        $name = $staff->name;
        $staffId = $staff->id;
        $staff->delete();

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'staff_user_deleted',
            'auditable_type' => User::class,
            'auditable_id' => $staffId,
            'new_values' => ['deleted_user_name' => $name],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Staff member {$name} has been deleted.");
    }
}

