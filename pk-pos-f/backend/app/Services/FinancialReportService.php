<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FinancialReportService
{
    /**
     * Generate Trial Balance.
     */
    public function getTrialBalance(Tenant|string $tenant, ?string $asOfDate = null): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $date = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : now()->endOfDay();

        $accounts = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get();

        $rows = [];
        $totalDebitsMinor = 0;
        $totalCreditsMinor = 0;

        foreach ($accounts as $acc) {
            $debits = (int) JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->whereHas('journalEntry', function (Builder $q) use ($date) {
                    $q->where('status', 'posted')
                        ->whereDate('entry_date', '<=', $date);
                })
                ->sum('debit_minor');

            $credits = (int) JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->whereHas('journalEntry', function (Builder $q) use ($date) {
                    $q->where('status', 'posted')
                        ->whereDate('entry_date', '<=', $date);
                })
                ->sum('credit_minor');

            // Net balance according to normal balance convention
            if ($acc->isDebitNormal()) {
                $netBalance = $acc->opening_balance_minor + ($debits - $credits);
                $trialDebit = $netBalance > 0 ? $netBalance : 0;
                $trialCredit = $netBalance < 0 ? abs($netBalance) : 0;
            } else {
                $netBalance = $acc->opening_balance_minor + ($credits - $debits);
                $trialDebit = $netBalance < 0 ? abs($netBalance) : 0;
                $trialCredit = $netBalance > 0 ? $netBalance : 0;
            }

            if ($debits > 0 || $credits > 0 || $acc->opening_balance_minor != 0) {
                $rows[] = [
                    'account_id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'type' => $acc->type,
                    'sub_type' => $acc->sub_type,
                    'normal_balance' => $acc->normal_balance,
                    'opening_balance_minor' => $acc->opening_balance_minor,
                    'period_debits_minor' => $debits,
                    'period_credits_minor' => $credits,
                    'trial_debit_minor' => $trialDebit,
                    'trial_credit_minor' => $trialCredit,
                    'net_balance_minor' => $netBalance,
                ];

                $totalDebitsMinor += $trialDebit;
                $totalCreditsMinor += $trialCredit;
            }
        }

        return [
            'as_of_date' => $date->toDateString(),
            'rows' => $rows,
            'total_debits_minor' => $totalDebitsMinor,
            'total_credits_minor' => $totalCreditsMinor,
            'difference_minor' => $totalDebitsMinor - $totalCreditsMinor,
            'is_balanced' => $totalDebitsMinor === $totalCreditsMinor,
        ];
    }

    /**
     * Generate Profit & Loss (Income Statement).
     */
    public function getProfitAndLoss(Tenant|string $tenant, ?string $startDate = null, ?string $endDate = null): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        // 1. Revenue Accounts (code 4000s)
        $revenueAccounts = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'revenue')
            ->orderBy('code')
            ->get();

        $revenueRows = [];
        $grossSalesMinor = 0;
        $salesReturnsMinor = 0;
        $salesDiscountsMinor = 0;
        $otherIncomeMinor = 0;

        foreach ($revenueAccounts as $acc) {
            $debits = (int) $this->getPeriodLinesQuery($tenantId, $acc->id, $start, $end)->sum('debit_minor');
            $credits = (int) $this->getPeriodLinesQuery($tenantId, $acc->id, $start, $end)->sum('credit_minor');

            // Revenue normal balance is credit: Net = Credits - Debits
            $net = $credits - $debits;

            if ($acc->code === '4010') {
                $grossSalesMinor = $net;
            } elseif ($acc->code === '4020') {
                // Contra-revenue: Debits represent returns
                $salesReturnsMinor = $debits - $credits;
            } elseif ($acc->code === '4030') {
                $salesDiscountsMinor = $debits - $credits;
            } else {
                $otherIncomeMinor += $net;
            }

            if ($debits > 0 || $credits > 0) {
                $revenueRows[] = [
                    'account_id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'amount_minor' => $net,
                ];
            }
        }

        $netSalesRevenueMinor = $grossSalesMinor - $salesReturnsMinor - $salesDiscountsMinor + $otherIncomeMinor;

        // 2. Direct Costs / COGS Accounts (code 5000s)
        $cogsAccounts = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'expense')
            ->where('sub_type', 'direct_cost')
            ->orderBy('code')
            ->get();

        $cogsRows = [];
        $totalCogsMinor = 0;

        foreach ($cogsAccounts as $acc) {
            $debits = (int) $this->getPeriodLinesQuery($tenantId, $acc->id, $start, $end)->sum('debit_minor');
            $credits = (int) $this->getPeriodLinesQuery($tenantId, $acc->id, $start, $end)->sum('credit_minor');

            // Expense normal is debit
            $net = $debits - $credits;
            $totalCogsMinor += $net;

            if ($debits > 0 || $credits > 0) {
                $cogsRows[] = [
                    'account_id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'amount_minor' => $net,
                ];
            }
        }

        $grossProfitMinor = $netSalesRevenueMinor - $totalCogsMinor;
        $grossMarginPct = $netSalesRevenueMinor > 0 ? round(($grossProfitMinor / $netSalesRevenueMinor) * 100, 2) : 0.0;

        // 3. Operating Expenses (code 6000s)
        $expenseAccounts = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'expense')
            ->where('sub_type', '!=', 'direct_cost')
            ->orderBy('code')
            ->get();

        $expenseRows = [];
        $totalOperatingExpensesMinor = 0;

        foreach ($expenseAccounts as $acc) {
            $debits = (int) $this->getPeriodLinesQuery($tenantId, $acc->id, $start, $end)->sum('debit_minor');
            $credits = (int) $this->getPeriodLinesQuery($tenantId, $acc->id, $start, $end)->sum('credit_minor');

            $net = $debits - $credits;
            $totalOperatingExpensesMinor += $net;

            if ($debits > 0 || $credits > 0) {
                $expenseRows[] = [
                    'account_id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'amount_minor' => $net,
                ];
            }
        }

        $netProfitMinor = $grossProfitMinor - $totalOperatingExpensesMinor;
        $netMarginPct = $netSalesRevenueMinor > 0 ? round(($netProfitMinor / $netSalesRevenueMinor) * 100, 2) : 0.0;

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'revenue' => [
                'gross_sales_minor' => $grossSalesMinor,
                'sales_returns_minor' => $salesReturnsMinor,
                'sales_discounts_minor' => $salesDiscountsMinor,
                'other_income_minor' => $otherIncomeMinor,
                'net_sales_revenue_minor' => $netSalesRevenueMinor,
                'rows' => $revenueRows,
            ],
            'cogs' => [
                'total_cogs_minor' => $totalCogsMinor,
                'rows' => $cogsRows,
            ],
            'gross_profit_minor' => $grossProfitMinor,
            'gross_margin_percentage' => $grossMarginPct,
            'operating_expenses' => [
                'total_expenses_minor' => $totalOperatingExpensesMinor,
                'rows' => $expenseRows,
            ],
            'net_profit_minor' => $netProfitMinor,
            'net_margin_percentage' => $netMarginPct,
        ];
    }

    /**
     * Generate Balance Sheet (Statement of Financial Position).
     */
    public function getBalanceSheet(Tenant|string $tenant, ?string $asOfDate = null): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $date = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : now()->endOfDay();

        // 1. ASSETS
        $assetAccounts = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'asset')
            ->orderBy('code')
            ->get();

        $currentAssetsRows = [];
        $nonCurrentAssetsRows = [];
        $totalAssetsMinor = 0;

        foreach ($assetAccounts as $acc) {
            $debits = (int) JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $date))
                ->sum('debit_minor');

            $credits = (int) JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $date))
                ->sum('credit_minor');

            $balance = $acc->opening_balance_minor + ($debits - $credits);
            $totalAssetsMinor += $balance;

            $row = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'sub_type' => $acc->sub_type,
                'balance_minor' => $balance,
            ];

            if ($acc->sub_type === 'fixed_asset') {
                $nonCurrentAssetsRows[] = $row;
            } else {
                $currentAssetsRows[] = $row;
            }
        }

        // 2. LIABILITIES
        $liabilityAccounts = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'liability')
            ->orderBy('code')
            ->get();

        $liabilityRows = [];
        $totalLiabilitiesMinor = 0;

        foreach ($liabilityAccounts as $acc) {
            $debits = (int) JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $date))
                ->sum('debit_minor');

            $credits = (int) JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $date))
                ->sum('credit_minor');

            $balance = $acc->opening_balance_minor + ($credits - $debits);
            $totalLiabilitiesMinor += $balance;

            $liabilityRows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'sub_type' => $acc->sub_type,
                'balance_minor' => $balance,
            ];
        }

        // 3. EQUITY
        $equityAccounts = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'equity')
            ->orderBy('code')
            ->get();

        $equityRows = [];
        $totalBaseEquityMinor = 0;

        foreach ($equityAccounts as $acc) {
            $debits = (int) JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $date))
                ->sum('debit_minor');

            $credits = (int) JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $date))
                ->sum('credit_minor');

            // Drawings are normal debit (reduce equity)
            if ($acc->isDebitNormal()) {
                $balance = -($acc->opening_balance_minor + ($debits - $credits));
            } else {
                $balance = $acc->opening_balance_minor + ($credits - $debits);
            }

            $totalBaseEquityMinor += $balance;

            $equityRows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'sub_type' => $acc->sub_type,
                'balance_minor' => $balance,
            ];
        }

        // Calculate cumulative Net Profit from inception up to asOfDate
        $pnl = $this->getProfitAndLoss($tenantId, '1970-01-01', $date->toDateString());
        $currentEarningsMinor = $pnl['net_profit_minor'];

        $totalEquityMinor = $totalBaseEquityMinor + $currentEarningsMinor;
        $totalLiabilitiesAndEquityMinor = $totalLiabilitiesMinor + $totalEquityMinor;

        return [
            'as_of_date' => $date->toDateString(),
            'assets' => [
                'current_assets' => $currentAssetsRows,
                'non_current_assets' => $nonCurrentAssetsRows,
                'total_assets_minor' => $totalAssetsMinor,
            ],
            'liabilities' => [
                'rows' => $liabilityRows,
                'total_liabilities_minor' => $totalLiabilitiesMinor,
            ],
            'equity' => [
                'rows' => $equityRows,
                'current_earnings_minor' => $currentEarningsMinor,
                'total_equity_minor' => $totalEquityMinor,
            ],
            'total_liabilities_and_equity_minor' => $totalLiabilitiesAndEquityMinor,
            'difference_minor' => $totalAssetsMinor - $totalLiabilitiesAndEquityMinor,
            'is_balanced' => $totalAssetsMinor === $totalLiabilitiesAndEquityMinor,
        ];
    }

    /**
     * Get Chronological General Ledger statement for an account.
     */
    public function getAccountLedger(Account $account, ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subMonths(3)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        // 1. Calculate opening balance before startDate
        $priorDebits = (int) JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $account->tenant_id)
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function (Builder $q) use ($start) {
                $q->where('status', 'posted')->whereDate('entry_date', '<', $start);
            })
            ->sum('debit_minor');

        $priorCredits = (int) JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $account->tenant_id)
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function (Builder $q) use ($start) {
                $q->where('status', 'posted')->whereDate('entry_date', '<', $start);
            })
            ->sum('credit_minor');

        if ($account->isDebitNormal()) {
            $runningBalance = $account->opening_balance_minor + ($priorDebits - $priorCredits);
        } else {
            $runningBalance = $account->opening_balance_minor + ($priorCredits - $priorDebits);
        }

        $openingBalanceMinor = $runningBalance;

        // 2. Fetch lines within period
        $lines = JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $account->tenant_id)
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->where('status', 'posted')
                    ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->with(['journalEntry:id,entry_number,entry_date,source_type,reference,description'])
            ->get()
            ->sortBy(fn ($l) => $l->journalEntry->entry_date->timestamp . '-' . $l->created_at->timestamp);

        $transactions = [];
        $periodDebitSum = 0;
        $periodCreditSum = 0;

        foreach ($lines as $line) {
            $debit = $line->debit_minor;
            $credit = $line->credit_minor;
            $periodDebitSum += $debit;
            $periodCreditSum += $credit;

            if ($account->isDebitNormal()) {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $transactions[] = [
                'id' => $line->id,
                'entry_id' => $line->journalEntry->id,
                'entry_number' => $line->journalEntry->entry_number,
                'entry_date' => $line->journalEntry->entry_date->toDateString(),
                'source_type' => $line->journalEntry->source_type,
                'reference' => $line->journalEntry->reference,
                'description' => $line->journalEntry->description,
                'memo' => $line->memo,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'running_balance_minor' => $runningBalance,
            ];
        }

        return [
            'account' => $account,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'opening_balance_minor' => $openingBalanceMinor,
            'transactions' => $transactions,
            'total_debit_minor' => $periodDebitSum,
            'total_credit_minor' => $periodCreditSum,
            'closing_balance_minor' => $runningBalance,
        ];
    }

    protected function getPeriodLinesQuery(string $tenantId, string $accountId, Carbon $start, Carbon $end): Builder
    {
        return JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->where('status', 'posted')
                    ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()]);
            });
    }
}
