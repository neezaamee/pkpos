<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController extends Controller
{
    /**
     * Display the paginated sales table.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isSuperAdmin = (bool) $user->super_admin && ! $user->tenant_id;

        $query = Sale::with(['items', 'tenant:id,name']);

        if ($isSuperAdmin && $request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        $sales = $query
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Sales', [
            'sales' => $sales,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }
}
