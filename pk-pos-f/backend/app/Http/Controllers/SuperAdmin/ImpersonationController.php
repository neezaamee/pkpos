<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Impersonate a tenant owner user.
     */
    public function impersonate(Request $request, string $userId): RedirectResponse
    {
        $targetUser = User::withoutGlobalScope(TenantScope::class)
            ->with('tenant')
            ->findOrFail($userId);

        // Security check: Must belong to a tenant and cannot impersonate another super admin
        if (! $targetUser->tenant_id || $targetUser->super_admin) {
            abort(403, 'Cannot impersonate a non-tenant user.');
        }

        // Security check: Cannot impersonate suspended tenant
        if ($targetUser->tenant?->isSuspended()) {
            return back()->with('error', 'Cannot impersonate a user from a suspended tenant.');
        }

        $superAdminId = Auth::id();

        // Audit log the impersonation action
        AuditLog::create([
            'tenant_id' => $targetUser->tenant_id,
            'user_id' => $superAdminId,
            'event' => 'impersonated',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
            'new_values' => [
                'target_user_name' => $targetUser->name,
                'target_user_email' => $targetUser->email,
                'tenant_name' => $targetUser->tenant?->name,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Remember super admin id and tenant name in session
        $request->session()->put('impersonated_by', $superAdminId);
        $request->session()->put('impersonated_tenant_id', $targetUser->tenant_id);
        $request->session()->put('impersonated_tenant_name', $targetUser->tenant?->name ?? 'Tenant');

        // Login as the target user
        Auth::loginUsingId($targetUser->id);

        return redirect()->route('dashboard');
    }

    /**
     * Stop impersonating and return to the Super Admin account.
     */
    public function leave(Request $request): RedirectResponse
    {
        if ($request->session()->has('impersonated_by')) {
            $superAdminId = $request->session()->get('impersonated_by');
            $impersonatedTenantId = $request->session()->get('impersonated_tenant_id');
            $impersonatedTenantName = $request->session()->get('impersonated_tenant_name');

            // Audit log the end of impersonation
            AuditLog::create([
                'tenant_id' => $impersonatedTenantId,
                'user_id' => $superAdminId,
                'event' => 'impersonation_ended',
                'auditable_type' => User::class,
                'auditable_id' => $superAdminId,
                'old_values' => [
                    'tenant_name' => $impersonatedTenantName,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $request->session()->forget([
                'impersonated_by',
                'impersonated_tenant_id',
                'impersonated_tenant_name',
            ]);

            $superAdmin = User::withoutGlobalScope(TenantScope::class)->find($superAdminId);
            if ($superAdmin) {
                Auth::login($superAdmin);
            }

            return redirect()
                ->route('superadmin.tenants.index')
                ->with('success', 'Returned to Super Admin session.');
        }

        return redirect()->route('dashboard');
    }
}
