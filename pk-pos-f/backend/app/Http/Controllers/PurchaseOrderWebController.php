<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderWebController extends Controller
{
    /**
     * Display a listing of purchase orders with status filtering.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $status = $request->query('status');
        $supplierId = $request->query('supplier_id');
        $q = $request->query('q');

        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['supplier:id,name,phone', 'location:id,name', 'user:id,name'])
            ->withCount('items');

        if (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($supplierId)) {
            $query->where('supplier_id', $supplierId);
        }

        if (! empty($q)) {
            $term = trim($q);
            $query->where(function ($sub) use ($term) {
                $sub->where('po_number', 'like', "%{$term}%")
                    ->orWhereHas('supplier', function ($sq) use ($term) {
                        $sq->where('name', 'ilike', "%{$term}%");
                    });
            });
        }

        $purchaseOrders = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $metrics = [
            'total_pos' => PurchaseOrder::where('tenant_id', $tenantId)->count(),
            'pending_receipt_count' => PurchaseOrder::where('tenant_id', $tenantId)
                ->whereIn('status', ['ordered', 'partial_received'])
                ->count(),
            'total_purchased_minor' => (int) PurchaseOrder::where('tenant_id', $tenantId)
                ->whereIn('status', ['received', 'partial_received'])
                ->sum('total_minor'),
        ];

        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Purchases/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => [
                'status' => $status,
                'supplier_id' => $supplierId,
                'q' => $q,
            ],
            'metrics' => $metrics,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Show form for creating a new purchase order.
     */
    public function create(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'phone', 'payment_terms_days')
            ->orderBy('name')
            ->get();

        $locations = Location::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $products = Product::where('tenant_id', $tenantId)
            ->with(['uomConversions'])
            ->select('id', 'name', 'sku', 'barcode', 'cost_minor', 'price_minor', 'unit_of_measure')
            ->orderBy('name')
            ->get();

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers,
            'locations' => $locations,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created purchase order with line items.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.unit_code' => ['required', 'string', 'max:20'],
            'items.*.unit_conversion_factor' => ['required', 'numeric', 'min:0.001'],
            'items.*.ordered_quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.cost_price_minor' => ['required', 'integer', 'min:0'],
            'items.*.tax_rate_basis_points' => ['nullable', 'integer', 'min:0'],
        ]);

        $po = DB::transaction(function () use ($tenantId, $request, $validated) {
            $dateNum = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $poNumber = "PO-{$dateNum}-{$random}";

            $subtotalMinor = 0;
            $taxMinor = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $qty = (float) $item['ordered_quantity'];
                $cost = (int) $item['cost_price_minor'];
                $taxBps = (int) ($item['tax_rate_basis_points'] ?? 0);

                $lineSubtotal = (int) round($qty * $cost);
                $lineTax = (int) round(($lineSubtotal * $taxBps) / 10000);
                $lineTotal = $lineSubtotal + $lineTax;

                $subtotalMinor += $lineSubtotal;
                $taxMinor += $lineTax;

                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'unit_code' => $item['unit_code'],
                    'unit_conversion_factor' => $item['unit_conversion_factor'],
                    'ordered_quantity' => $qty,
                    'received_quantity' => 0,
                    'cost_price_minor' => $cost,
                    'tax_rate_basis_points' => $taxBps,
                    'tax_minor' => $lineTax,
                    'line_total_minor' => $lineTotal,
                ];
            }

            $totalMinor = $subtotalMinor + $taxMinor;

            $purchaseOrder = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'supplier_id' => $validated['supplier_id'],
                'location_id' => $validated['location_id'],
                'user_id' => $request->user()->id,
                'po_number' => $poNumber,
                'status' => 'ordered',
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'total_minor' => $totalMinor,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($itemsData as $data) {
                $purchaseOrder->items()->create($data);
            }

            return $purchaseOrder;
        });

        return redirect()->route('purchases.show', $po->id)
            ->with('success', "Purchase Order {$po->po_number} created successfully.");
    }

    /**
     * Display a purchase order details and Goods Receiving status.
     */
    public function show(Request $request, string $id): Response
    {
        $tenantId = $request->user()->tenant_id;

        $purchaseOrder = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with([
                'supplier',
                'location:id,name',
                'user:id,name',
                'items.product:id,name,sku,barcode,unit_of_measure',
                'transactions',
            ])
            ->firstOrFail();

        return Inertia::render('Purchases/Show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    /**
     * Process Goods Received Note (GRN) for arriving stock shipment.
     */
    public function receive(Request $request, string $id): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $purchaseOrder = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'uuid', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $purchaseOrder->receiveGoods(
            $validated['items'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return redirect()->route('purchases.show', $purchaseOrder->id)
            ->with('success', "Goods Received Note (GRN) processed. Stock levels updated and vendor payable invoice logged.");
    }
}
