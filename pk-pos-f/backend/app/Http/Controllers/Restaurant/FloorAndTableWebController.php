<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\RestaurantFloor;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FloorAndTableWebController extends Controller
{
    /**
     * Display floor plans and dining tables management.
     */
    public function index(Request $request): Response
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $locationId = $request->query('location_id') ?? $tenant->locations()->first()?->id;

        $floors = RestaurantFloor::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['tables' => fn ($q) => $q->orderBy('table_number')])
            ->orderBy('sort_order')
            ->get();

        $unassignedTables = RestaurantTable::where('tenant_id', $tenant->id)
            ->whereNull('floor_id')
            ->where('is_active', true)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->orderBy('table_number')
            ->get();

        $locations = $tenant->locations()->select('id', 'name')->get();

        return Inertia::render('Restaurant/Tables/Index', [
            'floors' => $floors,
            'unassignedTables' => $unassignedTables,
            'locations' => $locations,
            'selectedLocationId' => $locationId,
        ]);
    }

    /**
     * Create a new dining floor / zone.
     */
    public function storeFloor(Request $request): RedirectResponse
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);

        $validated = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'name' => ['required', 'string', 'max:100'],
            'floor_number' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        RestaurantFloor::create([
            'tenant_id' => $tenant->id,
            'location_id' => $validated['location_id'],
            'name' => $validated['name'],
            'floor_number' => $validated['floor_number'] ?? 1,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Floor '{$validated['name']}' created successfully.");
    }

    /**
     * Create a new dining table.
     */
    public function storeTable(Request $request): RedirectResponse
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);

        $validated = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'floor_id' => ['nullable', 'uuid', 'exists:restaurant_floors,id'],
            'table_number' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'shape' => ['nullable', 'string', 'in:square,round,rectangle'],
        ]);

        RestaurantTable::create([
            'tenant_id' => $tenant->id,
            'location_id' => $validated['location_id'],
            'floor_id' => $validated['floor_id'] ?? null,
            'table_number' => $validated['table_number'],
            'capacity' => $validated['capacity'],
            'shape' => $validated['shape'] ?? 'square',
            'status' => 'available',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Table '{$validated['table_number']}' added successfully.");
    }

    /**
     * Update table status or details.
     */
    public function updateTable(Request $request, string $id): RedirectResponse
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $table = RestaurantTable::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'table_number' => ['sometimes', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'shape' => ['sometimes', 'string', 'in:square,round,rectangle'],
            'status' => ['sometimes', 'string', 'in:available,occupied,billed,reserved,cleaning'],
            'floor_id' => ['nullable', 'uuid', 'exists:restaurant_floors,id'],
        ]);

        $table->update($validated);

        return redirect()->back()->with('success', "Table '{$table->table_number}' updated successfully.");
    }

    /**
     * Delete or deactivate table.
     */
    public function destroyTable(Request $request, string $id): RedirectResponse
    {
        $this->authorizeStaffOrSuperAdmin($request);

        $tenant = $this->requireTenant($request);
        $table = RestaurantTable::where('tenant_id', $tenant->id)->findOrFail($id);
        $table->delete();

        return redirect()->back()->with('success', 'Table removed.');
    }
}
