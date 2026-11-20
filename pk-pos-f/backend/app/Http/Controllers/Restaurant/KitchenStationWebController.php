<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenStationWebController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $locationId = $request->query('location_id') ?? $tenant->locations()->first()?->id;

        $stations = KitchenStation::where('tenant_id', $tenant->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->withCount('products')
            ->get();

        $locations = $tenant->locations()->select('id', 'name')->get();

        return Inertia::render('Restaurant/Stations/Index', [
            'stations' => $stations,
            'locations' => $locations,
            'selectedLocationId' => $locationId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'name' => ['required', 'string', 'max:100'],
            'printer_ip_or_name' => ['nullable', 'string', 'max:150'],
        ]);

        KitchenStation::create([
            'tenant_id' => $tenant->id,
            'location_id' => $validated['location_id'],
            'name' => $validated['name'],
            'printer_ip_or_name' => $validated['printer_ip_or_name'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Kitchen station '{$validated['name']}' created successfully.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $station = KitchenStation::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'printer_ip_or_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $station->update($validated);

        return redirect()->back()->with('success', "Kitchen station '{$station->name}' updated.");
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $station = KitchenStation::where('tenant_id', $tenant->id)->findOrFail($id);
        $station->delete();

        return redirect()->back()->with('success', 'Station removed.');
    }
}
