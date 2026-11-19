<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessSaleReturnAction;
use App\Http\Controllers\Controller;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleReturnApiController extends Controller
{
    /**
     * Verify an original invoice / receipt barcode and return its returnable line items.
     */
    public function verifyInvoice(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
        ]);

        $term = trim($validated['invoice_number']);

        $sale = Sale::where('tenant_id', $tenantId)
            ->where(function ($q) use ($term) {
                $q->where('sale_number', $term)
                    ->orWhere('fbr_usin', $term);
                if (\Illuminate\Support\Str::isUuid($term)) {
                    $q->orWhere('id', $term);
                }
            })
            ->with(['customer:id,name,phone,current_balance_minor', 'location:id,name', 'items'])
            ->first();

        if (! $sale) {
            return response()->json([
                'error' => 'Invoice not found. Please verify the invoice number or barcode.',
            ], 404);
        }

        $returnableItems = $sale->getReturnableItems();

        return response()->json([
            'sale' => $sale,
            'returnable_items' => $returnableItems,
        ]);
    }

    /**
     * Store a verified sales return from the POS terminal.
     */
    public function store(Request $request, ProcessSaleReturnAction $action): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'register_session_id' => ['nullable', 'uuid', 'exists:register_sessions,id'],
            'reason' => ['required', 'string', 'in:defective,wrong_item,customer_change_of_mind,expired,other'],
            'refund_method' => ['required', 'string', 'in:cash,credit_note_khata,original_payment'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'uuid', 'exists:sale_items,id'],
            'items.*.quantity_returned' => ['required', 'numeric', 'min:0.001'],
            'items.*.restock_condition' => ['required', 'string', 'in:restock_shelf,damaged_quarantine,discard'],
        ]);

        // Resolve active register session for drawer cash refund
        $session = null;
        if (! empty($validated['register_session_id'])) {
            $session = RegisterSession::where('tenant_id', $tenantId)
                ->where('id', $validated['register_session_id'])
                ->where('status', 'open')
                ->first();
        } else {
            $session = RegisterSession::where('tenant_id', $tenantId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();
        }

        $saleReturn = $action->execute($validated, $request->user(), $session);

        return response()->json([
            'message' => 'Sales Return & FBR Credit Note processed successfully.',
            'sale_return' => $saleReturn,
        ], 201);
    }

    /**
     * Retrieve return details for receipt printing.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $saleReturn = SaleReturn::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with([
                'sale',
                'customer',
                'user:id,name',
                'location:id,name,address',
                'items.product:id,name,sku,barcode',
            ])
            ->firstOrFail();

        return response()->json([
            'sale_return' => $saleReturn,
        ]);
    }
}
