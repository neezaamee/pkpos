<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminPlanController extends Controller
{
    /**
     * Display the SaaS subscription tiers, revenue analytics, and tenant subscriptions.
     */
    public function index(Request $request): Response
    {
        $plans = Plan::withCount(['subscriptions' => function ($q) {
            $q->whereIn('status', ['active', 'trialing']);
        }])->orderBy('price_minor')->get()->map(fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'description' => $p->description,
            'price_minor' => $p->price_minor,
            'price_pkr' => round($p->price_minor / 100, 2),
            'billing_interval' => $p->billing_interval,
            'max_locations' => $p->max_locations,
            'max_terminals' => $p->max_terminals,
            'features' => $p->features ?? [],
            'is_active' => (bool) $p->is_active,
            'active_subscribers_count' => $p->subscriptions_count,
            'mrr_pkr' => round(($p->price_minor * $p->subscriptions_count) / 100, 2),
        ]);

        $subscriptionsQuery = Subscription::withoutGlobalScopes()
            ->with(['tenant:id,name,province', 'plan:id,code,name,max_locations,max_terminals'])
            ->latest('starts_at');

        $tenantFilter = $request->input('tenant_id');
        $statusFilter = $request->input('status');

        if ($tenantFilter) {
            $subscriptionsQuery->where('tenant_id', $tenantFilter);
        }

        if ($statusFilter) {
            $subscriptionsQuery->where('status', $statusFilter);
        }

        $subscriptions = $subscriptionsQuery->paginate(15)
            ->withQueryString()
            ->through(function ($s) {
                $tenant = $s->tenant;
                $locationsCount = $tenant ? $tenant->locations()->withoutGlobalScopes()->count() : 0;
                $terminalsCount = $tenant ? $tenant->terminals()->withoutGlobalScopes()->where('is_active', true)->count() : 0;

                return [
                    'id' => $s->id,
                    'tenant_id' => $s->tenant_id,
                    'tenant_name' => $tenant?->name ?? 'Unknown Tenant',
                    'tenant_province' => $tenant?->province ?? '',
                    'plan_id' => $s->plan_id,
                    'plan_name' => $s->plan?->name ?? 'Unknown Plan',
                    'plan_code' => $s->plan?->code ?? '',
                    'status' => $s->status,
                    'starts_at' => $s->starts_at->format('Y-m-d'),
                    'ends_at' => $s->ends_at?->format('Y-m-d') ?? 'Auto-renewing',
                    'notes' => $s->notes,
                    'locations_usage' => [
                        'used' => $locationsCount,
                        'max' => $s->plan?->max_locations,
                    ],
                    'terminals_usage' => [
                        'used' => $terminalsCount,
                        'max' => $s->plan?->max_terminals,
                    ],
                ];
            });

        // Compute aggregate metrics
        $totalMrrPkr = $plans->sum('mrr_pkr');
        $activeSubscribersCount = Subscription::withoutGlobalScopes()
            ->whereIn('status', ['active', 'trialing'])
            ->count();

        $tenants = Tenant::withoutGlobalScopes()
            ->whereNull('suspended_at')
            ->orderBy('name')
            ->get(['id', 'name', 'province']);

        return Inertia::render('SuperAdmin/Plans/Index', [
            'plans' => $plans,
            'subscriptions' => $subscriptions,
            'tenants' => $tenants,
            'metrics' => [
                'mrr_pkr' => $totalMrrPkr,
                'active_subscribers_count' => $activeSubscribersCount,
                'total_plans_count' => $plans->count(),
            ],
            'filters' => [
                'tenant_id' => $tenantFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    /**
     * Store a newly created pricing plan tier.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:plans,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'billing_interval' => ['required', 'string', 'in:month,year'],
            'max_locations' => ['nullable', 'integer', 'min:1'],
            'max_terminals' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $plan = Plan::create($validated);

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'plan_created',
            'auditable_type' => Plan::class,
            'auditable_id' => $plan->id,
            'new_values' => [
                'code' => $plan->code,
                'name' => $plan->name,
                'price_minor' => $plan->price_minor,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Plan '{$plan->name}' created successfully.");
    }

    /**
     * Update an existing plan tier.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:plans,code,' . $plan->id],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'billing_interval' => ['required', 'string', 'in:month,year'],
            'max_locations' => ['nullable', 'integer', 'min:1'],
            'max_terminals' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $plan->update($validated);

        return back()->with('success', "Plan '{$plan->name}' updated successfully.");
    }

    /**
     * Assign, change, or upgrade a merchant tenant's subscription.
     */
    public function assignSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'status' => ['required', 'string', 'in:active,trialing,past_due,canceled'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Cancel previous active/trialing subscriptions for this tenant
        Subscription::withoutGlobalScopes()
            ->where('tenant_id', $validated['tenant_id'])
            ->whereIn('status', ['active', 'trialing'])
            ->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

        $sub = Subscription::withoutGlobalScopes()->create($validated);
        $plan = Plan::find($validated['plan_id']);
        $tenant = Tenant::withoutGlobalScopes()->find($validated['tenant_id']);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => Auth::id(),
            'event' => 'subscription_assigned',
            'auditable_type' => Subscription::class,
            'auditable_id' => $sub->id,
            'new_values' => [
                'tenant_name' => $tenant->name,
                'plan_name' => $plan?->name,
                'status' => $sub->status,
                'starts_at' => $sub->starts_at->toDateString(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Subscription for '{$tenant->name}' updated to '{$plan->name}'.");
    }

    /**
     * Cancel an active subscription.
     */
    public function cancelSubscription(Request $request, string $id): RedirectResponse
    {
        $sub = Subscription::withoutGlobalScopes()->findOrFail($id);

        $sub->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        AuditLog::create([
            'tenant_id' => $sub->tenant_id,
            'user_id' => Auth::id(),
            'event' => 'subscription_canceled',
            'auditable_type' => Subscription::class,
            'auditable_id' => $sub->id,
            'new_values' => [
                'canceled_at' => now()->toDateTimeString(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Subscription canceled successfully.');
    }
}

