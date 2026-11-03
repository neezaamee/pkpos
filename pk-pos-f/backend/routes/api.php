<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BroadcastApiController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReleaseApiController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SupportApiController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TaxRuleController;
use App\Http\Controllers\Api\V1\TerminalController;
use App\Http\Controllers\SuperAdmin\SuperAdminMasterDataController;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::get('/ping', [SyncController::class, 'ping']);
    Route::get('/broadcasts/active', [BroadcastApiController::class, 'active']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/tenant/config', function (Request $request) {
        $tenant = null;
        if ($bearer = $request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            $tenant = $token?->tokenable?->tenant;
        }
        $tenant = $tenant ?? \App\Models\Tenant::first();
        if (! $tenant) {
            return response()->json(['error' => 'No tenant configured'], 404);
        }
        $cred = $tenant->fbrCredential;

        $registerToken = null;
        $user = $tenant->users()->where('role', 'owner')->first() ?? $tenant->users()->first();
        if ($user) {
            $existing = $user->tokens()->where('name', 'pos-register')->latest()->first();
            if (! $existing || ! $bearer) {
                $newPat = $user->createToken('pos-register');
                $registerToken = $newPat->plainTextToken;
            }
        }

        return response()->json([
            'pos_id' => $cred?->pos_id ?? $tenant->fbr_pos_id ?? 'POS-001',
            'bearer_token' => $cred?->bearer_token ?? '',
            'ntn' => $cred?->ntn ?? $tenant->ntn ?? '1234567-8',
            'strn' => $cred?->strn ?? $tenant->strn ?? '12-34-5678-001-99',
            'name' => $tenant->name,
            'province' => $tenant->province,
            'register_token' => $registerToken,
        ]);
    });

    Route::get('/catalog/sync', [ProductController::class, 'sync']);
    Route::get('/master-data/hs-codes', [SuperAdminMasterDataController::class, 'searchHsCodesApi']);

    // POS Desktop Client Pairing & Heartbeat
    Route::post('/terminals/pair', [TerminalController::class, 'pair']);
    Route::post('/terminals/heartbeat', [TerminalController::class, 'heartbeat']);

    // Desktop Tauri OTA Updates & Changelogs
    Route::get('/updates/desktop/{target}/{current_version}', [ReleaseApiController::class, 'tauriUpdate']);
    Route::get('/changelog', [ReleaseApiController::class, 'changelog']);
    Route::get('/version/check', [ReleaseApiController::class, 'versionCheck']);

    // Authenticated routes
    Route::middleware(['auth:sanctum', 'tenant.active'])->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::get('/locations', function () {
            return Location::all();
        });

        Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);

        // Products & Catalog
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);

        // Tax Rules
        Route::get('/tax-rules', [TaxRuleController::class, 'index']);
        Route::post('/tax-rules', [TaxRuleController::class, 'store']);
        Route::put('/tax-rules/{id}', [TaxRuleController::class, 'update']);

        // Inventory & Stock Management
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
        Route::post('/inventory/restock', [InventoryController::class, 'restock']);
        Route::get('/inventory/levels', [InventoryController::class, 'levels']);
        Route::get('/inventory/movements', [InventoryController::class, 'movements']);

        // Customer Support Ticketing
        Route::post('/support/tickets', [SupportApiController::class, 'createTicket']);
        Route::get('/support/tickets', [SupportApiController::class, 'listTickets']);

        // Idempotent Sync routes
        Route::middleware('idempotent')->group(function () {
            Route::post('/sales/sync', [SyncController::class, 'syncSales']);
            Route::post('/sales/failed-fbr', [SyncController::class, 'failedFbr']);
            Route::post('/stock-movements/sync', [SyncController::class, 'syncStockMovements']);
        });
    });
});
