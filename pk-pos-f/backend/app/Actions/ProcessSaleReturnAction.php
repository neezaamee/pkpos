<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessSaleReturnAction
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Process a verified sales return, update physical stock, issue refund,
     * and generate statutory FBR Credit Note.
     *
     * @param array<string, mixed> $data
     */
    public function execute(array $data, User $user, ?RegisterSession $activeSession = null): SaleReturn
    {
        return DB::transaction(function () use ($data, $user, $activeSession) {
            $tenantId = $user->tenant_id;

            /** @var Sale $sale */
            $sale = Sale::where('tenant_id', $tenantId)
                ->where('id', $data['sale_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $saleItems = $sale->items()->get()->keyBy('id');

            // Gather already returned quantities for each item
            $returnedQuantities = SaleReturnItem::whereIn('sale_item_id', $saleItems->keys())
                ->groupBy('sale_item_id')
                ->selectRaw('sale_item_id, sum(quantity_returned) as total_returned')
                ->pluck('total_returned', 'sale_item_id');

            $itemsData = [];
            $subtotalMinor = 0;
            $taxMinor = 0;

            foreach ($data['items'] as $item) {
                $saleItemId = $item['sale_item_id'] ?? null;
                $qty = (float) ($item['quantity_returned'] ?? 0);
                $condition = $item['restock_condition'] ?? 'restock_shelf';

                if (! $saleItemId || $qty <= 0 || ! isset($saleItems[$saleItemId])) {
                    continue;
                }

                /** @var SaleItem $saleItem */
                $saleItem = $saleItems[$saleItemId];
                $alreadyReturned = (float) ($returnedQuantities[$saleItemId] ?? 0);
                $soldQty = (float) $saleItem->quantity;
                $remainingReturnable = max(0, $soldQty - $alreadyReturned);

                if ($qty > $remainingReturnable) {
                    throw ValidationException::withMessages([
                        'items' => ["Cannot return {$qty} units for '{$saleItem->product_name}'. Max returnable is {$remainingReturnable}."],
                    ]);
                }

                $conversionFactor = (float) ($saleItem->unit_conversion_factor ?: 1.0);
                $unitPrice = (int) $saleItem->unit_price_minor;
                $taxBps = (int) ($saleItem->tax_rate_basis_points ?: 0);

                $lineSubtotal = (int) round($qty * $unitPrice);
                $lineTax = (int) round(($lineSubtotal * $taxBps) / 10000);
                $lineRefund = $lineSubtotal + $lineTax;

                $subtotalMinor += $lineSubtotal;
                $taxMinor += $lineTax;

                $itemsData[] = [
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'unit_code' => $saleItem->unit_code ?: 'pcs',
                    'unit_conversion_factor' => $conversionFactor,
                    'quantity_returned' => $qty,
                    'unit_price_minor' => $unitPrice,
                    'tax_rate_basis_points' => $taxBps,
                    'tax_minor' => $lineTax,
                    'refund_amount_minor' => $lineRefund,
                    'restock_condition' => $condition,
                ];
            }

            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => ['No valid items selected for return.'],
                ]);
            }

            $totalRefundMinor = $subtotalMinor + $taxMinor;

            // 1. Generate unique return sequence number
            $dateNum = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $returnNumber = "RET-{$dateNum}-{$random}";

            // 2. Generate statutory FBR Credit Note USIN (InvoiceType = 2)
            $refUsin = $sale->fbr_usin ?: $sale->sale_number;
            $fbrCreditNoteNumber = "CN-{$refUsin}-{$random}";

            $tenant = Tenant::findOrFail($tenantId);
            $fbrQrData = json_encode([
                'InvoiceType' => 2, // 2 = Credit Note (FBR POS Specification)
                'RefUSIN' => $refUsin,
                'CreditNoteNo' => $fbrCreditNoteNumber,
                'POSID' => $tenant->fbr_pos_id ?? 'POS-001',
                'DateTime' => now()->toIso8601String(),
                'RefundAmount' => $totalRefundMinor / 100,
            ]);

            // 3. Create Sale Return Header
            $saleReturn = SaleReturn::create([
                'tenant_id' => $tenantId,
                'sale_id' => $sale->id,
                'location_id' => $sale->location_id,
                'user_id' => $user->id,
                'customer_id' => $sale->customer_id,
                'register_session_id' => $activeSession?->id,
                'return_number' => $returnNumber,
                'reason' => $data['reason'] ?? 'customer_change_of_mind',
                'refund_method' => $data['refund_method'] ?? 'cash',
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'total_refund_minor' => $totalRefundMinor,
                'fbr_status' => 'submitted',
                'fbr_credit_note_number' => $fbrCreditNoteNumber,
                'fbr_credit_note_qr' => $fbrQrData,
                'notes' => $data['notes'] ?? null,
            ]);

            // 4. Create items and restock physical inventory
            foreach ($itemsData as $itemRow) {
                $saleReturn->items()->create($itemRow);

                // If condition is restock_shelf, return physical stock to on-hand inventory
                if ($itemRow['restock_condition'] === 'restock_shelf' && ! empty($itemRow['product_id'])) {
                    $baseRestockQty = $itemRow['quantity_returned'] * $itemRow['unit_conversion_factor'];

                    $this->stockService->recordMovement(
                        $tenant,
                        $itemRow['product_id'],
                        $sale->location_id,
                        'return',
                        $baseRestockQty, // positive restock
                        'sale_return',
                        $saleReturn->id,
                        "Sales Return #{$returnNumber} from Invoice #{$sale->sale_number}",
                        $user->id
                    );
                }
            }

            // 5. Financial settlement
            $refundMethod = $data['refund_method'] ?? 'cash';

            if ($refundMethod === 'cash' && $activeSession) {
                // Deduct cash from active cash drawer
                $activeSession->recordCashMovement(
                    'refund',
                    $totalRefundMinor,
                    $user,
                    "Cash Refund for Return #{$returnNumber}"
                );
            } elseif ($refundMethod === 'credit_note_khata' && $sale->customer_id) {
                // Credit customer Khata ledger (reduces customer's outstanding balance)
                $customer = Customer::withoutGlobalScopes()->find($sale->customer_id);
                if ($customer) {
                    $customer->recordReturnCredit($saleReturn, $totalRefundMinor, $user);
                }
            }

            // Auto-post to General Ledger
            try {
                app(\App\Services\AccountingService::class)->postSaleReturn($saleReturn);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Accounting auto-post skipped for return {$saleReturn->id}: " . $e->getMessage());
            }

            return $saleReturn->load(['items.product', 'sale', 'customer', 'location', 'user']);
        });
    }
}
