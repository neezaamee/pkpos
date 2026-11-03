<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->super_admin) {
            abort(403, 'Unauthorized access.');
        }

        if (! $request->user()->is_active) {
            abort(403, 'Your staff account has been deactivated.');
        }

        return $next($request);
    }
}
