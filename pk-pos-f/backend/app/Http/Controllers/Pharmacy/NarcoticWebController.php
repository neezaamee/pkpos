<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\NarcoticRegisterEntry;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NarcoticWebController extends Controller
{
    /**
     * Display the official Controlled Substances & Narcotics Dispensing Register (Schedule D).
     */
    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;
        $search = $request->query('search');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $productId = $request->query('product_id');

        $query = NarcoticRegisterEntry::with(['product', 'prescription', 'sale', 'dispensedByUser', 'batch'])
            ->where('tenant_id', $tenant->id)
            ->whereBetween('dispensed_at', [
                \Carbon\Carbon::parse($from)->startOfDay(),
                \Carbon\Carbon::parse($to)->endOfDay(),
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'ilike', "%{$search}%")
                    ->orWhere('patient_cnic', 'ilike', "%{$search}%")
                    ->orWhere('doctor_name', 'ilike', "%{$search}%")
                    ->orWhere('doctor_pmdc_number', 'ilike', "%{$search}%")
                    ->orWhere('batch_number', 'ilike', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('generic_name', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $entries = $query->orderBy('dispensed_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $narcoticProducts = Product::where('tenant_id', $tenant->id)
            ->where('is_narcotic', true)
            ->select('id', 'name', 'generic_name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Pharmacy/Narcotics/Index', [
            'entries' => $entries,
            'filters' => [
                'search' => $search,
                'from' => $from,
                'to' => $to,
                'product_id' => $productId,
            ],
            'narcoticProducts' => $narcoticProducts,
        ]);
    }

    protected function authorizeAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, ['owner', 'manager']),
            403,
            'Unauthorized. Requires owner or manager role.'
        );
    }
}
