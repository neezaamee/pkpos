<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return $next($request);
        }

        $tenantId = $request->user()?->tenant_id ?? 'global';
        $cacheKey = "idempotency:{$tenantId}:{$idempotencyKey}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response()->json($cached['data'], $cached['status'])
                ->header('Idempotency-Replayed', 'true');
        }

        $response = $next($request);

        // Cache successful responses for 24 hours
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $content = json_decode($response->getContent(), true);

            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'data' => $content ?? $response->getContent(),
            ], now()->addHours(24));
        }

        return $response;
    }
}
