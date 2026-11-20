<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use App\Services\Restaurant\KitchenOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KdsWebController extends Controller
{
    public function __construct(
        protected KitchenOrderService $kotService
    ) {}

    /**
     * Live Kitchen Display System (KDS) for kitchen chef screens.
     */
    public function index(Request $request): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $locationId = $request->query('location_id') ?? $tenant->locations()->first()?->id;
        $stationId = $request->query('station_id');

        $stations = KitchenStation::where('tenant_id', $tenant->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->get(['id', 'name']);

        $orders = $this->kotService->getLiveKdsOrders($tenant, $stationId, $locationId);

        return Inertia::render('Restaurant/Kds/Index', [
            'initialOrders' => $orders,
            'stations' => $stations,
            'selectedStationId' => $stationId,
            'locationId' => $locationId,
        ]);
    }
}
