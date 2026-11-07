<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the admin summary dashboard.
     */
    public function __invoke(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        if ($request->user()?->super_admin && ! session()->has('impersonated_by')) {
            return redirect()->route('superadmin.dashboard');
        }

        $todaySalesCount = Sale::whereDate('created_at', today())->count();
        $totalRevenueMinor = (int) Sale::sum('total_minor');

        $fbrSubmitted = Sale::where('fbr_status', 'submitted')->count();
        $fbrOffline = Sale::where('fbr_status', 'offline')->count();
        $fbrFailed = Sale::where('fbr_status', 'failed')->count();
        $fbrPending = Sale::where('fbr_status', 'pending')->count();

        $fbrEligible = $fbrSubmitted + $fbrOffline + $fbrFailed + $fbrPending;
        $fbrSuccessRate = $fbrEligible > 0 ? round(($fbrSubmitted / $fbrEligible) * 100, 1) : 100.0;

        // Pending outbox count: items pending cloud submission or offline awaiting sync
        $pendingOutboxCount = $fbrPending + $fbrOffline;

        $formattedRevenue = 'PKR ' . number_format($totalRevenueMinor / 100, 2);

        $tenantId = $request->user()->tenant_id;
        $totalTerminals = \App\Models\PosTerminal::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();
        $onlineTerminals = \App\Models\PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('pairing_status', 'paired')
            ->where('last_heartbeat_at', '>=', now()->subMinutes(15))
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'today_sales_count' => $todaySalesCount,
                'total_revenue_minor' => $totalRevenueMinor,
                'formatted_revenue' => $formattedRevenue,
                'fbr_success_rate' => $fbrSuccessRate,
                'fbr_submitted_count' => $fbrSubmitted,
                'fbr_offline_count' => $fbrOffline,
                'fbr_failed_count' => $fbrFailed,
                'fbr_pending_count' => $fbrPending,
                'fbr_eligible_count' => $fbrEligible,
                'pending_outbox_count' => $pendingOutboxCount,
                'terminals_total' => $totalTerminals,
                'terminals_online' => $onlineTerminals,
            ],
        ]);
    }
}
