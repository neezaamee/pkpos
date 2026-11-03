<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantNotSuspended
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $isSuspended = \App\Models\Tenant::where('id', $user->tenant_id)
                ->whereNotNull('suspended_at')
                ->exists();

            if ($isSuspended) {
                return response()->json([
                    'message' => 'Tenant suspended',
                ], 403);
            }
        }

        return $next($request);
    }
}
