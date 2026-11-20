<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountingSetting;
use App\Models\CashDrawerTransaction;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    /**
     * Standard Pakistani SME Retail/Distribution Chart of Accounts template.
     */
    protected const DEFAULT_ACCOUNTS = [
        // 1000: ASSETS
        [
            'code' => '1010',
            'name' => 'Cash & Cash Equivalents',
            'type' => 'asset',
            'sub_type' => 'cash',
            'normal_balance' => 'debit',
            'is_system' => true,
            'description' => 'Parent account for cash and liquid bank accounts',
            'children' => [
                [
                    'code' => '1011',
                    'name' => 'Main Cash Drawer / Till',
                    'type' => 'asset',
                    'sub_type' => 'cash',
                    'normal_balance' => 'debit',
                    'is_system' => true,
                    'description' => 'Physical currency notes and coins kept in the POS cash drawer',
                ],
                [
                    'code' => '1012',
                    'name' => 'Bank Account (Meezan Bank PKR)',
                    'type' => 'asset',
                    'sub_type' => 'bank',
                    'normal_balance' => 'debit',
                    'is_system' => true,
                    'description' => 'Operating business bank account for card and cheque settlements',
                ],
                [
                    'code' => '1013',
                    'name' => 'Raast & Digital Wallet Clearing',
                    'type' => 'asset',
                    'sub_type' => 'bank',
                    'normal_balance' => 'debit',
                    'is_system' => true,
                    'description' => 'Clearing account for instant P2M Raast and digital wallets',
                ],
            ],
        ],
        [
            'code' => '1030',
            'name' => 'Accounts Receivable (Customer Khata)',
            'type' => 'asset',
            'sub_type' => 'receivable',
            'normal_balance' => 'debit',
            'is_system' => true,
            'description' => 'Total credit sales (Udhaar) owed by customers',
        ],
        [
            'code' => '1040',
            'name' => 'Inventory Asset (Stock on Hand)',
            'type' => 'asset',
            'sub_type' => 'inventory',
            'normal_balance' => 'debit',
            'is_system' => true,
            'description' => 'Current commercial valuation of resale products in inventory',
        ],
        [
            'code' => '1510',
            'name' => 'POS Hardware & Computers',
            'type' => 'asset',
            'sub_type' => 'fixed_asset',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'Thermal printers, barcode scanners, cash drawers, and POS machines',
        ],
        [
            'code' => '1520',
            'name' => 'Store Furniture & Fixtures',
            'type' => 'asset',
            'sub_type' => 'fixed_asset',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'Display racks, counters, shelves, and lighting',
        ],

        // 2000: LIABILITIES
        [
            'code' => '2011',
            'name' => 'Accounts Payable (Supplier Khata)',
            'type' => 'liability',
            'sub_type' => 'payable',
            'normal_balance' => 'credit',
            'is_system' => true,
            'description' => 'Outstanding trade debts owed to distributors and suppliers for inventory purchases',
        ],
        [
            'code' => '2020',
            'name' => 'FBR Sales Tax Payable (GST)',
            'type' => 'liability',
            'sub_type' => 'tax',
            'normal_balance' => 'credit',
            'is_system' => true,
            'description' => 'Statutory sales tax collected from customers to be remitted to FBR IRIS',
        ],
        [
            'code' => '2021',
            'name' => 'Provincial Services Tax Payable (PRA / SRB)',
            'type' => 'liability',
            'sub_type' => 'tax',
            'normal_balance' => 'credit',
            'is_system' => false,
            'description' => 'Provincial service tax collected on services or food',
        ],
        [
            'code' => '2030',
            'name' => 'Customer Advance Deposits',
            'type' => 'liability',
            'sub_type' => 'payable',
            'normal_balance' => 'credit',
            'is_system' => false,
            'description' => 'Advances received from customers prior to delivery of goods',
        ],

        // 3000: EQUITY
        [
            'code' => '3010',
            'name' => "Owner's Capital",
            'type' => 'equity',
            'sub_type' => 'equity',
            'normal_balance' => 'credit',
            'is_system' => true,
            'description' => 'Initial and subsequent capital contributed by store proprietor or partners',
        ],
        [
            'code' => '3020',
            'name' => "Owner's Drawings",
            'type' => 'equity',
            'sub_type' => 'equity',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'Cash or inventory withdrawn by owner for personal use',
        ],
        [
            'code' => '3030',
            'name' => 'Retained Earnings',
            'type' => 'equity',
            'sub_type' => 'equity',
            'normal_balance' => 'credit',
            'is_system' => true,
            'description' => 'Accumulated net profit retained in the enterprise',
        ],

        // 4000: REVENUE
        [
            'code' => '4010',
            'name' => 'Gross Sales Revenue',
            'type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'normal_balance' => 'credit',
            'is_system' => true,
            'description' => 'Primary commercial revenue from point-of-sale customer receipts',
        ],
        [
            'code' => '4020',
            'name' => 'Sales Returns & Refunds',
            'type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'normal_balance' => 'debit',
            'is_system' => true,
            'description' => 'Contra-revenue account tracking customer product returns & statutory credit notes',
        ],
        [
            'code' => '4030',
            'name' => 'Sales Discounts Allowed',
            'type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'normal_balance' => 'debit',
            'is_system' => true,
            'description' => 'Contra-revenue account tracking promotional and cashier discount allowances',
        ],
        [
            'code' => '4040',
            'name' => 'Other Income / Scrap Sales',
            'type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'normal_balance' => 'credit',
            'is_system' => false,
            'description' => 'Incidental income from sale of cardboard cartons, delivery charges, etc.',
        ],

        // 5000: COST OF GOODS SOLD
        [
            'code' => '5010',
            'name' => 'Cost of Goods Sold (COGS)',
            'type' => 'expense',
            'sub_type' => 'direct_cost',
            'normal_balance' => 'debit',
            'is_system' => true,
            'description' => 'Direct cost of inventory sold according to Weighted Average Cost',
        ],
        [
            'code' => '5020',
            'name' => 'Inventory Spoilage, Damage & Shrinkage',
            'type' => 'expense',
            'sub_type' => 'direct_cost',
            'normal_balance' => 'debit',
            'is_system' => true,
            'description' => 'Losses due to damaged, defective, or expired stock write-offs',
        ],
        [
            'code' => '5030',
            'name' => 'Inward Freight & Cartage',
            'type' => 'expense',
            'sub_type' => 'direct_cost',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'Shipping and logistics carriage paid to receive inventory shipments',
        ],

        // 6000: OPERATING EXPENSES
        [
            'code' => '6010',
            'name' => 'Shop Rent Expense',
            'type' => 'expense',
            'sub_type' => 'operating_expense',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'Monthly rental expense for store premises',
        ],
        [
            'code' => '6020',
            'name' => 'Electricity, Gas & Fuel Expense',
            'type' => 'expense',
            'sub_type' => 'operating_expense',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'WAPDA/K-Electric utility bills and generator fuel',
        ],
        [
            'code' => '6030',
            'name' => 'Staff Salaries & Cashier Wages',
            'type' => 'expense',
            'sub_type' => 'operating_expense',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'Monthly remuneration paid to sales clerks and store employees',
        ],
        [
            'code' => '6040',
            'name' => 'Tea, Refreshments & Petty Cash Expense',
            'type' => 'expense',
            'sub_type' => 'operating_expense',
            'normal_balance' => 'debit',
            'is_system' => true,
            'description' => 'Daily staff tea, snacks, and minor cash drawer petty disbursements',
        ],
        [
            'code' => '6050',
            'name' => 'Repair & Maintenance',
            'type' => 'expense',
            'sub_type' => 'operating_expense',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'Store upkeep, electrical repairs, and fixture maintenance',
        ],
        [
            'code' => '6060',
            'name' => 'Software & Cloud Subscriptions',
            'type' => 'expense',
            'sub_type' => 'operating_expense',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'POS software licensing, cloud hosting, and internet bills',
        ],
        [
            'code' => '6070',
            'name' => 'Bank Charges & POS Machine Fees',
            'type' => 'expense',
            'sub_type' => 'operating_expense',
            'normal_balance' => 'debit',
            'is_system' => false,
            'description' => 'Debit/credit card swipe MDR commission and bank maintenance charges',
        ],
    ];

    /**
     * Provision default Pakistani Chart of Accounts and configure system mapping for a tenant.
     */
    public function provisionDefaultChartOfAccounts(Tenant $tenant): AccountingSetting
    {
        return DB::transaction(function () use ($tenant) {
            $createdAccounts = [];

            foreach (self::DEFAULT_ACCOUNTS as $accData) {
                $children = $accData['children'] ?? null;
                unset($accData['children']);

                $accData['tenant_id'] = $tenant->id;
                $parentAccount = Account::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $accData['code']],
                    $accData
                );
                $createdAccounts[$parentAccount->code] = $parentAccount;

                if (! empty($children)) {
                    foreach ($children as $childData) {
                        $childData['tenant_id'] = $tenant->id;
                        $childData['parent_id'] = $parentAccount->id;
                        $child = Account::withoutGlobalScopes()->updateOrCreate(
                            ['tenant_id' => $tenant->id, 'code' => $childData['code']],
                            $childData
                        );
                        $createdAccounts[$child->code] = $child;
                    }
                }
            }

            // Provision or update AccountingSetting
            return AccountingSetting::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'default_cash_account_id' => $createdAccounts['1011']?->id ?? null,
                    'default_bank_account_id' => $createdAccounts['1012']?->id ?? null,
                    'default_ar_account_id' => $createdAccounts['1030']?->id ?? null,
                    'default_inventory_account_id' => $createdAccounts['1040']?->id ?? null,
                    'default_ap_account_id' => $createdAccounts['2011']?->id ?? null,
                    'default_sales_tax_account_id' => $createdAccounts['2020']?->id ?? null,
                    'default_sales_revenue_account_id' => $createdAccounts['4010']?->id ?? null,
                    'default_sales_return_account_id' => $createdAccounts['4020']?->id ?? null,
                    'default_sales_discount_account_id' => $createdAccounts['4030']?->id ?? null,
                    'default_cogs_account_id' => $createdAccounts['5010']?->id ?? null,
                    'default_retained_earnings_account_id' => $createdAccounts['3030']?->id ?? null,
                ]
            );
        });
    }

    /**
     * Get or lazy-provision accounting settings for a tenant.
     */
    public function getSettings(Tenant|string $tenant): AccountingSetting
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $settings = AccountingSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $settings) {
            $t = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenantId);
            $settings = $this->provisionDefaultChartOfAccounts($t);
        }

        return $settings;
    }

    /**
     * Generate sequential Journal Entry number.
     */
    protected function generateEntryNumber(string $tenantId): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        return "JE-{$date}-{$random}";
    }

    /**
     * Post a completed POS Sale to the General Ledger.
     */
    public function postSale(Sale $sale): ?JournalEntry
    {
        // Avoid duplicate posting
        $existing = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $sale->tenant_id)
            ->where('source_type', 'sale')
            ->where('source_id', $sale->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $settings = $this->getSettings($sale->tenant_id);

        return DB::transaction(function () use ($sale, $settings) {
            $entry = JournalEntry::create([
                'tenant_id' => $sale->tenant_id,
                'entry_number' => $this->generateEntryNumber($sale->tenant_id),
                'entry_date' => $sale->created_at ? $sale->created_at->toDateString() : now()->toDateString(),
                'source_type' => 'sale',
                'source_id' => $sale->id,
                'reference' => $sale->sale_number,
                'description' => "Sale #{$sale->sale_number} ({$sale->payment_method})",
                'status' => 'draft',
                'created_by_user_id' => $sale->user_id,
            ]);

            // 1. Payment Settlement Debit (Cash, Bank, or Customer Khata AR)
            $paymentMethod = $sale->payment_method;
            if ($paymentMethod === 'credit') {
                $paymentAccountId = $settings->default_ar_account_id;
                $memo = "Customer Khata Receivable #{$sale->sale_number}";
            } elseif (in_array($paymentMethod, ['card', 'raast', 'wallet'])) {
                $paymentAccountId = $settings->default_bank_account_id;
                $memo = "Bank / Digital Settlement #{$sale->sale_number}";
            } else {
                $paymentAccountId = $settings->default_cash_account_id;
                $memo = "Cash in Drawer #{$sale->sale_number}";
            }

            $entry->lines()->create([
                'tenant_id' => $sale->tenant_id,
                'account_id' => $paymentAccountId,
                'debit_minor' => $sale->total_minor,
                'credit_minor' => 0,
                'memo' => $memo,
            ]);

            // 2. Gross Sales Revenue Credit
            $entry->lines()->create([
                'tenant_id' => $sale->tenant_id,
                'account_id' => $settings->default_sales_revenue_account_id,
                'debit_minor' => 0,
                'credit_minor' => $sale->subtotal_minor,
                'memo' => "Sales Revenue #{$sale->sale_number}",
            ]);

            // 3. Sales Tax Payable Credit (if tax was collected)
            if ($sale->tax_minor > 0) {
                $entry->lines()->create([
                    'tenant_id' => $sale->tenant_id,
                    'account_id' => $settings->default_sales_tax_account_id,
                    'debit_minor' => 0,
                    'credit_minor' => $sale->tax_minor,
                    'memo' => "FBR Sales Tax Payable #{$sale->sale_number}",
                ]);
            }

            // 4. Cost of Goods Sold (COGS) & Inventory Asset Reduction
            // Calculate total cost from sale items
            $sale->loadMissing('items.product');
            $totalCostMinor = 0;
            foreach ($sale->items as $item) {
                $unitCost = $item->product?->cost_minor ?? 0;
                $totalCostMinor += (int) round($item->quantity * $unitCost);
            }

            if ($totalCostMinor > 0) {
                // Debit COGS
                $entry->lines()->create([
                    'tenant_id' => $sale->tenant_id,
                    'account_id' => $settings->default_cogs_account_id,
                    'debit_minor' => $totalCostMinor,
                    'credit_minor' => 0,
                    'memo' => "Cost of Goods Sold #{$sale->sale_number}",
                ]);

                // Credit Inventory Asset
                $entry->lines()->create([
                    'tenant_id' => $sale->tenant_id,
                    'account_id' => $settings->default_inventory_account_id,
                    'debit_minor' => 0,
                    'credit_minor' => $totalCostMinor,
                    'memo' => "Inventory De-stocking #{$sale->sale_number}",
                ]);
            }

            return $entry->post();
        });
    }

    /**
     * Post a Sales Return / FBR Credit Note to the General Ledger.
     */
    public function postSaleReturn(SaleReturn $saleReturn): ?JournalEntry
    {
        $existing = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $saleReturn->tenant_id)
            ->where('source_type', 'sale_return')
            ->where('source_id', $saleReturn->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $settings = $this->getSettings($saleReturn->tenant_id);

        return DB::transaction(function () use ($saleReturn, $settings) {
            $entry = JournalEntry::create([
                'tenant_id' => $saleReturn->tenant_id,
                'entry_number' => $this->generateEntryNumber($saleReturn->tenant_id),
                'entry_date' => $saleReturn->created_at ? $saleReturn->created_at->toDateString() : now()->toDateString(),
                'source_type' => 'sale_return',
                'source_id' => $saleReturn->id,
                'reference' => $saleReturn->return_number,
                'description' => "Sales Return #{$saleReturn->return_number} (Ref: {$saleReturn->sale?->sale_number})",
                'status' => 'draft',
                'created_by_user_id' => $saleReturn->user_id,
            ]);

            // 1. Debit Sales Returns & Allowances (Contra-Revenue)
            $entry->lines()->create([
                'tenant_id' => $saleReturn->tenant_id,
                'account_id' => $settings->default_sales_return_account_id,
                'debit_minor' => $saleReturn->subtotal_minor,
                'credit_minor' => 0,
                'memo' => "Sales Return Reversal #{$saleReturn->return_number}",
            ]);

            // 2. Debit FBR Sales Tax Payable (Reduces tax liability)
            if ($saleReturn->tax_minor > 0) {
                $entry->lines()->create([
                    'tenant_id' => $saleReturn->tenant_id,
                    'account_id' => $settings->default_sales_tax_account_id,
                    'debit_minor' => $saleReturn->tax_minor,
                    'credit_minor' => 0,
                    'memo' => "FBR Sales Tax Liability Reduction #{$saleReturn->return_number}",
                ]);
            }

            // 3. Credit Refund Settlement (Cash, Bank, or Customer Khata)
            $refundMethod = $saleReturn->refund_method;
            if ($refundMethod === 'credit_note_khata') {
                $refundAccountId = $settings->default_ar_account_id;
                $memo = "Customer Khata Credit Note #{$saleReturn->return_number}";
            } else {
                $refundAccountId = $settings->default_cash_account_id;
                $memo = "Cash Drawer Refund #{$saleReturn->return_number}";
            }

            $entry->lines()->create([
                'tenant_id' => $saleReturn->tenant_id,
                'account_id' => $refundAccountId,
                'debit_minor' => 0,
                'credit_minor' => $saleReturn->total_refund_minor,
                'memo' => $memo,
            ]);

            // 4. Restocking / Inventory adjustment
            $saleReturn->loadMissing('items.product');
            $restockCostMinor = 0;
            $damagedCostMinor = 0;

            foreach ($saleReturn->items as $item) {
                $unitCost = $item->product?->cost_minor ?? 0;
                $cost = (int) round($item->quantity_returned * $unitCost);
                if ($item->restock_condition === 'restock_shelf') {
                    $restockCostMinor += $cost;
                } else {
                    $damagedCostMinor += $cost;
                }
            }

            // Resellable: Re-capitalize to Inventory Asset and credit COGS
            if ($restockCostMinor > 0) {
                $entry->lines()->create([
                    'tenant_id' => $saleReturn->tenant_id,
                    'account_id' => $settings->default_inventory_account_id,
                    'debit_minor' => $restockCostMinor,
                    'credit_minor' => 0,
                    'memo' => "Inventory Restock #{$saleReturn->return_number}",
                ]);

                $entry->lines()->create([
                    'tenant_id' => $saleReturn->tenant_id,
                    'account_id' => $settings->default_cogs_account_id,
                    'debit_minor' => 0,
                    'credit_minor' => $restockCostMinor,
                    'memo' => "COGS Reversal on Return #{$saleReturn->return_number}",
                ]);
            }

            // Damaged/Discard: Transfer to Spoilage & Damaged Goods expense
            if ($damagedCostMinor > 0) {
                $spoilageAccount = Account::withoutGlobalScopes()
                    ->where('tenant_id', $saleReturn->tenant_id)
                    ->where('code', '5020')
                    ->first();

                if ($spoilageAccount) {
                    $entry->lines()->create([
                        'tenant_id' => $saleReturn->tenant_id,
                        'account_id' => $spoilageAccount->id,
                        'debit_minor' => $damagedCostMinor,
                        'credit_minor' => 0,
                        'memo' => "Damaged / Expired Return Spoilage #{$saleReturn->return_number}",
                    ]);

                    $entry->lines()->create([
                        'tenant_id' => $saleReturn->tenant_id,
                        'account_id' => $settings->default_cogs_account_id,
                        'debit_minor' => 0,
                        'credit_minor' => $damagedCostMinor,
                        'memo' => "COGS Transfer to Spoilage #{$saleReturn->return_number}",
                    ]);
                }
            }

            return $entry->post();
        });
    }

    /**
     * Post Goods Received Note (GRN) to the General Ledger.
     */
    public function postGoodsReceipt(PurchaseOrder $po, int $billTotalMinor): ?JournalEntry
    {
        $existing = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $po->tenant_id)
            ->where('source_type', 'purchase_order_grn')
            ->where('source_id', $po->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $settings = $this->getSettings($po->tenant_id);

        return DB::transaction(function () use ($po, $billTotalMinor, $settings) {
            $entry = JournalEntry::create([
                'tenant_id' => $po->tenant_id,
                'entry_number' => $this->generateEntryNumber($po->tenant_id),
                'entry_date' => now()->toDateString(),
                'source_type' => 'purchase_order_grn',
                'source_id' => $po->id,
                'reference' => $po->po_number,
                'description' => "Goods Received (GRN) for PO #{$po->po_number} from {$po->supplier?->name}",
                'status' => 'draft',
                'created_by_user_id' => $po->user_id,
            ]);

            // 1. Debit Inventory Asset (Increase on-hand stock valuation)
            $entry->lines()->create([
                'tenant_id' => $po->tenant_id,
                'account_id' => $settings->default_inventory_account_id,
                'debit_minor' => $billTotalMinor,
                'credit_minor' => 0,
                'memo' => "Inventory Asset Inflow PO #{$po->po_number}",
            ]);

            // 2. Credit Accounts Payable (Supplier Khata)
            $entry->lines()->create([
                'tenant_id' => $po->tenant_id,
                'account_id' => $settings->default_ap_account_id,
                'debit_minor' => 0,
                'credit_minor' => $billTotalMinor,
                'memo' => "Accounts Payable to {$po->supplier?->name}",
            ]);

            return $entry->post();
        });
    }

    /**
     * Post a Supplier Payment (Disbursement) to the General Ledger.
     */
    public function postSupplierPayment(Supplier $supplier, int $amountMinor, string $paymentMethod = 'cash', ?string $reference = null, ?User $user = null): JournalEntry
    {
        $settings = $this->getSettings($supplier->tenant_id);

        return DB::transaction(function () use ($supplier, $amountMinor, $paymentMethod, $reference, $user, $settings) {
            $entry = JournalEntry::create([
                'tenant_id' => $supplier->tenant_id,
                'entry_number' => $this->generateEntryNumber($supplier->tenant_id),
                'entry_date' => now()->toDateString(),
                'source_type' => 'supplier_payment',
                'source_id' => $supplier->id,
                'reference' => $reference ?? "SUP-PAY-{$supplier->id}",
                'description' => "Supplier Debt Payment to {$supplier->name} ({$paymentMethod})",
                'status' => 'draft',
                'created_by_user_id' => $user?->id,
            ]);

            // 1. Debit Accounts Payable (Reduces vendor liability)
            $entry->lines()->create([
                'tenant_id' => $supplier->tenant_id,
                'account_id' => $settings->default_ap_account_id,
                'debit_minor' => $amountMinor,
                'credit_minor' => 0,
                'memo' => "Payable Settlement to {$supplier->name}",
            ]);

            // 2. Credit Bank or Cash Account
            $disbursementAccountId = in_array($paymentMethod, ['bank', 'cheque', 'online'])
                ? $settings->default_bank_account_id
                : $settings->default_cash_account_id;

            $entry->lines()->create([
                'tenant_id' => $supplier->tenant_id,
                'account_id' => $disbursementAccountId,
                'debit_minor' => 0,
                'credit_minor' => $amountMinor,
                'memo' => "Disbursement for {$supplier->name} ({$paymentMethod})",
            ]);

            return $entry->post();
        });
    }

    /**
     * Post a Customer Debt Collection (Khata Payment) to the General Ledger.
     */
    public function postCustomerPayment(Customer $customer, int $amountMinor, string $paymentMethod = 'cash', ?string $reference = null, ?User $user = null): JournalEntry
    {
        $settings = $this->getSettings($customer->tenant_id);

        return DB::transaction(function () use ($customer, $amountMinor, $paymentMethod, $reference, $user, $settings) {
            $entry = JournalEntry::create([
                'tenant_id' => $customer->tenant_id,
                'entry_number' => $this->generateEntryNumber($customer->tenant_id),
                'entry_date' => now()->toDateString(),
                'source_type' => 'customer_payment',
                'source_id' => $customer->id,
                'reference' => $reference ?? "CUST-REC-{$customer->id}",
                'description' => "Customer Debt Collection from {$customer->name} ({$paymentMethod})",
                'status' => 'draft',
                'created_by_user_id' => $user?->id,
            ]);

            // 1. Debit Cash or Bank Account
            $receiptAccountId = in_array($paymentMethod, ['bank', 'online', 'cheque'])
                ? $settings->default_bank_account_id
                : $settings->default_cash_account_id;

            $entry->lines()->create([
                'tenant_id' => $customer->tenant_id,
                'account_id' => $receiptAccountId,
                'debit_minor' => $amountMinor,
                'credit_minor' => 0,
                'memo' => "Cash Inflow from {$customer->name} ({$paymentMethod})",
            ]);

            // 2. Credit Accounts Receivable (Reduces customer debt)
            $entry->lines()->create([
                'tenant_id' => $customer->tenant_id,
                'account_id' => $settings->default_ar_account_id,
                'debit_minor' => 0,
                'credit_minor' => $amountMinor,
                'memo' => "Khata Receivable Reduction for {$customer->name}",
            ]);

            return $entry->post();
        });
    }

    /**
     * Post a Shift Cash Drawer Petty Cash Payout (Expense) to the General Ledger.
     */
    public function postDrawerPayout(CashDrawerTransaction $transaction, ?string $expenseAccountId = null): JournalEntry
    {
        $settings = $this->getSettings($transaction->tenant_id);

        return DB::transaction(function () use ($transaction, $expenseAccountId, $settings) {
            // Default to 6040 Tea/Entertainment petty expense if none provided
            if (! $expenseAccountId) {
                $teaAccount = Account::withoutGlobalScopes()
                    ->where('tenant_id', $transaction->tenant_id)
                    ->where('code', '6040')
                    ->first();
                $expenseAccountId = $teaAccount?->id ?? $settings->default_cogs_account_id;
            }

            $entry = JournalEntry::create([
                'tenant_id' => $transaction->tenant_id,
                'entry_number' => $this->generateEntryNumber($transaction->tenant_id),
                'entry_date' => $transaction->created_at ? $transaction->created_at->toDateString() : now()->toDateString(),
                'source_type' => 'cash_drawer',
                'source_id' => $transaction->id,
                'reference' => "DRAWER-EXP-{$transaction->id}",
                'description' => "Cash Drawer Petty Expense: {$transaction->reason}",
                'status' => 'draft',
                'created_by_user_id' => $transaction->user_id,
            ]);

            // 1. Debit Expense Account
            $entry->lines()->create([
                'tenant_id' => $transaction->tenant_id,
                'account_id' => $expenseAccountId,
                'debit_minor' => $transaction->amount_minor,
                'credit_minor' => 0,
                'memo' => "Petty Payout: {$transaction->reason}",
            ]);

            // 2. Credit Main Cash Drawer
            $entry->lines()->create([
                'tenant_id' => $transaction->tenant_id,
                'account_id' => $settings->default_cash_account_id,
                'debit_minor' => 0,
                'credit_minor' => $transaction->amount_minor,
                'memo' => "Drawer Cash Outflow: {$transaction->reason}",
            ]);

            return $entry->post();
        });
    }

    /**
     * Post a Manual Journal Entry created by accountant / store manager.
     */
    public function postManualJournal(array $data, User $user): JournalEntry
    {
        $tenantId = $user->tenant_id;

        return DB::transaction(function () use ($data, $user, $tenantId) {
            $entryNumber = $this->generateEntryNumber($tenantId);

            $entry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'entry_number' => $entryNumber,
                'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                'source_type' => 'manual',
                'source_id' => null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? 'Manual Journal Entry',
                'status' => 'draft',
                'created_by_user_id' => $user->id,
            ]);

            foreach ($data['lines'] as $line) {
                $debit = (int) ($line['debit_minor'] ?? 0);
                $credit = (int) ($line['credit_minor'] ?? 0);

                if ($debit <= 0 && $credit <= 0) {
                    continue;
                }

                $entry->lines()->create([
                    'tenant_id' => $tenantId,
                    'account_id' => $line['account_id'],
                    'debit_minor' => $debit,
                    'credit_minor' => $credit,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry->post();
        });
    }

    /**
     * Retroactively synchronize all unposted commercial transactions into the General Ledger.
     */
    public function syncExistingTransactions(Tenant $tenant): array
    {
        $this->provisionDefaultChartOfAccounts($tenant);

        $salesCount = 0;
        $returnsCount = 0;
        $grnCount = 0;

        // 1. Sync completed sales
        $sales = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->get();

        foreach ($sales as $sale) {
            $hasEntry = JournalEntry::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('source_type', 'sale')
                ->where('source_id', $sale->id)
                ->exists();

            if (! $hasEntry) {
                $this->postSale($sale);
                $salesCount++;
            }
        }

        // 2. Sync sales returns
        $returns = SaleReturn::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get();

        foreach ($returns as $return) {
            $hasEntry = JournalEntry::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('source_type', 'sale_return')
                ->where('source_id', $return->id)
                ->exists();

            if (! $hasEntry) {
                $this->postSaleReturn($return);
                $returnsCount++;
            }
        }

        // 3. Sync purchase orders received
        $purchaseOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['received', 'partially_received'])
            ->get();

        foreach ($purchaseOrders as $po) {
            $hasEntry = JournalEntry::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('source_type', 'purchase_order_grn')
                ->where('source_id', $po->id)
                ->exists();

            if (! $hasEntry) {
                $this->postGoodsReceipt($po, $po->total_minor);
                $grnCount++;
            }
        }

        return [
            'sales_synced' => $salesCount,
            'returns_synced' => $returnsCount,
            'grn_synced' => $grnCount,
        ];
    }
}
