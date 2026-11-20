<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrescriptionWebController extends Controller
{
    /**
     * Display listing of recorded doctor prescriptions.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;
        $search = $request->query('search');

        $query = Prescription::with(['sale', 'dispensedByUser', 'narcoticEntries'])
            ->where('tenant_id', $tenant->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'ilike', "%{$search}%")
                    ->orWhere('patient_phone', 'ilike', "%{$search}%")
                    ->orWhere('patient_cnic', 'ilike', "%{$search}%")
                    ->orWhere('doctor_name', 'ilike', "%{$search}%")
                    ->orWhere('doctor_pmdc_number', 'ilike', "%{$search}%")
                    ->orWhereHas('sale', function ($sq) use ($search) {
                        $sq->where('sale_number', 'ilike', "%{$search}%");
                    });
            });
        }

        $prescriptions = $query->orderBy('prescription_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Pharmacy/Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display a specific prescription and its dispensed medicines.
     */
    public function show(Request $request, string $id): Response
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;
        $prescription = Prescription::with([
            'sale.items.product',
            'sale.items.batch',
            'dispensedByUser',
            'narcoticEntries.product',
            'narcoticEntries.batch',
        ])
            ->where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        return Inertia::render('Pharmacy/Prescriptions/Show', [
            'prescription' => $prescription,
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
