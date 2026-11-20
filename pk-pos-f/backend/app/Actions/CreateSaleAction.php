<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\RegisterSession;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class CreateSaleAction
{
    /**
     * Create a sale with items in a single database transaction.
     *
     * @param array<string, mixed> $data
     * @param string|null $userId
     * @return Sale
     */
    public function execute(array $data, ?string $userId = null): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = $data['items'];
            unset($data['items']);

            if ($userId) {
                $data['user_id'] = $userId;
            }

            if (! isset($data['subtotal_minor']) || ! isset($data['total_minor'])) {
                $subtotal = 0;
                $tax = 0;
                $total = 0;
                foreach ($items as $item) {
                    $qty = (float) ($item['quantity'] ?? 1);
                    $price = (int) ($item['unit_price_minor'] ?? 0);
                    $taxBps = (int) ($item['tax_rate_basis_points'] ?? 0);
                    $lineTax = (int) ($item['tax_amount_minor'] ?? round(($qty * $price * $taxBps) / 10000));
                    $lineTotal = (int) ($item['line_total_minor'] ?? (round($qty * $price) + $lineTax));
                    $total += $lineTotal;
                    $tax += $lineTax;
                    $subtotal += ($lineTotal - $lineTax);
                }
                $data['subtotal_minor'] = $data['subtotal_minor'] ?? $subtotal;
                $data['tax_minor'] = $data['tax_minor'] ?? $tax;
                $data['total_minor'] = $data['total_minor'] ?? $total;
            }

            $data['status'] = $data['status'] ?? 'completed';

            if (empty($data['sale_number'])) {
                $date = now()->format('Ymd');
                $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
                $data['sale_number'] = "INV-{$date}-{$rand}";
            }

            $sale = Sale::create($data);

            $tenant = \App\Models\Tenant::find($sale->tenant_id);
            $stockService = app(\App\Services\Inventory\StockService::class);

            foreach ($items as $item) {
                $prod = null;
                if (! empty($item['product_id'])) {
                    $prod = Product::find($item['product_id']);
                }

                if (empty($item['hs_code'])) {
                    $item['hs_code'] = $prod?->hs_code ?? '21039000';
                }
                if (empty($item['product_name'])) {
                    $item['product_name'] = $prod?->name ?? 'Standard Item';
                }

                $qty = (float) ($item['quantity'] ?? 1);
                $unitPrice = (int) ($item['unit_price_minor'] ?? 0);
                $taxBps = (int) ($item['tax_rate_basis_points'] ?? 0);
                $item['tax_amount_minor'] = $item['tax_amount_minor'] ?? (int) round(($qty * $unitPrice * $taxBps) / 10000);
                $item['line_total_minor'] = $item['line_total_minor'] ?? (int) round($qty * $unitPrice) + $item['tax_amount_minor'];

                $sale->items()->create($item);

                // Auto-deduct inventory in base units (taking conversion_factor into account)
                if ($tenant && ! empty($item['product_id']) && ! empty($sale->location_id)) {
                    $conversionFactor = (float) ($item['unit_conversion_factor'] ?? 1.0);
                    $deductedQty = ((float) $item['quantity']) * ($conversionFactor ?: 1.0);

                    try {
                        $stockService->recordMovement(
                            $tenant,
                            $item['product_id'],
                            $sale->location_id,
                            'sale',
                            -$deductedQty,
                            'sale',
                            $sale->id,
                            "POS Sale #{$sale->sale_number}",
                            $userId
                        );
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("Stock movement skipped for item {$item['product_id']}: " . $e->getMessage());
                    }
                }
            }

            // Record sale in active register session if one exists
            $activeSession = RegisterSession::where('tenant_id', $sale->tenant_id)
                ->where('location_id', $sale->location_id)
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();

            if ($activeSession) {
                $activeSession->recordSale($sale);
            }

            // If credit sale or customer attached, handle Khata ledger
            if (! empty($sale->customer_id) && $sale->payment_method === 'credit') {
                $customer = \App\Models\Customer::where('tenant_id', $sale->tenant_id)
                    ->where('id', $sale->customer_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $customer->canAcceptCredit($sale->total_minor)) {
                    $limitRs = number_format($customer->credit_limit_minor / 100);
                    $balanceRs = number_format($customer->current_balance_minor / 100);
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'payment_method' => ["Credit limit exceeded. Customer balance is Rs. {$balanceRs}, limit is Rs. {$limitRs}."],
                    ]);
                }

                $user = \App\Models\User::find($userId);
                $customer->chargeCreditSale($sale, $sale->total_minor, $user);
            }

            // Auto-post to General Ledger
            try {
                app(\App\Services\AccountingService::class)->postSale($sale);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Accounting auto-post skipped for sale {$sale->id}: " . $e->getMessage());
            }

            return $sale->load('items');
        });
    }
}
