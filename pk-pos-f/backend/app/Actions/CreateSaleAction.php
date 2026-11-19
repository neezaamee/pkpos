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

            return $sale->load('items');
        });
    }
}
