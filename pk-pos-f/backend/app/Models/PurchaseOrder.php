<?php

namespace App\Models;

use App\Services\Inventory\StockService;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'location_id',
        'user_id',
        'po_number',
        'status',
        'expected_delivery_date',
        'received_at',
        'subtotal_minor',
        'tax_minor',
        'total_minor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_delivery_date' => 'date',
            'received_at' => 'datetime',
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class, 'purchase_order_id');
    }

    /**
     * Process Goods Received Note (GRN):
     * Increments stock levels, updates moving unit cost, updates PO status,
     * and logs payable invoice on supplier's ledger.
     *
     * @param array<int, array{item_id: string, quantity_received: float}> $receivedLines
     */
    public function receiveGoods(array $receivedLines, User $receivedBy, ?string $grnNotes = null): void
    {
        DB::transaction(function () use ($receivedLines, $receivedBy, $grnNotes) {
            $this->lockForUpdate();
            $stockService = app(StockService::class);
            $tenant = Tenant::findOrFail($this->tenant_id);

            $receivedValueMinor = 0;
            $itemsMap = $this->items()->get()->keyBy('id');

            foreach ($receivedLines as $line) {
                $itemId = $line['item_id'] ?? $line['po_item_id'] ?? null;
                $qtyReceived = (float) ($line['quantity_received'] ?? 0);

                if (! $itemId || $qtyReceived <= 0 || ! isset($itemsMap[$itemId])) {
                    continue;
                }

                /** @var PurchaseOrderItem $poItem */
                $poItem = $itemsMap[$itemId];
                $poItem->received_quantity += $qtyReceived;
                $poItem->save();

                $conversionFactor = $poItem->unit_conversion_factor ?: 1.0;
                $baseQty = $qtyReceived * $conversionFactor;

                // 1. Record stock movement and increment quantity_on_hand
                $stockService->recordMovement(
                    $tenant,
                    $poItem->product_id,
                    $this->location_id,
                    'restock',
                    $baseQty,
                    'purchase_order',
                    $this->id,
                    "GRN Shipment Receipt for PO #{$this->po_number}",
                    $receivedBy->id
                );

                // 1b. If batch info is provided (e.g. Pharmacy GRN), record batch stock
                if (! empty($line['batch_number']) && ! empty($line['expiry_date'])) {
                    app(\App\Services\Pharmacy\BatchService::class)->recordBatch($tenant, [
                        'product_id' => $poItem->product_id,
                        'location_id' => $this->location_id,
                        'batch_number' => $line['batch_number'],
                        'manufacturing_date' => $line['manufacturing_date'] ?? null,
                        'expiry_date' => $line['expiry_date'],
                        'quantity_on_hand' => $baseQty,
                        'cost_minor' => $poItem->cost_price_minor,
                        'mrp_minor' => $line['mrp_minor'] ?? null,
                    ]);
                }

                // 2. Update product latest cost price
                if ($poItem->cost_price_minor > 0) {
                    $product = Product::withoutGlobalScopes()->find($poItem->product_id);
                    if ($product) {
                        $product->update(['cost_minor' => $poItem->cost_price_minor]);
                    }
                }

                // 3. Accumulate payable bill value
                $receivedValueMinor += (int) round($qtyReceived * $poItem->cost_price_minor);
            }

            // Check if all items are fully received
            $allReceived = true;
            $hasAnyReceived = false;

            $this->refresh();
            foreach ($this->items as $item) {
                if ($item->received_quantity < $item->ordered_quantity) {
                    $allReceived = false;
                }
                if ($item->received_quantity > 0) {
                    $hasAnyReceived = true;
                }
            }

            $newStatus = $allReceived ? 'received' : ($hasAnyReceived ? 'partial_received' : $this->status);
            $this->update([
                'status' => $newStatus,
                'received_at' => now(),
            ]);

            // 4. Log payable invoice on supplier's ledger if value received > 0
            if ($receivedValueMinor > 0) {
                $this->supplier->recordBill(
                    $receivedValueMinor,
                    $this,
                    $receivedBy,
                    $grnNotes ?? "GRN Receipt for PO #{$this->po_number}"
                );

                // Auto-post to General Ledger
                try {
                    app(\App\Services\AccountingService::class)->postGoodsReceipt($this, $receivedValueMinor);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Accounting auto-post skipped for GRN {$this->id}: " . $e->getMessage());
                }
            }
        });
    }
}
