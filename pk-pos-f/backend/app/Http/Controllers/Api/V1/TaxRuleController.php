<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaxRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxRuleController extends Controller
{
    /**
     * Display a listing of tax rules.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $query = TaxRule::query()
            ->when($request->filled('tax_category'), function ($q) use ($request) {
                $q->where('tax_category', $request->input('tax_category'));
            })
            ->when($request->filled('province'), function ($q) use ($request) {
                $q->where('province', $request->input('province'));
            })
            ->when($request->has('is_active'), function ($q) use ($request) {
                $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('effective_from', 'desc');

        return response()->json($query->get());
    }

    /**
     * Store a newly created tax rule.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tax_category' => ['required', 'string', 'max:50'],
            'province' => ['required', Rule::in(['punjab', 'sindh', 'kp', 'balochistan', 'federal'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'raast', 'wallet', 'any'])],
            'rate_basis_points' => ['required', 'integer', 'between:0,10000'],
            'authority' => ['required', Rule::in(['fbr', 'pra', 'srb', 'kpra', 'bra'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = TaxRule::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'tax_category' => $validated['tax_category'],
            'province' => $validated['province'],
            'payment_method' => $validated['payment_method'] ?? 'any',
            'rate_basis_points' => $validated['rate_basis_points'],
            'authority' => $validated['authority'],
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($rule, 201);
    }

    /**
     * Update the specified tax rule.
     * Only is_active and effective_to may be updated.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAccess($request);

        $rule = TaxRule::findOrFail($id);

        $immutableFields = ['rate_basis_points', 'province', 'payment_method', 'tax_category', 'authority'];
        foreach ($immutableFields as $field) {
            if ($request->has($field) && $request->input($field) != $rule->{$field}) {
                return response()->json([
                    'message' => 'Create a new rule instead.',
                    'errors' => [
                        $field => ['Create a new rule instead.'],
                    ],
                ], 422);
            }
        }

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'effective_to' => ['nullable', 'date'],
        ]);

        $rule->update($validated);

        return response()->json($rule);
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
