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
    public function execute(array $data, \App\Models\User|string|null $userId = null): Sale
    {
        $resolvedUserId = $userId instanceof \App\Models\User ? $userId->id : $userId;

        return DB::transaction(function () use ($data, $resolvedUserId) {
            $items = $data['items'];
            unset($data['items']);

            $payments = $data['payments'] ?? null;
            unset($data['payments']);

            $prescriptionData = $data['prescription'] ?? null;
            unset($data['prescription']);

            if ($resolvedUserId) {
                $data['user_id'] = $resolvedUserId;
                if (empty($data['tenant_id'])) {
                    $u = \App\Models\User::withoutGlobalScopes()->find($resolvedUserId);
                    $data['tenant_id'] = $u?->tenant_id;
                }
            }

            if (!empty($payments) && is_array($payments)) {
                if (count($payments) > 1) {
                    $data['payment_method'] = 'split';
                } elseif (empty($data['payment_method'])) {
                    $data['payment_method'] = $payments[0]['payment_method'] ?? 'cash';
                }
            }

            if (!isset($data['subtotal_minor']) || !isset($data['total_minor'])) {
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

            // Calculate Service Charge if configured (e.g. Dine-In 5% or 10%)
            $serviceChargeBps = (int) ($data['service_charge_basis_points'] ?? 0);
            if ($serviceChargeBps > 0 && empty($data['service_charge_minor'])) {
                $charge = (int) round(($data['subtotal_minor'] * $serviceChargeBps) / 10000);
                $data['service_charge_minor'] = $charge;
                $data['total_minor'] += $charge;
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
            $batchService = app(\App\Services\Pharmacy\BatchService::class);
            $requiresPrescription = false;

            foreach ($items as $item) {
                $prod = null;
                if (!empty($item['product_id'])) {
                    $prod = Product::find($item['product_id']);
                }

                if ($prod && ($prod->is_prescription_required || $prod->is_narcotic)) {
                    $requiresPrescription = true;
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
                $item['tax_rate_basis_points'] = $taxBps;
                $item['tax_amount_minor'] = $item['tax_amount_minor'] ?? (int) round(($qty * $unitPrice * $taxBps) / 10000);
                $item['line_total_minor'] = $item['line_total_minor'] ?? (int) round($qty * $unitPrice) + $item['tax_amount_minor'];

                // Batch & FEFO resolution:
                $batch = null;
                if (!empty($item['batch_id'])) {
                    $batch = \App\Models\ProductBatch::find($item['batch_id']);
                } elseif ($prod) {
                    $batch = $prod->fefoBatch($sale->location_id);
                }

                if ($batch) {
                    if ($batch->isExpired()) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => ["Statutory violation: Batch {$batch->batch_number} for {$item['product_name']} expired on {$batch->expiry_date->format('d-M-Y')} and cannot be dispensed."],
                        ]);
                    }

                    $item['batch_id'] = $batch->id;
                    $item['batch_number'] = $batch->batch_number;
                    $item['expiry_date'] = $batch->expiry_date->toDateString();
                }

                $item['selected_modifiers'] = $item['selected_modifiers'] ?? null;
                $item['cooking_notes'] = $item['cooking_notes'] ?? null;
                $item['kitchen_station_id'] = $item['kitchen_station_id'] ?? $prod?->kitchen_station_id ?? null;

                $sale->items()->create($item);

                // Auto-deduct inventory in base units (taking conversion_factor into account)
                if ($tenant && !empty($item['product_id']) && !empty($sale->location_id)) {
                    $conversionFactor = (float) ($item['unit_conversion_factor'] ?? 1.0);
                    $deductedQty = ((float) $item['quantity']) * ($conversionFactor ?: 1.0);

                    if ($batch) {
                        try {
                            $batchService->deductBatchStock($batch, $deductedQty);
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::warning("Batch stock deduction skipped: " . $e->getMessage());
                        }
                    }

                    try {
                        $stockService->recordMovement(
                            $tenant,
                            $item['product_id'],
                            $sale->location_id,
                            'sale',
                            -$deductedQty,
                            'sale',
                            $sale->id,
                            "POS Sale #{$sale->sale_number}" . ($batch ? " (Batch: {$batch->batch_number})" : ''),
                            $userId
                        );
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("Stock movement skipped for item {$item['product_id']}: " . $e->getMessage());
                    }
                }
            }

            // Record tender payment records (sale_payments)
            if (!empty($payments) && is_array($payments)) {
                foreach ($payments as $p) {
                    $sale->payments()->create([
                        'tenant_id' => $sale->tenant_id,
                        'payment_method' => $p['payment_method'],
                        'amount_minor' => (int) $p['amount_minor'],
                        'tendered_minor' => isset($p['tendered_minor']) ? (int) $p['tendered_minor'] : (int) $p['amount_minor'],
                        'change_minor' => (int) ($p['change_minor'] ?? 0),
                        'reference' => $p['reference'] ?? $p['reference_number'] ?? null,
                        'notes' => $p['notes'] ?? null,
                    ]);
                }
            } else {
                $sale->payments()->create([
                    'tenant_id' => $sale->tenant_id,
                    'payment_method' => $sale->payment_method ?: 'cash',
                    'amount_minor' => $sale->total_minor,
                    'tendered_minor' => $sale->total_minor,
                    'change_minor' => 0,
                    'reference' => null,
                    'notes' => null,
                ]);
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

            // If credit sale or customer attached, handle Khata ledger (works for full or split credit)
            $creditAmount = $sale->payments()->where('payment_method', 'credit')->sum('amount_minor');
            if ($creditAmount > 0) {
                if (empty($sale->customer_id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'customer_id' => ['A customer must be selected for credit / Khata payment.'],
                    ]);
                }

                $customer = \App\Models\Customer::where('tenant_id', $sale->tenant_id)
                    ->where('id', $sale->customer_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$customer->canAcceptCredit($creditAmount)) {
                    $limitRs = number_format($customer->credit_limit_minor / 100);
                    $balanceRs = number_format($customer->current_balance_minor / 100);
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'payment_method' => ["Credit limit exceeded. Customer balance is Rs. {$balanceRs}, limit is Rs. {$limitRs}."],
                    ]);
                }

                $user = $resolvedUserId ? \App\Models\User::withoutGlobalScopes()->find($resolvedUserId) : null;
                $customer->chargeCreditSale($sale, $creditAmount, $user);
            }

            // Auto-post to General Ledger
            try {
                app(\App\Services\AccountingService::class)->postSale($sale);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Accounting auto-post skipped for sale {$sale->id}: " . $e->getMessage());
            }

            // Prescription validation & Controlled substance (Narcotic) register logging
            if ($requiresPrescription && empty($prescriptionData)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'prescription' => ['Doctor prescription details (Doctor Name & PMDC #) are mandatory for dispensing prescription or narcotic medicines.'],
                ]);
            }

            if (!empty($prescriptionData)) {
                $batchService->recordPrescriptionAndNarcotics($tenant, $sale, $prescriptionData, $resolvedUserId);
            }

            // Restaurant KOT Dispatch
            $fireKot = $data['fire_kot'] ?? false;
            if ($fireKot) {
                app(\App\Services\Restaurant\KitchenOrderService::class)->fireKitchenOrders($sale);
            }

            // Restaurant Table Lifecycle
            if ($sale->restaurant_table_id) {
                $table = \App\Models\RestaurantTable::find($sale->restaurant_table_id);
                if ($table) {
                    if ($sale->status === 'completed') {
                        $table->release();
                    } else {
                        $table->occupy($sale);
                    }
                }
            }

            // Hardware Delivery Challan Auto-Dispatch
            if (!empty($data['create_delivery_challan'])) {
                $challanData = is_array($data['delivery_challan'] ?? null) ? $data['delivery_challan'] : [];
                app(\App\Services\Hardware\HardwareBuildingService::class)->createDeliveryChallan($sale, $challanData);
            }

            return $sale->load(['items.batch', 'payments', 'prescription.narcoticEntries', 'kitchenOrders.items', 'restaurantTable', 'deliveryChallans']);
        });
    }
}
