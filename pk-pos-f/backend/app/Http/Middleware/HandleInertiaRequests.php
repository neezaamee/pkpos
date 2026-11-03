<?php

namespace App\Http\Middleware;

use App\Models\PosTerminal;
use App\Models\Sale;
use App\Models\SupportTicket;
use App\Models\TenantApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'tenant_id' => $request->user()->tenant_id,
                    ...($request->user()->super_admin ? [
                        'is_super_admin' => true,
                        'staff_role' => $request->user()->staff_role ?: 'super_admin',
                    ] : []),
                ] : null,
                'impersonation' => $request->session()->has('impersonated_by') ? [
                    'active' => true,
                    'tenant_name' => $request->session()->get('impersonated_tenant_name'),
                ] : null,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'superadmin_badges' => fn () => ($request->user() && $request->user()->super_admin) ? [
                'pending_onboarding' => TenantApplication::where('status', 'pending')->count(),
                'open_tickets' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
                'dlq_count' => Sale::withoutGlobalScopes()->where('fbr_status', 'failed')->count(),
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'offline_terminals' => PosTerminal::withoutGlobalScopes()->where('is_active', true)->where(function ($q) {
                    $q->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', now()->subMinutes(15));
                })->count(),
            ] : null,
            'system_telemetry' => fn () => [
                'server_time_pkt' => now('Asia/Karachi')->format('d M Y - H:i:s \P\K\T'),
                'fbr_sandbox_mode' => (bool) config('services.fbr.sandbox', true),
                'environment' => config('app.env', 'production'),
                'version' => 'v2.4.0-fiscal',
                'impersonating' => (bool) $request->session()->has('impersonated_by'),
            ],
        ];
    }
}
