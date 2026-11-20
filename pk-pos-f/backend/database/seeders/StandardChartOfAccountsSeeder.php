<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\AccountingService;
use Illuminate\Database\Seeder;

class StandardChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(AccountingService $accountingService): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $accountingService->provisionDefaultChartOfAccounts($tenant);
        }
    }
}
