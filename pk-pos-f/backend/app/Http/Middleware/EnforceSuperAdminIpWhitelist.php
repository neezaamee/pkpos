<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\SuperAdminIpWhitelist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnforceSuperAdminIpWhitelist
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip();

        if (! SuperAdminIpWhitelist::isIpAllowed($clientIp)) {
            Log::warning("SuperAdmin Security Alert: Unauthorized access attempt from IP '{$clientIp}' by User ID " . (Auth::id() ?? 'guest'));

            try {
                AuditLog::create([
                    'tenant_id' => null,
                    'user_id' => Auth::id(),
                    'event' => 'superadmin_ip_blocked',
                    'auditable_type' => SuperAdminIpWhitelist::class,
                    'auditable_id' => '00000000-0000-0000-0000-000000000000',
                    'old_values' => ['blocked_ip' => $clientIp, 'url' => $request->fullUrl()],
                    'ip_address' => $clientIp,
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable) {
                // Ignore audit log failure
            }

            abort(403, "Access Restricted: IP address '{$clientIp}' is not authorized to access the Super Admin Command Center.");
        }

        return $next($request);
    }
}
