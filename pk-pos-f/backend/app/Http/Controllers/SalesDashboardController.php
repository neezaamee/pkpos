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
        $sales = Sale::with('items')
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Sales', [
            'sales' => $sales,
        ]);
    }
}
