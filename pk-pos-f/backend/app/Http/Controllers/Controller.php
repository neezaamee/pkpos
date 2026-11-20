<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve the active tenant context for the request.
     * Supports tenant users, active impersonation session, and super admin preview fallback.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        if ($user->tenant) {
            return $user->tenant;
        }

        if ($user->tenant_id) {
            return Tenant::find($user->tenant_id);
        }

        // For Super Admin / Central Staff: allow viewing as impersonated tenant, query param, or first active tenant
        if ($user->super_admin) {
            $tenantId = $request->session()->get('impersonated_tenant_id')
                ?? $request->query('tenant_id')
                ?? $request->header('X-Tenant-ID');

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    return $tenant;
                }
            }

            return Tenant::whereNull('suspended_at')->first() ?? Tenant::first();
        }

        return null;
    }

    /**
     * Resolve the active tenant ID.
     */
    protected function resolveTenantId(Request $request): ?string
    {
        return $this->resolveTenant($request)?->id;
    }

    /**
     * Require a non-null active tenant context or gracefully redirect / abort.
     */
    protected function requireTenant(Request $request): Tenant
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            if ($request->user()?->super_admin) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    redirect()->route('superadmin.tenants.index')->with('error', 'No active store tenant found. Please create a tenant first.')
                );
            }
            abort(403, 'No active store tenant found.');
        }

        return $tenant;
    }

    /**
     * Authorize access for owner, manager, or super_admin.
     */
    protected function authorizeStaffOrSuperAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->super_admin || in_array($request->user()?->role, ['owner', 'manager']),
            403,
            'Unauthorized. Requires store owner, manager, or super admin privileges.'
        );
    }
}
