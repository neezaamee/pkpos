<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    /**
     * Display a listing of sales for the current tenant.
     */
    public function index(): JsonResponse
    {
        return response()->json(Sale::with('items')->latest()->paginate());
    }

    /**
     * Store a new sale with items.
     */
    public function store(StoreSaleRequest $request, CreateSaleAction $action): JsonResponse
    {
        $sale = $action->execute($request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Sale created successfully.',
            'sale' => $sale,
        ], 201);
    }

    /**
     * Display the specified sale.
     */
    public function show(Sale $sale): JsonResponse
    {
        return response()->json($sale->load('items'));
    }
}
