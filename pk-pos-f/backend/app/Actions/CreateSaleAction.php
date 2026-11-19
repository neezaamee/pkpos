<?php

namespace App\Actions;

use App\Models\RegisterSession;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class CreateSaleAction
{
    /**
     * Create a sale with items in a single database transaction.
     *
     * @param array<string, mixed> $data
     * @param string $userId
     * @return Sale
     */
    public function execute(array $data, string $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = $data['items'];
            unset($data['items']);

            $data['user_id'] = $userId;
            $sale = Sale::create($data);

            foreach ($items as $item) {
                $sale->items()->create($item);
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
                    throw new \DomainException("Credit limit exceeded. Customer balance is Rs. {$balanceRs}, limit is Rs. {$limitRs}.");
                }

                $user = \App\Models\User::find($userId);
                $customer->chargeCreditSale($sale, $sale->total_minor, $user);
            }

            return $sale->load('items');
        });
    }
}
