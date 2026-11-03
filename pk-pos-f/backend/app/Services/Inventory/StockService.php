<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Record a stock movement and update the cached stock level projection atomically.
     *
     * @throws InsufficientStockException
     */
    public function recordMovement(
        Tenant $tenant,
        string $productId,
        string $locationId,
        string $movementType,
        int $quantityDelta,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $note = null,
        ?string $createdBy = null
    ): StockMovement {
        return DB::transaction(function () use (
            $tenant,
            $productId,
            $locationId,
            $movementType,
            $quantityDelta,
            $referenceType,
            $referenceId,
            $note,
            $createdBy
        ) {
            // Ensure stock level row exists, then query with exclusive row lock
            StockLevel::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $productId,
                    'location_id' => $locationId,
                ],
                [
                    'quantity_on_hand' => 0,
                    'updated_at' => now(),
                ]
            );

            /** @var StockLevel $level */
            $level = StockLevel::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            $newQuantity = $level->quantity_on_hand + $quantityDelta;

            if ($quantityDelta < 0 && $newQuantity < 0) {
                $isSale = ($movementType === 'sale');
                $strictSaleSync = config('inventory.strict_sale_sync', false);
                $allowNegativeStock = config('inventory.allow_negative_stock', false);

                if ($isSale && ! $strictSaleSync) {
                    // Offline POS sales are business truth: deduct and allow negative stock with warning
                    Log::warning("Offline completed sale caused negative stock for product [{$productId}] at location [{$locationId}]. Projected: [{$newQuantity}].");
                } elseif (! $allowNegativeStock) {
                    throw new InsufficientStockException(
                        $productId,
                        $locationId,
                        abs($quantityDelta),
                        $level->quantity_on_hand
                    );
                }
            }

            // Update cached projection
            $level->quantity_on_hand = $newQuantity;
            $level->updated_at = now();
            $level->save();

            // Insert append-only movement truth
            return StockMovement::create([
                'tenant_id' => $tenant->id,
                'product_id' => $productId,
                'location_id' => $locationId,
                'movement_type' => $movementType,
                'quantity_delta' => $quantityDelta,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Get the current on-hand stock for a product at a given location.
     */
    public function getCurrentStock(Tenant $tenant, string $productId, string $locationId): int
    {
        $level = StockLevel::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->first();

        return $level ? (int) $level->quantity_on_hand : 0;
    }

    /**
     * Get chronological stock history for a product at a location.
     */
    public function getStockHistory(
        Tenant $tenant,
        string $productId,
        string $locationId,
        int $limit = 50
    ): Collection {
        return StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
