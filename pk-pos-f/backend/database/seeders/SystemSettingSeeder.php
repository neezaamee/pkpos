<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the system settings seeder.
     */
    public function run(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $settings = [
            // Platform Branding & Defaults
            [
                'key' => 'platform_name',
                'value' => '01POS Cloud Retail OS',
                'group' => 'platform_defaults',
                'type' => 'string',
                'is_secret' => false,
                'label' => 'Platform Brand Name',
                'description' => 'Branded name displayed across backoffice headers, emails, and invoices.',
            ],
            [
                'key' => 'platform_default_currency',
                'value' => 'PKR',
                'group' => 'platform_defaults',
                'type' => 'string',
                'is_secret' => false,
                'label' => 'Base Accounting Currency',
                'description' => 'Default 3-letter currency code (ISO 4217).',
            ],
            [
                'key' => 'platform_default_locale',
                'value' => 'en',
                'group' => 'platform_defaults',
                'type' => 'string',
                'is_secret' => false,
                'label' => 'System Default Language',
                'description' => 'Primary platform language (en = English, ur = Urdu).',
            ],
            [
                'key' => 'platform_default_timezone',
                'value' => 'Asia/Karachi',
                'group' => 'platform_defaults',
                'type' => 'string',
                'is_secret' => false,
                'label' => 'System Timezone',
                'description' => 'Standard timezone for fiscal dates and receipt timestamps.',
            ],
            [
                'key' => 'receipt_global_footer_note',
                'value' => "Thank you for your business!\nPowered by 01POS Retail Cloud\nFBR Tier-1 Fiscal POS Integration",
                'group' => 'thermal_receipts',
                'type' => 'text',
                'is_secret' => false,
                'label' => 'Default Thermal Receipt Footer',
                'description' => 'Global disclaimer printed at the bottom of customer 80mm/58mm thermal receipts.',
            ],
            [
                'key' => 'gateway_bank_wire_enabled',
                'value' => '1',
                'group' => 'payment_gateways',
                'type' => 'boolean',
                'is_secret' => false,
                'label' => 'Enable Offline Bank Wire & 1Bill / Kuickpay',
                'description' => 'Allow tenants to submit wire transfer proof or pay via 1Link 1Bill.',
            ],
            [
                'key' => 'gateway_bank_wire_bank_name',
                'value' => 'Meezan Bank Limited',
                'group' => 'payment_gateways',
                'type' => 'string',
                'is_secret' => false,
                'label' => 'Corporate Bank Name',
                'description' => 'Primary designated collection bank.',
            ],
            [
                'key' => 'gateway_bank_wire_account_title',
                'value' => '01Output Systems Private Limited',
                'group' => 'payment_gateways',
                'type' => 'string',
                'is_secret' => false,
                'label' => 'Account Title',
                'description' => 'Official registered corporate account title.',
            ],
            [
                'key' => 'gateway_bank_wire_iban',
                'value' => 'PK00MEZN0000000000000000',
                'group' => 'payment_gateways',
                'type' => 'string',
                'is_secret' => false,
                'label' => 'Account Number / IBAN',
                'description' => '24-character international bank account number.',
            ],
            [
                'key' => 'gateway_bank_wire_1bill_prefix',
                'value' => '100123',
                'group' => 'payment_gateways',
                'type' => 'string',
                'is_secret' => false,
                'label' => '1Bill Invoicing Prefix',
                'description' => '6-digit 1Link billing prefix for automated consumer bill discovery.',
            ],
        ];

        foreach ($settings as $item) {
            SystemSetting::updateOrCreate(['key' => $item['key']], $item);
        }
    }
}
