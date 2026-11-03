<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->super_admin) {
            abort(403, 'Unauthorized access.');
        }

        if (! $user->is_active) {
            abort(403, 'Your staff account has been deactivated.');
        }

        $currentRole = $user->staff_role ?: 'super_admin';

        if (! empty($roles) && ! in_array($currentRole, $roles, true)) {
            abort(403, 'Your staff role does not have permission to access this resource.');
        }

        return $next($request);
    }
}

