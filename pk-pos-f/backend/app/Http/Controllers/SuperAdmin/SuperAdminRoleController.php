<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminRoleController extends Controller
{
    /**
     * Display the Roles & Granular Permissions Directory and Matrix.
     */
    public function index(): Response
    {
        $roles = Role::with(['permissions:id,name,slug,module'])
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'description' => $r->description,
                'color' => $r->color,
                'scope' => $r->scope,
                'is_system' => (bool) $r->is_system,
                'users_count' => $r->users_count,
                'permissions' => $r->permissions->pluck('id')->toArray(),
                'permissions_count' => $r->permissions->count(),
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        $permissionsByModule = Permission::orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module')
            ->map(fn ($group) => $group->map(fn ($p) => [
                'id' => $p->id,
                'module' => $p->module,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
            ]));

        $allPermissionsCount = Permission::count();
        $totalRolesCount = $roles->count();
        $systemRolesCount = $roles->where('is_system', true)->count();
        $customRolesCount = $roles->where('is_system', false)->count();

        return Inertia::render('SuperAdmin/Roles/Index', [
            'roles' => $roles,
            'permissions_by_module' => $permissionsByModule,
            'metrics' => [
                'total_roles' => $totalRolesCount,
                'system_roles' => $systemRolesCount,
                'custom_roles' => $customRolesCount,
                'total_permissions' => $allPermissionsCount,
            ],
        ]);
    }

    /**
     * Store a newly created custom role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $slug = $validated['slug'] ?: Str::slug($validated['name'], '_');

        // Check unique slug fallback
        if (Role::where('slug', $slug)->exists()) {
            $slug .= '_' . Str::lower(Str::random(4));
        }

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?: '#3b82f6',
            'scope' => 'platform',
            'is_system' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'role_created',
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'new_values' => [
                'name' => $role->name,
                'slug' => $role->slug,
                'permissions_count' => count($validated['permissions'] ?? []),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Custom role '{$role->name}' created successfully.");
    }

    /**
     * Update an existing role and its permissions matrix.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $role->description,
            'color' => $validated['color'] ?: $role->color,
        ]);

        // If not super_admin system role, sync submitted permissions
        if ($role->slug !== 'super_admin') {
            $role->permissions()->sync($validated['permissions'] ?? []);
        }

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'role_updated',
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'new_values' => [
                'name' => $role->name,
                'permissions_count' => count($validated['permissions'] ?? []),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Role '{$role->name}' updated successfully.");
    }

    /**
     * Delete a custom role.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return back()->with('error', "System role '{$role->name}' cannot be deleted.");
        }

        $assignedUsersCount = $role->users()->count();
        if ($assignedUsersCount > 0) {
            return back()->with('error', "Cannot delete role '{$role->name}' because {$assignedUsersCount} staff member(s) are assigned to it.");
        }

        $roleName = $role->name;
        $roleId = $role->id;
        $role->delete();

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'role_deleted',
            'auditable_type' => Role::class,
            'auditable_id' => $roleId,
            'new_values' => ['deleted_role' => $roleName],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Custom role '{$roleName}' has been removed.");
    }
}

