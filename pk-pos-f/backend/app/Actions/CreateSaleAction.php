<?php

namespace App\Actions;

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

            return $sale->load('items');
        });
    }
}
