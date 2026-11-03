<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allow Negative Stock
    |--------------------------------------------------------------------------
    |
    | Whether stock levels are allowed to become negative during manual
    | operations or general stock deduction.
    |
    */
    'allow_negative_stock' => env('INVENTORY_ALLOW_NEGATIVE_STOCK', false),

    /*
    |--------------------------------------------------------------------------
    | Strict Sale Sync
    |--------------------------------------------------------------------------
    |
    | When false (default), POS sales completed offline will always deduct
    | stock and record a movement even if stock level goes negative.
    | When true (testing mode), insufficient stock throws an exception and
    | rolls back the sync transaction.
    |
    */
    'strict_sale_sync' => env('INVENTORY_STRICT_SALE_SYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | The quantity threshold at or below which an inventory item is flagged
    | as low stock.
    |
    */
    'low_stock_threshold' => env('INVENTORY_LOW_STOCK_THRESHOLD', 10),
];
