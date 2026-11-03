<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PosTerminal;
use App\Models\Sale;
use App\Models\Scopes\TenantScope;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminDashboardController extends Controller
{
    /**
     * Display the Super Admin executive analytics & platform intelligence dashboard.
     */
    public function index(): Response
    {
        $thirtyDaysAgo = now()->subDays(30);

        // 1. Core Platform Counts & 30-Day Metrics
        $totalTenants = Tenant::count();

        $activeTenants = Tenant::whereHas('sales', function ($q) use ($thirtyDaysAgo) {
            $q->withoutGlobalScope(TenantScope::class)->where('created_at', '>=', $thirtyDaysAgo);
        })->count();

        $totalSalesVolume = (int) Sale::withoutGlobalScope(TenantScope::class)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('total_minor');

        $fbrSubmissionsToday = Sale::withoutGlobalScope(TenantScope::class)
            ->whereDate('created_at', today())
            ->where('fbr_status', 'submitted')
            ->count();

        $allTimeGmv = (int) Sale::withoutGlobalScope(TenantScope::class)->sum('total_minor');
        $totalSalesCount = Sale::withoutGlobalScope(TenantScope::class)->count();

        // 2. FBR Fiscal Compliance Breakdown & Score
        $fbrStatsRaw = Sale::withoutGlobalScope(TenantScope::class)
            ->select('fbr_status', DB::raw('COUNT(*) as count'))
            ->groupBy('fbr_status')
            ->pluck('count', 'fbr_status')
            ->toArray();

        $submittedCount = $fbrStatsRaw['submitted'] ?? 0;
        $failedCount = $fbrStatsRaw['failed'] ?? 0;
        $pendingCount = $fbrStatsRaw['pending'] ?? 0;
        $provisionalCount = $fbrStatsRaw['provisional'] ?? 0;

        $totalFiscalized = $submittedCount + $failedCount + $pendingCount + $provisionalCount;
        $complianceRate = $totalFiscalized > 0
            ? round(($submittedCount / $totalFiscalized) * 100, 1)
            : 100.0;

        // 3. Operational Quick Alert Counts
        $openTicketsCount = 0;
        try {
            $openTicketsCount = SupportTicket::withoutGlobalScopes()
                ->whereIn('status', ['open', 'in_progress'])
                ->count();
        } catch (\Throwable) {
            $openTicketsCount = 0;
        }

        $activeTerminalsCount = 0;
        try {
            $activeTerminalsCount = PosTerminal::withoutGlobalScopes()
                ->where('is_active', true)
                ->where('pairing_status', 'paired')
                ->count();
        } catch (\Throwable) {
            $activeTerminalsCount = 0;
        }

        // 4. 14-Day Daily GMV Trend
        $fourteenDaysAgo = now()->subDays(14)->startOfDay();
        $dailyTrendsRaw = Sale::withoutGlobalScope(TenantScope::class)
            ->where('created_at', '>=', $fourteenDaysAgo)
            ->selectRaw("DATE(created_at) as sale_date, SUM(total_minor) as total_volume, COUNT(*) as tx_count")
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        $dailyGmv = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $row = $dailyTrendsRaw->firstWhere('sale_date', $d);
            $dailyGmv[] = [
                'date' => $d,
                'label' => now()->subDays($i)->format('d M'),
                'total_volume' => (int) ($row->total_volume ?? 0),
                'tx_count' => (int) ($row->tx_count ?? 0),
            ];
        }

        // 5. Provincial Revenue & Merchant Distribution
        $provinceData = DB::table('tenants')
            ->leftJoin('sales', 'tenants.id', '=', 'sales.tenant_id')
            ->select(
                'tenants.province',
                DB::raw('COUNT(DISTINCT tenants.id) as tenant_count'),
                DB::raw('COALESCE(SUM(sales.total_minor), 0) as total_volume'),
                DB::raw('COUNT(sales.id) as sales_count')
            )
            ->groupBy('tenants.province')
            ->get()
            ->map(fn ($r) => [
                'province' => $r->province ?: 'other',
                'tenant_count' => (int) $r->tenant_count,
                'total_volume' => (int) $r->total_volume,
                'sales_count' => (int) $r->sales_count,
            ]);

        // 6. Top Merchants by Volume
        $topMerchants = Tenant::withCount(['sales as total_sales' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->withSum(['sales as total_volume' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)], 'total_minor')
            ->withCount('terminals')
            ->orderByDesc('total_volume')
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'province' => $t->province,
                'total_volume' => (int) ($t->total_volume ?? 0),
                'total_sales' => (int) ($t->total_sales ?? 0),
                'terminals_count' => (int) ($t->terminals_count ?? 0),
                'is_suspended' => (bool) $t->suspended_at,
            ]);

        // 7. Recent 5 Tenants
        $recentTenants = Tenant::latest()
            ->take(5)
            ->get(['id', 'name', 'ntn', 'province', 'created_at', 'suspended_at']);

        return Inertia::render('SuperAdmin/Dashboard', [
            'metrics' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'total_sales_volume' => $totalSalesVolume,
                'fbr_submissions_today' => $fbrSubmissionsToday,
                'all_time_gmv' => $allTimeGmv,
                'total_sales_count' => $totalSalesCount,
                'fbr_compliance_rate' => $complianceRate,
                'failed_invoices_count' => $failedCount,
                'pending_invoices_count' => $pendingCount,
                'open_tickets_count' => $openTicketsCount,
                'active_terminals_count' => $activeTerminalsCount,
            ],
            'fbr_breakdown' => [
                'submitted' => $submittedCount,
                'failed' => $failedCount,
                'pending' => $pendingCount,
                'provisional' => $provisionalCount,
                'compliance_rate' => $complianceRate,
            ],
            'daily_gmv' => $dailyGmv,
            'province_distribution' => $provinceData,
            'top_merchants' => $topMerchants,
            'recent_tenants' => $recentTenants,
        ]);
    }
}
