<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModifierWebController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        $groups = ModifierGroup::where('tenant_id', $tenant->id)
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return Inertia::render('Restaurant/Modifiers/Index', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'min_selections' => ['required', 'integer', 'min:0'],
            'max_selections' => ['required', 'integer', 'min:1'],
            'options' => ['required', 'array', 'min:1'],
            'options.*.name' => ['required', 'string', 'max:100'],
            'options.*.price_override_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $group = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'min_selections' => $validated['min_selections'],
            'max_selections' => $validated['max_selections'],
            'is_active' => true,
        ]);

        foreach ($validated['options'] as $idx => $opt) {
            $group->options()->create([
                'name' => $opt['name'],
                'price_override_minor' => $opt['price_override_minor'] ?? 0,
                'sort_order' => $idx,
                'is_active' => true,
            ]);
        }

        return redirect()->back()->with('success', "Modifier group '{$group->name}' created.");
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $group = ModifierGroup::where('tenant_id', $tenant->id)->findOrFail($id);
        $group->delete();

        return redirect()->back()->with('success', 'Modifier group deleted.');
    }
}
