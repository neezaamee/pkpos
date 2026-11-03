<?php

namespace Database\Seeders;

use App\Models\MasterHsCode;
use App\Models\ProvincialTaxTemplate;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the master data seeder.
     */
    public function run(): void
    {
        // 1. Common Pakistan Customs / FBR HS Codes
        $hsCodes = [
            [
                'hs_code' => '1006.3010',
                'description' => 'Semi-milled or wholly milled rice, polished or glazed (Basmati)',
                'tax_category' => 'general_goods',
                'default_rate_basis_points' => 1800,
                'authority' => 'fbr',
            ],
            [
                'hs_code' => '1512.1100',
                'description' => 'Sunflower-seed or safflower oil and fractions thereof',
                'tax_category' => 'general_goods',
                'default_rate_basis_points' => 1800,
                'authority' => 'fbr',
            ],
            [
                'hs_code' => '0902.3000',
                'description' => 'Black tea (fermented) and partly fermented tea in immediate packings <= 3 kg',
                'tax_category' => 'general_goods',
                'default_rate_basis_points' => 1800,
                'authority' => 'fbr',
            ],
            [
                'hs_code' => '0401.2000',
                'description' => 'Milk and cream, not concentrated nor containing added sugar, fat > 1% but <= 6%',
                'tax_category' => 'exempt',
                'default_rate_basis_points' => 0,
                'authority' => 'fbr',
            ],
            [
                'hs_code' => '3004.9099',
                'description' => 'Medicaments of mixed or unmixed products for therapeutic or prophylactic uses',
                'tax_category' => 'reduced_goods',
                'default_rate_basis_points' => 100,
                'authority' => 'fbr',
            ],
            [
                'hs_code' => '8517.1300',
                'description' => 'Smartphones and other cellular mobile telephones',
                'tax_category' => 'tier_1_retail',
                'default_rate_basis_points' => 1800,
                'authority' => 'fbr',
            ],
            [
                'hs_code' => '9801.1000',
                'description' => 'Services provided by restaurants, cafes, and marriage halls',
                'tax_category' => 'services',
                'default_rate_basis_points' => 1600,
                'authority' => 'pra',
            ],
            [
                'hs_code' => '9802.1000',
                'description' => 'Services provided by beauty parlors, saloons, and slimming centers',
                'tax_category' => 'services',
                'default_rate_basis_points' => 1600,
                'authority' => 'pra',
            ],
        ];

        foreach ($hsCodes as $code) {
            MasterHsCode::firstOrCreate(['hs_code' => $code['hs_code']], $code);
        }

        // 2. Standard Provincial & Federal Tax Templates
        $templates = [
            [
                'province' => 'federal',
                'name' => 'Federal Standard GST (Goods)',
                'authority' => 'fbr',
                'tax_category' => 'general_goods',
                'payment_method' => 'any',
                'rate_basis_points' => 1800,
                'effective_from' => '2024-07-01',
                'description' => 'Standard Federal General Sales Tax applicable to all taxable goods.',
            ],
            [
                'province' => 'punjab',
                'name' => 'Punjab Standard Sales Tax (Services)',
                'authority' => 'pra',
                'tax_category' => 'services',
                'payment_method' => 'any',
                'rate_basis_points' => 1600,
                'effective_from' => '2024-07-01',
                'description' => 'Standard PRA Sales Tax on services in Punjab province.',
            ],
            [
                'province' => 'punjab',
                'name' => 'Punjab Digital Restaurant Incentive (Card/Digital)',
                'authority' => 'pra',
                'tax_category' => 'services',
                'payment_method' => 'card',
                'rate_basis_points' => 500,
                'effective_from' => '2024-07-01',
                'description' => 'PRA 5% reduced tax incentive for dining paid through credit/debit card or digital wallet.',
            ],
            [
                'province' => 'sindh',
                'name' => 'Sindh Standard Sales Tax (Services)',
                'authority' => 'srb',
                'tax_category' => 'services',
                'payment_method' => 'any',
                'rate_basis_points' => 1500,
                'effective_from' => '2024-07-01',
                'description' => 'Standard SRB Sales Tax on services in Sindh province.',
            ],
            [
                'province' => 'sindh',
                'name' => 'Sindh Digital Dining Incentive (Card/Digital)',
                'authority' => 'srb',
                'tax_category' => 'services',
                'payment_method' => 'card',
                'rate_basis_points' => 800,
                'effective_from' => '2024-07-01',
                'description' => 'SRB 8% reduced rate for restaurant services settled via electronic payment cards.',
            ],
            [
                'province' => 'kp',
                'name' => 'Khyber Pakhtunkhwa Standard Sales Tax',
                'authority' => 'kpra',
                'tax_category' => 'services',
                'payment_method' => 'any',
                'rate_basis_points' => 1500,
                'effective_from' => '2024-07-01',
                'description' => 'KPRA standard sales tax on services.',
            ],
            [
                'province' => 'balochistan',
                'name' => 'Balochistan Standard Sales Tax',
                'authority' => 'bra',
                'tax_category' => 'services',
                'payment_method' => 'any',
                'rate_basis_points' => 1500,
                'effective_from' => '2024-07-01',
                'description' => 'BRA standard sales tax on services.',
            ],
        ];

        foreach ($templates as $tmpl) {
            ProvincialTaxTemplate::firstOrCreate(
                ['province' => $tmpl['province'], 'name' => $tmpl['name']],
                $tmpl
            );
        }
    }
}

