<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the plans seeder.
     */
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter Tier',
                'description' => 'Single checkout register for small retail kiosks, bakeries, and standalone cafes.',
                'price_minor' => 350000, // 3,500 PKR / month
                'billing_interval' => 'month',
                'max_locations' => 1,
                'max_terminals' => 1,
                'features' => [
                    'fbr_integration',
                    'offline_sales',
                    'thermal_receipts',
                    'basic_reporting',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'growth',
                'name' => 'Retail Growth Tier',
                'description' => 'Multi-counter setup designed for growing supermarkets, pharmacies, and department stores.',
                'price_minor' => 850000, // 8,500 PKR / month
                'billing_interval' => 'month',
                'max_locations' => 3,
                'max_terminals' => 5,
                'features' => [
                    'fbr_integration',
                    'offline_sales',
                    'thermal_receipts',
                    'advanced_inventory',
                    'multi_branch',
                    'digital_incentives_pra_srb',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise Flagship',
                'description' => 'Unlimited counters and branches with dedicated SLA, 24/7 compliance support, and custom integrations.',
                'price_minor' => 2500000, // 25,000 PKR / month
                'billing_interval' => 'month',
                'max_locations' => null, // unlimited
                'max_terminals' => null, // unlimited
                'features' => [
                    'fbr_integration',
                    'offline_sales',
                    'thermal_receipts',
                    'advanced_inventory',
                    'multi_branch',
                    'digital_incentives_pra_srb',
                    'priority_fbr_support',
                    'custom_api_integrations',
                    'unlimited_terminals',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['code' => $plan['code']], $plan);
        }
    }
}

