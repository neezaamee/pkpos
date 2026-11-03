<?php

namespace App\Http\Controllers;

use App\Models\TaxRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaxRuleWebController extends Controller
{
    /**
     * Display a listing of the tax rules.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $taxRules = TaxRule::latest('effective_from')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('TaxRules/Index', [
            'taxRules' => $taxRules,
        ]);
    }

    /**
     * Show the form for creating a new tax rule.
     */
    public function create(Request $request): Response
    {
        $this->authorizeAccess($request);

        return Inertia::render('TaxRules/Create');
    }

    /**
     * Store a newly created tax rule in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tax_category' => ['required', 'string', 'max:50'],
            'province' => ['required', Rule::in(['punjab', 'sindh', 'kp', 'balochistan', 'federal'])],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'raast', 'wallet', 'any'])],
            'rate_percent' => ['required', 'numeric', 'between:0,100'],
            'authority' => ['required', Rule::in(['fbr', 'pra', 'srb', 'kpra', 'bra'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rateBasisPoints = (int) round($validated['rate_percent'] * 100);

        TaxRule::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'tax_category' => $validated['tax_category'],
            'province' => $validated['province'],
            'payment_method' => $validated['payment_method'],
            'rate_basis_points' => $rateBasisPoints,
            'authority' => $validated['authority'],
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('tax-rules.index')->with('success', 'Tax rule created successfully.');
    }

    /**
     * Update the specified tax rule in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAccess($request);

        $rule = TaxRule::findOrFail($id);

        $immutableFields = ['rate_percent', 'rate_basis_points', 'province', 'payment_method', 'tax_category', 'authority'];
        foreach ($immutableFields as $field) {
            if ($request->has($field) && $request->input($field) != $rule->{$field}) {
                return redirect()->back()->withErrors([
                    $field => 'Create a new rule instead.',
                ]);
            }
        }

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'effective_to' => ['nullable', 'date'],
        ]);

        $rule->update($validated);

        return redirect()->back()->with('success', 'Tax rule updated successfully.');
    }

    /**
     * Authorize that the authenticated user is an owner or manager.
     */
    protected function authorizeAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, ['owner', 'manager']),
            403,
            'Unauthorized. Requires owner or manager role.'
        );
    }
}
