<?php

namespace App\Http\Controllers;

use App\Services\AccountingService;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportWebController extends Controller
{
    /**
     * Display Trial Balance report.
     */
    public function trialBalance(Request $request, FinancialReportService $reportService, AccountingService $accountingService): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $accountingService->provisionDefaultChartOfAccounts($tenant);

        $asOfDate = $request->query('as_of_date') ?? now()->toDateString();
        $report = $reportService->getTrialBalance($tenant, $asOfDate);

        return Inertia::render('Accounting/Reports/TrialBalance', [
            'report' => $report,
            'filters' => [
                'as_of_date' => $asOfDate,
            ],
        ]);
    }

    /**
     * Display Profit & Loss statement (Income Statement).
     */
    public function profitAndLoss(Request $request, FinancialReportService $reportService, AccountingService $accountingService): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $accountingService->provisionDefaultChartOfAccounts($tenant);

        $startDate = $request->query('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->query('end_date') ?? now()->toDateString();

        $report = $reportService->getProfitAndLoss($tenant, $startDate, $endDate);

        return Inertia::render('Accounting/Reports/ProfitLoss', [
            'report' => $report,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Display Balance Sheet (Statement of Financial Position).
     */
    public function balanceSheet(Request $request, FinancialReportService $reportService, AccountingService $accountingService): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $accountingService->provisionDefaultChartOfAccounts($tenant);

        $asOfDate = $request->query('as_of_date') ?? now()->toDateString();
        $report = $reportService->getBalanceSheet($tenant, $asOfDate);

        return Inertia::render('Accounting/Reports/BalanceSheet', [
            'report' => $report,
            'filters' => [
                'as_of_date' => $asOfDate,
            ],
        ]);
    }
}
