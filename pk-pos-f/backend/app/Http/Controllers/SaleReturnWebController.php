<?php

namespace App\Http\Controllers;

use App\Actions\ProcessSaleReturnAction;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleReturnWebController extends Controller
{
    /**
     * Display a listing of sales returns and credit notes.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $q = $request->query('q');
        $refundMethod = $request->query('refund_method');

        $query = SaleReturn::where('tenant_id', $tenantId)
            ->with(['sale:id,sale_number,total_minor,fbr_usin', 'customer:id,name,phone', 'user:id,name', 'location:id,name'])
            ->withCount('items');

        if (! empty($refundMethod)) {
            $query->where('refund_method', $refundMethod);
        }

        if (! empty($q)) {
            $term = trim($q);
            $query->where(function ($sub) use ($term) {
                $sub->where('return_number', 'like', "%{$term}%")
                    ->orWhere('fbr_credit_note_number', 'like', "%{$term}%")
                    ->orWhereHas('sale', function ($sq) use ($term) {
                        $sq->where('sale_number', 'like', "%{$term}%")
                            ->orWhere('fbr_usin', 'like', "%{$term}%");
                    });
            });
        }

        $returns = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $metrics = [
            'total_returns_count' => SaleReturn::where('tenant_id', $tenantId)->count(),
            'total_refunded_minor' => (int) SaleReturn::where('tenant_id', $tenantId)->sum('total_refund_minor'),
            'fbr_credit_notes_count' => SaleReturn::where('tenant_id', $tenantId)->whereNotNull('fbr_credit_note_number')->count(),
        ];

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
            'filters' => [
                'q' => $q,
                'refund_method' => $refundMethod,
            ],
            'metrics' => $metrics,
        ]);
    }

    /**
     * Show form for creating a sales return / invoice lookup.
     */
    public function create(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $invoiceNumber = $request->query('invoice_number');

        $matchedSale = null;
        $returnableItems = [];

        if (! empty($invoiceNumber)) {
            $matchedSale = Sale::where('tenant_id', $tenantId)
                ->where(function ($q) use ($invoiceNumber) {
                    $q->where('sale_number', $invoiceNumber)
                        ->orWhere('fbr_usin', $invoiceNumber);
                    if (\Illuminate\Support\Str::isUuid($invoiceNumber)) {
                        $q->orWhere('id', $invoiceNumber);
                    }
                })
                ->with(['customer:id,name,phone,current_balance_minor', 'location:id,name', 'items'])
                ->first();

            if ($matchedSale) {
                $returnableItems = $matchedSale->getReturnableItems();
            }
        }

        return Inertia::render('Returns/Create', [
            'invoice_number' => $invoiceNumber,
            'matched_sale' => $matchedSale,
            'returnable_items' => $returnableItems,
        ]);
    }

    /**
     * Store a verified sales return and generate statutory FBR Credit Note.
     */
    public function store(Request $request, ProcessSaleReturnAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'reason' => ['required', 'string', 'in:defective,wrong_item,customer_change_of_mind,expired,other'],
            'refund_method' => ['required', 'string', 'in:cash,credit_note_khata,original_payment'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'uuid', 'exists:sale_items,id'],
            'items.*.quantity_returned' => ['required', 'numeric', 'min:0.001'],
            'items.*.restock_condition' => ['required', 'string', 'in:restock_shelf,damaged_quarantine,discard'],
        ]);

        $saleReturn = $action->execute($validated, $request->user(), null);

        return redirect()->route('returns.show', $saleReturn->id)
            ->with('success', "Sales Return #{$saleReturn->return_number} processed. FBR Credit Note #{$saleReturn->fbr_credit_note_number} generated.");
    }

    /**
     * Display return details and printable Credit Note slip.
     */
    public function show(Request $request, string $id): Response
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

        return Inertia::render('Returns/Show', [
            'saleReturn' => $saleReturn,
        ]);
    }
}
