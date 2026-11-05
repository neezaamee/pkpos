<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Ping endpoint for connectivity checks. No authentication required.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'server_time' => now()->toIso8601String(),
        ], 200);
    }

    /**
     * Sync sales batch (max 100).
     */
    public function syncSales(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Automatically refresh POS terminal heartbeat on sales sync
        if ($bearer = $request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            if ($token && str_starts_with($token->name, 'pos-terminal:')) {
                $code = substr($token->name, strlen('pos-terminal:'));
                \App\Models\PosTerminal::withoutGlobalScopes()
                    ->where('terminal_code', $code)
                    ->update([
                        'last_heartbeat_at' => now(),
                        'last_sync_at' => now(),
                        'ip_address' => $request->ip(),
                    ]);
            }
        }
        if ($termCode = $request->header('X-Terminal-Code')) {
            \App\Models\PosTerminal::withoutGlobalScopes()
                ->where('terminal_code', $termCode)
                ->update([
                    'last_heartbeat_at' => now(),
                    'last_sync_at' => now(),
                    'ip_address' => $request->ip(),
                ]);
        }

        // Normalize sales array from root array or keyed object
        $sales = $this->extractArrayPayload($request, 'sales');

        if (count($sales) > 100) {
            return response()->json([
                'message' => 'The sales batch cannot exceed 100 records.',
            ], 422);
        }

        // Strict cross-tenant validation: reject immediately with 403 if any record targets another tenant
        foreach ($sales as $saleData) {
            if (isset($saleData['tenant_id']) && $saleData['tenant_id'] !== $tenantId) {
                return response()->json([
                    'message' => 'Forbidden: Cannot sync sales for another tenant.',
                ], 403);
            }
        }

        $syncedCount = 0;
        $errors = [];

        foreach ($sales as $index => $saleData) {
            $saleId = $saleData['id'] ?? null;

            if (! $saleId || ! Str::isUuid($saleId)) {
                $errors[] = [
                    'index' => $index,
                    'id' => $saleId,
                    'error' => 'A valid UUID id is required for each sale.',
                ];
                continue;
            }

            // Check if sale already exists (idempotent upsert)
            $existing = Sale::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $saleId)
                ->exists();

            if ($existing) {
                // Idempotent success: record already exists. Do NOT re-deduct stock!
                continue;
            }

            // Validate sale items if present
            $items = $saleData['items'] ?? [];
            $itemValidationError = false;
            foreach ($items as $item) {
                if (empty($item['hs_code'])) {
                    $errors[] = [
                        'index' => $index,
                        'id' => $saleId,
                        'error' => 'The hs_code is required for all sale items.',
                    ];
                    $itemValidationError = true;
                    break;
                }
            }

            if ($itemValidationError) {
                continue;
            }

            try {
                DB::transaction(function () use ($saleData, $saleId, $tenantId, $user, $items) {
                    $locationId = $saleData['location_id'] ?? $user->tenant?->locations()->first()?->id;

                    $sale = Sale::create([
                        'id' => $saleId,
                        'tenant_id' => $tenantId,
                        'location_id' => $locationId,
                        'user_id' => $saleData['user_id'] ?? $user->id,
                        'sale_number' => $saleData['sale_number'],
                        'payment_method' => $saleData['payment_method'],
                        'currency' => $saleData['currency'] ?? 'PKR',
                        'subtotal_minor' => $saleData['subtotal_minor'] ?? 0,
                        'tax_minor' => $saleData['tax_minor'] ?? 0,
                        'total_minor' => $saleData['total_minor'] ?? 0,
                        'status' => $saleData['status'] ?? 'completed',
                        'fbr_usin' => $saleData['fbr_usin'] ?? null,
                        'fbr_status' => $saleData['fbr_status'] ?? 'pending',
                        'fbr_error' => $saleData['fbr_error'] ?? null,
                        'fbr_submitted_at' => $saleData['fbr_submitted_at'] ?? null,
                    ]);

                    foreach ($items as $item) {
                        $productId = $item['product_id'] ?? null;
                        if (! $productId || ! Str::isUuid($productId)) {
                            $productId = Product::withoutGlobalScopes()
                                ->where('tenant_id', $tenantId)
                                ->where('hs_code', $item['hs_code'])
                                ->value('id');
                        }

                        SaleItem::create([
                            'id' => $item['id'] ?? (string) Str::uuid(),
                            'sale_id' => $sale->id,
                            'product_id' => $productId,
                            'product_name' => $item['product_name'],
                            'hs_code' => $item['hs_code'],
                            'quantity' => $item['quantity'],
                            'unit_price_minor' => $item['unit_price_minor'],
                            'tax_rate_basis_points' => $item['tax_rate_basis_points'] ?? 0,
                            'tax_amount_minor' => $item['tax_amount_minor'] ?? $item['tax_minor'] ?? 0,
                            'line_total_minor' => $item['line_total_minor'] ?? $item['total_minor'] ?? 0,
                        ]);

                        // Record stock deduction if product and location exist
                        if ($productId && $sale->location_id) {
                            $this->stockService->recordMovement(
                                $user->tenant,
                                $productId,
                                $sale->location_id,
                                'sale',
                                -1 * abs($item['quantity']),
                                'sale',
                                $sale->id,
                                "Sale: {$sale->sale_number}",
                                $user->id
                            );
                        }
                    }
                });

                $syncedCount++;
            } catch (InsufficientStockException $e) {
                if (config('inventory.strict_sale_sync', false)) {
                    return response()->json([
                        'message' => 'Sale sync rejected due to insufficient stock.',
                        'product_id' => $e->productId,
                        'location_id' => $e->locationId,
                        'requested' => $e->requested,
                        'available' => $e->available,
                        'error' => $e->getMessage(),
                    ], 422);
                }
                $errors[] = [
                    'index' => $index,
                    'id' => $saleId,
                    'error' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'index' => $index,
                    'id' => $saleId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'synced' => $syncedCount,
            'errors' => $errors,
        ], 200);
    }

    /**
     * Mark sales as failed FBR submission and record the error.
     */
    public function failedFbr(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Automatically refresh POS terminal heartbeat on sales sync
        if ($bearer = $request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            if ($token && str_starts_with($token->name, 'pos-terminal:')) {
                $code = substr($token->name, strlen('pos-terminal:'));
                \App\Models\PosTerminal::withoutGlobalScopes()
                    ->where('terminal_code', $code)
                    ->update([
                        'last_heartbeat_at' => now(),
                        'last_sync_at' => now(),
                        'ip_address' => $request->ip(),
                    ]);
            }
        }
        if ($termCode = $request->header('X-Terminal-Code')) {
            \App\Models\PosTerminal::withoutGlobalScopes()
                ->where('terminal_code', $termCode)
                ->update([
                    'last_heartbeat_at' => now(),
                    'last_sync_at' => now(),
                    'ip_address' => $request->ip(),
                ]);
        }

        $items = $this->extractArrayPayload($request, 'sales');

        // If a single object was passed without array wrapper
        if (empty($items) && $request->has('id')) {
            $items = [$request->all()];
        }

        $updatedCount = 0;

        foreach ($items as $item) {
            $saleId = $item['id'] ?? $item['sale_id'] ?? null;
            $fbrError = $item['fbr_error'] ?? $item['error'] ?? 'FBR submission failed';

            if (! $saleId) {
                continue;
            }

            $affected = Sale::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $saleId)
                ->update([
                    'fbr_status' => 'failed',
                    'fbr_error' => $fbrError,
                    'updated_at' => now(),
                ]);

            if ($affected > 0) {
                $updatedCount += $affected;
            }
        }

        return response()->json([
            'status' => 'ok',
            'updated' => $updatedCount,
        ], 200);
    }

    /**
     * Sync stock movements (append-only upsert).
     */
    public function syncStockMovements(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Automatically refresh POS terminal heartbeat on sales sync
        if ($bearer = $request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            if ($token && str_starts_with($token->name, 'pos-terminal:')) {
                $code = substr($token->name, strlen('pos-terminal:'));
                \App\Models\PosTerminal::withoutGlobalScopes()
                    ->where('terminal_code', $code)
                    ->update([
                        'last_heartbeat_at' => now(),
                        'last_sync_at' => now(),
                        'ip_address' => $request->ip(),
                    ]);
            }
        }
        if ($termCode = $request->header('X-Terminal-Code')) {
            \App\Models\PosTerminal::withoutGlobalScopes()
                ->where('terminal_code', $termCode)
                ->update([
                    'last_heartbeat_at' => now(),
                    'last_sync_at' => now(),
                    'ip_address' => $request->ip(),
                ]);
        }

        $movements = $this->extractArrayPayload($request, 'movements');
        if (empty($movements)) {
            $movements = $this->extractArrayPayload($request, 'stock_movements');
        }

        // Strict cross-tenant validation
        foreach ($movements as $movement) {
            if (isset($movement['tenant_id']) && $movement['tenant_id'] !== $tenantId) {
                return response()->json([
                    'message' => 'Forbidden: Cannot sync stock movements for another tenant.',
                ], 403);
            }
        }

        $syncedCount = 0;

        foreach ($movements as $movement) {
            $movementId = $movement['id'] ?? (string) Str::uuid();

            $existing = StockMovement::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $movementId)
                ->exists();

            if (! $existing) {
                StockMovement::create([
                    'id' => $movementId,
                    'tenant_id' => $tenantId,
                    'product_id' => $movement['product_id'] ?? null,
                    'location_id' => $movement['location_id'] ?? null,
                    'movement_type' => $movement['movement_type'] ?? 'adjustment',
                    'quantity_delta' => $movement['quantity_delta'] ?? $movement['quantity'] ?? 0,
                    'reference_type' => $movement['reference_type'] ?? null,
                    'reference_id' => $movement['reference_id'] ?? null,
                    'note' => $movement['note'] ?? $movement['reference'] ?? null,
                    'created_by' => $user->id,
                ]);

                $syncedCount++;
            }
        }

        return response()->json([
            'status' => 'ok',
            'synced' => $syncedCount,
        ], 200);
    }

    /**
     * Helper to extract an array payload whether passed as a root JSON list or keyed object.
     */
    private function extractArrayPayload(Request $request, string $key): array
    {
        $all = $request->json()->all();

        if (is_array($all) && array_is_list($all)) {
            return $all;
        }

        if ($request->has($key) && is_array($request->input($key))) {
            return $request->input($key);
        }

        if (is_array($all) && isset($all['id']) && ! isset($all[$key])) {
            return [$all];
        }

        return [];
    }
}
