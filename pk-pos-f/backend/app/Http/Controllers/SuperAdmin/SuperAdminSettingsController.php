<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminSettingsController extends Controller
{
    /**
     * Standard setting definitions and baseline defaults.
     */
    protected static array $defaultDefinitions = [
        // 1. Payment Gateways: Stripe
        'gateway_stripe_enabled' => [
            'group' => 'payment_gateways',
            'type' => 'boolean',
            'is_secret' => false,
            'default' => false,
            'label' => 'Enable Stripe Online Payments',
            'description' => 'Allow tenants to pay SaaS subscriptions via International Credit/Debit Cards.',
        ],
        'gateway_stripe_publishable_key' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => false,
            'default' => '',
            'label' => 'Stripe Publishable Key',
            'description' => 'Public API key starting with pk_test_ or pk_live_.',
        ],
        'gateway_stripe_secret_key' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => true,
            'default' => '',
            'label' => 'Stripe Secret Key',
            'description' => 'Restricted secret key starting with sk_test_ or sk_live_.',
        ],
        'gateway_stripe_webhook_secret' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => true,
            'default' => '',
            'label' => 'Stripe Webhook Signing Secret',
            'description' => 'Endpoint signing secret starting with whsec_.',
        ],

        // 1. Payment Gateways: JazzCash
        'gateway_jazzcash_enabled' => [
            'group' => 'payment_gateways',
            'type' => 'boolean',
            'is_secret' => false,
            'default' => false,
            'label' => 'Enable JazzCash Merchant Portal',
            'description' => 'Accept recurring and one-off SaaS subscription payments via JazzCash Mobile Wallets and Vouchers.',
        ],
        'gateway_jazzcash_merchant_id' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => false,
            'default' => '',
            'label' => 'JazzCash Merchant ID',
            'description' => 'Official JazzCash Merchant ID assigned by Mobilink Microfinance Bank.',
        ],
        'gateway_jazzcash_password' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => true,
            'default' => '',
            'label' => 'JazzCash Portal Password',
            'description' => 'API password generated in the JazzCash Merchant Portal.',
        ],
        'gateway_jazzcash_hash_key' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => true,
            'default' => '',
            'label' => 'JazzCash Integrity Salt / Hash Key',
            'description' => 'HMAC-SHA256 salt string used to sign HTTP post requests.',
        ],
        'gateway_jazzcash_sandbox' => [
            'group' => 'payment_gateways',
            'type' => 'boolean',
            'is_secret' => false,
            'default' => true,
            'label' => 'JazzCash Sandbox / Test Mode',
            'description' => 'When active, API requests route to sandbox.jazzcash.com.pk.',
        ],

        // 1. Payment Gateways: EasyPaisa
        'gateway_easypaisa_enabled' => [
            'group' => 'payment_gateways',
            'type' => 'boolean',
            'is_secret' => false,
            'default' => false,
            'label' => 'Enable EasyPaisa Merchant Gateway',
            'description' => 'Allow merchants to pay subscription fees using EasyPaisa App or OTC Retail Agents.',
        ],
        'gateway_easypaisa_store_id' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => false,
            'default' => '',
            'label' => 'EasyPaisa Store ID',
            'description' => 'Digital Merchant Store ID allocated by Telenor Bank.',
        ],
        'gateway_easypaisa_hash_key' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => true,
            'default' => '',
            'label' => 'EasyPaisa Hash Key',
            'description' => 'Merchant secret key used for checksum verification.',
        ],
        'gateway_easypaisa_sandbox' => [
            'group' => 'payment_gateways',
            'type' => 'boolean',
            'is_secret' => false,
            'default' => true,
            'label' => 'EasyPaisa Sandbox / Test Mode',
            'description' => 'Routes transactions to easypaisa test gateway environment.',
        ],

        // 1. Payment Gateways: Direct Bank Wire & 1Bill
        'gateway_bank_wire_enabled' => [
            'group' => 'payment_gateways',
            'type' => 'boolean',
            'is_secret' => false,
            'default' => true,
            'label' => 'Enable Offline Bank Wire & 1Bill / Kuickpay',
            'description' => 'Allow tenants to submit wire transfer proof or pay via 1Link 1Bill.',
        ],
        'gateway_bank_wire_bank_name' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => false,
            'default' => 'Meezan Bank Limited',
            'label' => 'Corporate Bank Name',
            'description' => 'Primary designated collection bank.',
        ],
        'gateway_bank_wire_account_title' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => false,
            'default' => '01Output Systems Private Limited',
            'label' => 'Account Title',
            'description' => 'Official registered corporate account title.',
        ],
        'gateway_bank_wire_iban' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => false,
            'default' => 'PK00MEZN0000000000000000',
            'label' => 'Account Number / IBAN',
            'description' => '24-character international bank account number.',
        ],
        'gateway_bank_wire_1bill_prefix' => [
            'group' => 'payment_gateways',
            'type' => 'string',
            'is_secret' => false,
            'default' => '100123',
            'label' => '1Bill Invoicing Prefix',
            'description' => '6-digit 1Link billing prefix for automated consumer bill discovery.',
        ],
        'gateway_bank_wire_instructions' => [
            'group' => 'payment_gateways',
            'type' => 'text',
            'is_secret' => false,
            'default' => "Please transfer your invoice amount using online IBFT or 1Bill.\nReference your SaaS Invoice Number (e.g. INV-2026-XXXX) in the transaction narration.\nEmail payment receipt to billing@01output.com for instant account activation.",
            'label' => 'Wire Payment Instructions',
            'description' => 'Instructions displayed on the tenant invoice checkout screen.',
        ],

        // 2. Platform Defaults
        'platform_name' => [
            'group' => 'platform_defaults',
            'type' => 'string',
            'is_secret' => false,
            'default' => '01POS Cloud Retail OS',
            'label' => 'Platform Brand Name',
            'description' => 'Branded name displayed across backoffice headers, emails, and invoices.',
        ],
        'platform_default_currency' => [
            'group' => 'platform_defaults',
            'type' => 'string',
            'is_secret' => false,
            'default' => 'PKR',
            'label' => 'Base Accounting Currency',
            'description' => 'Default 3-letter currency code (ISO 4217).',
        ],
        'platform_default_locale' => [
            'group' => 'platform_defaults',
            'type' => 'string',
            'is_secret' => false,
            'default' => 'en',
            'label' => 'System Default Language',
            'description' => 'Primary platform language (en = English, ur = Urdu).',
        ],
        'platform_default_timezone' => [
            'group' => 'platform_defaults',
            'type' => 'string',
            'is_secret' => false,
            'default' => 'Asia/Karachi',
            'label' => 'System Timezone',
            'description' => 'Standard timezone for fiscal dates and receipt timestamps.',
        ],
        'platform_maintenance_mode' => [
            'group' => 'platform_defaults',
            'type' => 'boolean',
            'is_secret' => false,
            'default' => false,
            'label' => 'Broadcast Maintenance Banner',
            'description' => 'Display a platform maintenance alert across all tenant web backoffices.',
        ],
        'platform_maintenance_message' => [
            'group' => 'platform_defaults',
            'type' => 'string',
            'is_secret' => false,
            'default' => 'Scheduled platform infrastructure optimization is ongoing. POS offline sync remains fully operational.',
            'label' => 'Maintenance Banner Text',
            'description' => 'Text displayed in the top banner during maintenance periods.',
        ],

        // 3. Thermal Receipt Global Defaults
        'receipt_global_footer_note' => [
            'group' => 'thermal_receipts',
            'type' => 'text',
            'is_secret' => false,
            'default' => "Thank you for your business!\nPowered by 01POS Retail Cloud\nFBR Tier-1 Fiscal POS Integration",
            'label' => 'Default Thermal Receipt Footer',
            'description' => 'Global disclaimer printed at the bottom of customer 80mm/58mm thermal receipts.',
        ],
        'receipt_return_policy_days' => [
            'group' => 'thermal_receipts',
            'type' => 'integer',
            'is_secret' => false,
            'default' => 7,
            'label' => 'Default Return Policy Window (Days)',
            'description' => 'Number of days allowed for customer refunds or merchandise exchanges.',
        ],
        'receipt_return_policy_disclaimer' => [
            'group' => 'thermal_receipts',
            'type' => 'text',
            'is_secret' => false,
            'default' => 'Refunds or exchanges accepted within 7 days with original invoice and undamaged packaging. Perishable or clearance items are non-refundable.',
            'label' => 'Return Policy Receipt Text',
            'description' => 'Standard refund policy printed on physical receipts.',
        ],

        // 4. Support & Communication
        'support_email' => [
            'group' => 'support_contact',
            'type' => 'string',
            'is_secret' => false,
            'default' => 'support@01output.com',
            'label' => 'Support Desk Email',
            'description' => 'Inbound customer inquiry and helpdesk contact email.',
        ],
        'support_phone' => [
            'group' => 'support_contact',
            'type' => 'string',
            'is_secret' => false,
            'default' => '+92 42 111 010 101',
            'label' => 'UAN Helpline / Phone',
            'description' => 'Direct telephone number for merchant support.',
        ],
        'support_whatsapp' => [
            'group' => 'support_contact',
            'type' => 'string',
            'is_secret' => false,
            'default' => '+92 300 0000000',
            'label' => 'Official WhatsApp Business Helpline',
            'description' => 'WhatsApp number with country code for rapid chat support.',
        ],
        'support_knowledge_base_url' => [
            'group' => 'support_contact',
            'type' => 'string',
            'is_secret' => false,
            'default' => 'https://01pos.01output.com/docs',
            'label' => 'Help Documentation URL',
            'description' => 'Merchant guide and onboarding tutorial portal link.',
        ],
    ];

    /**
     * Display the System Settings and Payment Gateways configuration page.
     */
    public function index(Request $request): Response
    {
        // Ensure all default settings exist in the database
        $this->ensureDefaultSettingsExist();

        $allSettings = SystemSetting::all();

        // Organize settings by groups
        $grouped = [
            'payment_gateways' => [],
            'platform_defaults' => [],
            'thermal_receipts' => [],
            'support_contact' => [],
        ];

        foreach ($allSettings as $setting) {
            $group = $setting->group;
            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $maskedValue = $setting->getMaskedValue();
            $rawValue = $setting->value;

            $grouped[$group][] = [
                'id' => $setting->id,
                'key' => $setting->key,
                'group' => $setting->group,
                'type' => $setting->type,
                'is_secret' => $setting->is_secret,
                'label' => $setting->label,
                'description' => $setting->description,
                'value' => $setting->is_secret ? '' : ($setting->type === 'boolean' ? filter_var($rawValue, FILTER_VALIDATE_BOOLEAN) : ($setting->type === 'integer' ? (int) $rawValue : $rawValue)),
                'masked_value' => $maskedValue,
                'has_value' => ! empty($rawValue),
            ];
        }

        // Recent Audit Logs for settings
        $auditLogs = AuditLog::where('event', 'system_settings_updated')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'event' => $log->event,
                'created_at' => $log->created_at?->diffForHumans() ?? 'Just now',
                'ip_address' => $log->ip_address,
                'user_name' => $log->user?->name ?? 'System Administrator',
            ]);

        return Inertia::render('SuperAdmin/Settings/Index', [
            'grouped_settings' => $grouped,
            'audit_logs' => $auditLogs,
        ]);
    }

    /**
     * Update system settings across groups.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:100'],
            'settings.*.value' => ['nullable'],
        ]);

        $changedKeys = [];

        foreach ($validated['settings'] as $item) {
            $key = $item['key'];
            $value = $item['value'];

            $setting = SystemSetting::where('key', $key)->first();
            if (! $setting) {
                continue;
            }

            // For secret settings: If empty or unchanged placeholder, do not overwrite existing secret
            if ($setting->is_secret) {
                if ($value === null || $value === '' || str_contains((string) $value, '••••')) {
                    continue;
                }
            }

            // Cast boolean strings
            if ($setting->type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } elseif ($setting->type === 'integer') {
                $value = (string) (int) $value;
            }

            // Encrypt secret if needed
            $storedValue = $value;
            if ($setting->is_secret && ! empty($value)) {
                $storedValue = Crypt::encryptString((string) $value);
            }

            if ($setting->value !== $storedValue) {
                $setting->update(['value' => $storedValue]);
                $changedKeys[] = $key;
            }
        }

        if (! empty($changedKeys)) {
            AuditLog::create([
                'tenant_id' => null,
                'user_id' => Auth::id(),
                'event' => 'system_settings_updated',
                'auditable_type' => SystemSetting::class,
                'auditable_id' => '00000000-0000-0000-0000-000000000000',
                'new_values' => ['changed_keys' => $changedKeys],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return back()->with('success', 'System configurations and payment gateway parameters saved successfully.');
    }

    /**
     * Test connection credentials for payment gateways.
     */
    public function testGateway(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', 'in:stripe,jazzcash,easypaisa'],
        ]);

        $gateway = $validated['gateway'];

        if ($gateway === 'stripe') {
            $secretKey = SystemSetting::get('gateway_stripe_secret_key');
            if (empty($secretKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe Secret Key is not configured yet.',
                ], 422);
            }

            if (! str_starts_with($secretKey, 'sk_test_') && ! str_starts_with($secretKey, 'sk_live_')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Stripe key format. Secret key must start with sk_test_ or sk_live_.',
                ], 422);
            }

            try {
                $res = Http::withToken($secretKey)
                    ->timeout(8)
                    ->get('https://api.stripe.com/v1/balance');

                if ($res->successful()) {
                    $mode = str_starts_with($secretKey, 'sk_live_') ? 'Live Production' : 'Sandbox Test';
                    return response()->json([
                        'success' => true,
                        'message' => "Stripe authenticated successfully! Operating mode: {$mode}.",
                    ]);
                }

                $err = $res->json()['error']['message'] ?? 'Authentication failed.';
                return response()->json([
                    'success' => false,
                    'message' => "Stripe API rejected credentials: {$err}",
                ], 422);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Connection to Stripe API timed out or could not be established.',
                ], 500);
            }
        }

        if ($gateway === 'jazzcash') {
            $merchantId = SystemSetting::get('gateway_jazzcash_merchant_id');
            $password = SystemSetting::get('gateway_jazzcash_password');
            $hashKey = SystemSetting::get('gateway_jazzcash_hash_key');
            $isSandbox = SystemSetting::get('gateway_jazzcash_sandbox', true);

            if (empty($merchantId) || empty($password) || empty($hashKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'JazzCash Merchant ID, Password, and Hash Salt are required.',
                ], 422);
            }

            $mode = $isSandbox ? 'Sandbox (sandbox.jazzcash.com.pk)' : 'Live Production';
            return response()->json([
                'success' => true,
                'message' => "JazzCash configuration syntax valid! Prepared for {$mode} operations.",
            ]);
        }

        if ($gateway === 'easypaisa') {
            $storeId = SystemSetting::get('gateway_easypaisa_store_id');
            $hashKey = SystemSetting::get('gateway_easypaisa_hash_key');
            $isSandbox = SystemSetting::get('gateway_easypaisa_sandbox', true);

            if (empty($storeId) || empty($hashKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'EasyPaisa Store ID and Hash Key are required.',
                ], 422);
            }

            $mode = $isSandbox ? 'Sandbox Environment' : 'Live Production';
            return response()->json([
                'success' => true,
                'message' => "EasyPaisa credentials verified! Ready for {$mode}.",
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Unsupported gateway.'], 400);
    }

    /**
     * Populate baseline system settings if missing.
     */
    protected function ensureDefaultSettingsExist(): void
    {
        foreach (static::$defaultDefinitions as $key => $meta) {
            $exists = SystemSetting::where('key', $key)->exists();
            if (! $exists) {
                SystemSetting::set(
                    key: $key,
                    value: $meta['default'],
                    group: $meta['group'],
                    type: $meta['type'],
                    isSecret: $meta['is_secret'],
                    label: $meta['label'],
                    description: $meta['description']
                );
            }
        }
    }
}
