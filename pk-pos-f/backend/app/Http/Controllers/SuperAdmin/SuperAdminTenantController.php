<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\TenantWelcomeMail;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Plan;
use App\Models\PosTerminal;
use App\Models\SaasInvoice;
use App\Models\Sale;
use App\Models\Scopes\TenantScope;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StarterCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminTenantController extends Controller
{
    /**
     * Display a paginated list of tenants with search, plan packages, and terminal metrics.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $tenants = Tenant::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->whereLike('name', "%{$search}%")
                        ->orWhereLike('ntn', "%{$search}%");
                });
            })
            ->with([
                'users' => function ($q) {
                    $q->withoutGlobalScope(TenantScope::class)->where('role', 'owner');
                },
                'subscriptions' => function ($q) {
                    $q->withoutGlobalScopes()
                        ->with('plan')
                        ->whereIn('status', ['active', 'trialing'])
                        ->latest('starts_at');
                },
                'terminals' => function ($q) {
                    $q->withoutGlobalScopes()
                        ->where('is_active', true)
                        ->where('pairing_status', '!=', 'revoked');
                },
                'locations' => function ($q) {
                    $q->withoutGlobalScopes();
                },
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function ($tenant) {
                $owner = $tenant->users->first();
                $activeSub = $tenant->subscriptions->first();
                $plan = $activeSub?->plan;

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'ntn' => $tenant->ntn,
                    'strn' => $tenant->strn,
                    'province' => $tenant->province,
                    'owner_email' => $owner?->email ?? 'N/A',
                    'owner_id' => $owner?->id,
                    'plan_name' => $plan?->name ?? 'Free Tier',
                    'plan_code' => $plan?->code ?? 'free',
                    'active_terminals_count' => $tenant->terminals->count(),
                    'max_terminals' => $plan?->max_terminals,
                    'locations_count' => $tenant->locations->count(),
                    'max_locations' => $plan?->max_locations,
                    'created_at' => $tenant->created_at?->format('Y-m-d H:i') ?? '',
                    'status' => $tenant->suspended_at ? 'suspended' : 'active',
                    'suspended_at' => $tenant->suspended_at?->format('Y-m-d H:i'),
                ];
            });

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the tenant creation form.
     */
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Tenants/Create');
    }

    /**
     * Store a newly created tenant, location, and owner.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_name' => ['required', 'string', 'max:255'],
            'ntn' => ['required', 'string', 'max:50'],
            'strn' => ['nullable', 'string', 'max:50'],
            'province' => ['required', 'in:punjab,sindh,kp,balochistan,federal'],
            'fbr_pos_id' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8'],
        ]);

        $tenant = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'name' => $validated['tenant_name'],
                'ntn' => $validated['ntn'],
                'strn' => $validated['strn'] ?? '',
                'province' => $validated['province'],
                'fbr_pos_id' => $validated['fbr_pos_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            Location::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['tenant_name'] . ' Main Branch',
                'address' => 'Main Commercial Road',
                'province' => $tenant->province,
            ]);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'password' => Hash::make($validated['owner_password']),
                'role' => 'owner',
                'super_admin' => false,
            ]);

            // Queue welcome email with login instructions
            try {
                Mail::to($owner->email)->queue(
                    new TenantWelcomeMail($tenant, $owner, $validated['owner_password'])
                );
            } catch (\Throwable $e) {
                // Non-blocking in dev if mailer fails
            }

            return $tenant;
        });

        return redirect()
            ->route('superadmin.tenants.show', $tenant->id)
            ->with('success', "Tenant '{$tenant->name}' and owner account created successfully.");
    }

    /**
     * Show the tenant edit form.
     */
    public function edit(string $id): Response
    {
        $tenant = Tenant::findOrFail($id);

        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'ntn' => $tenant->ntn,
                'strn' => $tenant->strn,
                'province' => $tenant->province,
                'fbr_pos_id' => $tenant->fbr_pos_id ?? '',
                'notes' => $tenant->notes ?? '',
                'is_suspended' => $tenant->isSuspended(),
                'suspended_at' => $tenant->suspended_at?->format('Y-m-d H:i'),
                'suspension_reason' => $tenant->suspension_reason ?? '',
                'created_at' => $tenant->created_at?->format('Y-m-d H:i') ?? '',
            ],
        ]);
    }

    /**
     * Update tenant business profile and notes.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ntn' => ['required', 'string', 'max:50'],
            'strn' => ['nullable', 'string', 'max:50'],
            'province' => ['required', 'in:punjab,sindh,kp,balochistan,federal'],
            'fbr_pos_id' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->update($validated);

        return redirect()
            ->route('superadmin.tenants.show', $tenant->id)
            ->with('success', "Tenant '{$tenant->name}' profile updated successfully.");
    }

    /**
     * Display comprehensive tenant details, subscription quotas, sales ledger, terminals, and staff.
     */
    public function show(string $id, Request $request): Response
    {
        $tenant = Tenant::with([
            'locations' => function ($q) {
                $q->withoutGlobalScopes()->orderBy('name');
            },
            'users' => function ($q) {
                $q->withoutGlobalScope(TenantScope::class)->orderByRaw("
                    CASE 
                        WHEN role = 'owner' THEN 1 
                        WHEN role = 'manager' THEN 2 
                        ELSE 3 
                    END
                ")->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        // Sales filters & search
        $salesSearch = $request->input('sales_search');
        $salesFbrStatus = $request->input('sales_fbr_status');
        $salesPaymentMethod = $request->input('sales_payment_method');
        $salesStatus = $request->input('sales_status');

        $salesQuery = Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->with(['user:id,name,email', 'location:id,name', 'items'])
            ->latest('created_at');

        if ($salesSearch) {
            $salesQuery->where(function ($q) use ($salesSearch) {
                $q->whereLike('sale_number', "%{$salesSearch}%")
                    ->orWhereLike('fbr_usin', "%{$salesSearch}%");
            });
        }

        if ($salesFbrStatus) {
            $salesQuery->where('fbr_status', $salesFbrStatus);
        }

        if ($salesPaymentMethod) {
            $salesQuery->where('payment_method', $salesPaymentMethod);
        }

        if ($salesStatus) {
            $salesQuery->where('status', $salesStatus);
        }

        $sales = $salesQuery->paginate(15, ['*'], 'sales_page')
            ->withQueryString()
            ->through(fn ($s) => [
                'id' => $s->id,
                'sale_number' => $s->sale_number,
                'payment_method' => $s->payment_method,
                'currency' => $s->currency ?? 'PKR',
                'subtotal_minor' => $s->subtotal_minor,
                'tax_minor' => $s->tax_minor,
                'total_minor' => $s->total_minor,
                'status' => $s->status,
                'fbr_status' => $s->fbr_status,
                'fbr_usin' => $s->fbr_usin,
                'fbr_error' => $s->fbr_error,
                'fbr_submitted_at' => $s->fbr_submitted_at?->format('Y-m-d H:i'),
                'cashier_name' => $s->user?->name ?? 'Unknown Cashier',
                'location_name' => $s->location?->name ?? 'Main Branch',
                'items_count' => $s->items->count(),
                'items' => $s->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'hs_code' => $item->hs_code,
                    'quantity' => $item->quantity,
                    'unit_price_minor' => $item->unit_price_minor,
                    'tax_rate_basis_points' => $item->tax_rate_basis_points ?? 0,
                    'tax_amount_minor' => $item->tax_amount_minor,
                    'line_total_minor' => $item->line_total_minor,
                ]),
                'created_at' => $s->created_at?->format('Y-m-d H:i'),
                'created_at_human' => $s->created_at?->diffForHumans(),
            ]);

        // Financial & Fiscal Aggregates for Tenant
        $sevenDaysAgo = now()->subDays(7);
        $recentSalesCount = Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        $fbrSubmitted = Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('fbr_status', 'submitted')
            ->count();

        $fbrFailed = Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('fbr_status', 'failed')
            ->count();

        $totalSubmissions = $fbrSubmitted + $fbrFailed;
        $fbrSuccessRate = $totalSubmissions > 0
            ? round(($fbrSubmitted / $totalSubmissions) * 100, 1)
            : 0;

        $lifetimeVolumeMinor = (int) Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->sum('total_minor');

        $lifetimeSalesCount = Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->count();

        // Current Subscription & Quota Metrics
        $currentSub = $tenant->currentSubscription();
        $currentPlan = $currentSub?->plan;

        $activeTerminalsCount = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('pairing_status', '!=', 'revoked')
            ->count();

        $locationsCount = $tenant->locations->count();
        $activeStaffCount = $tenant->users->count();
        $productsCount = $tenant->products()->withoutGlobalScopes()->count();

        $subscriptionData = [
            'id' => $currentSub?->id,
            'plan_id' => $currentPlan?->id,
            'plan_name' => $currentPlan?->name ?? 'Free Tier (Default)',
            'plan_code' => $currentPlan?->code ?? 'free',
            'price_minor' => $currentPlan?->price_minor ?? 0,
            'billing_interval' => $currentPlan?->billing_interval ?? 'monthly',
            'status' => $currentSub?->status ?? 'active',
            'starts_at' => $currentSub?->starts_at?->format('Y-m-d') ?? $tenant->created_at?->format('Y-m-d'),
            'ends_at' => $currentSub?->ends_at?->format('Y-m-d'),
            'trial_ends_at' => $currentSub?->trial_ends_at?->format('Y-m-d'),
            'is_active' => $currentSub ? $currentSub->isActive() : true,
            'max_terminals' => $currentPlan?->max_terminals,
            'max_locations' => $currentPlan?->max_locations,
            'max_staff' => $currentPlan?->max_staff,
            'max_skus' => $currentPlan?->max_skus,
            'active_terminals_count' => $activeTerminalsCount,
            'locations_count' => $locationsCount,
            'active_staff_count' => $activeStaffCount,
            'products_count' => $productsCount,
            'features' => $currentPlan?->features ?? ['standard_pos', 'fbr_sync', 'offline_wal'],
            'notes' => $currentSub?->notes ?? '',
        ];

        // All Active Plans for "Change Plan" Modal
        $availablePlans = Plan::where('is_active', true)->orderBy('price_minor')->get()->map(fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'price_minor' => $p->price_minor,
            'billing_interval' => $p->billing_interval,
            'max_locations' => $p->max_locations,
            'max_terminals' => $p->max_terminals,
            'max_staff' => $p->max_staff,
            'max_skus' => $p->max_skus,
            'features' => $p->features ?? [],
        ]);

        // Terminals Fleet for this Tenant
        $terminals = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('location:id,name')
            ->latest('last_heartbeat_at')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'terminal_code' => $t->terminal_code,
                'name' => $t->name,
                'location_id' => $t->location_id,
                'location_name' => $t->location?->name ?? 'Unassigned Branch',
                'platform_os' => $t->platform_os ?? 'windows',
                'app_version' => $t->app_version ?? '0.1.0',
                'ip_address' => $t->ip_address,
                'hostname' => $t->hostname,
                'pairing_status' => $t->pairing_status,
                'pairing_token' => $t->pairing_token,
                'pairing_token_expires_at' => $t->pairing_token_expires_at?->format('Y-m-d H:i'),
                'is_pairing_expired' => $t->pairing_token_expires_at ? $t->pairing_token_expires_at->isPast() : false,
                'is_active' => (bool) $t->is_active,
                'is_online' => $t->isOnline(),
                'revocation_reason' => $t->revocation_reason,
                'last_heartbeat_at' => $t->last_heartbeat_at?->diffForHumans(),
                'last_sync_at' => $t->last_sync_at?->diffForHumans(),
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ]);

        // SaaS Invoices for this Tenant
        $saasInvoices = SaasInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('plan:id,name')
            ->latest('created_at')
            ->take(15)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'plan_name' => $inv->plan?->name ?? 'SaaS Subscription',
                'status' => $inv->status,
                'billing_cycle' => $inv->billing_cycle,
                'period_start' => $inv->period_start?->format('Y-m-d'),
                'period_end' => $inv->period_end?->format('Y-m-d'),
                'total_paisa' => $inv->total_paisa,
                'due_date' => $inv->due_date?->format('Y-m-d'),
                'paid_at' => $inv->paid_at?->format('Y-m-d H:i'),
                'onebill_consumer_number' => $inv->onebill_consumer_number,
                'kuickpay_consumer_number' => $inv->kuickpay_consumer_number,
            ]);

        // Recent Audit Logs for this Tenant
        $auditLogs = AuditLog::where('tenant_id', $tenant->id)
            ->with('user:id,name,email')
            ->latest('created_at')
            ->take(20)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'event' => $log->event,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'user_name' => $log->user?->name ?? 'System',
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                'created_at_human' => $log->created_at?->diffForHumans(),
            ]);

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'ntn' => $tenant->ntn,
                'strn' => $tenant->strn,
                'province' => $tenant->province,
                'fbr_pos_id' => $tenant->fbr_pos_id ?? 'Not Assigned',
                'notes' => $tenant->notes ?? '',
                'created_at' => $tenant->created_at?->format('Y-m-d H:i') ?? '',
                'is_suspended' => $tenant->isSuspended(),
                'suspended_at' => $tenant->suspended_at?->format('Y-m-d H:i'),
                'suspension_reason' => $tenant->suspension_reason ?? '',
                'users' => $tenant->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'two_factor_enabled' => (bool) $u->two_factor_confirmed_at,
                    'created_at' => $u->created_at?->format('Y-m-d H:i') ?? '',
                ]),
                'locations' => $tenant->locations->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'address' => $l->address,
                    'province' => $l->province,
                    'created_at' => $l->created_at?->format('Y-m-d H:i') ?? '',
                ]),
            ],
            'subscription' => $subscriptionData,
            'available_plans' => $availablePlans,
            'sales' => $sales,
            'sales_filters' => [
                'sales_search' => $salesSearch,
                'sales_fbr_status' => $salesFbrStatus,
                'sales_payment_method' => $salesPaymentMethod,
                'sales_status' => $salesStatus,
            ],
            'terminals' => $terminals,
            'saas_invoices' => $saasInvoices,
            'audit_logs' => $auditLogs,
            'recent_sales_count' => $recentSalesCount,
            'lifetime_sales_count' => $lifetimeSalesCount,
            'lifetime_volume_minor' => $lifetimeVolumeMinor,
            'fbr_success_rate' => $fbrSuccessRate,
            'fbr_submitted_count' => $fbrSubmitted,
            'fbr_failed_count' => $fbrFailed,
        ]);
    }

    /**
     * Stream export sales of this tenant to CSV.
     */
    public function exportSales(Request $request, string $id): StreamedResponse
    {
        $tenant = Tenant::findOrFail($id);
        $fileName = 'sales-' . Str::slug($tenant->name) . '-' . now()->format('Ymd-His') . '.csv';

        $search = $request->input('sales_search');
        $fbrStatus = $request->input('sales_fbr_status');
        $paymentMethod = $request->input('sales_payment_method');
        $status = $request->input('sales_status');

        $query = Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->with(['user:id,name', 'location:id,name', 'items'])
            ->latest('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('sale_number', "%{$search}%")
                    ->orWhereLike('fbr_usin', "%{$search}%");
            });
        }

        if ($fbrStatus) {
            $query->where('fbr_status', $fbrStatus);
        }

        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Invoice #',
                'Date & Time (PKT)',
                'Branch Location',
                'Cashier',
                'Payment Method',
                'Items Count',
                'Subtotal (PKR)',
                'Tax Amount (PKR)',
                'Total Amount (PKR)',
                'Sale Status',
                'FBR Fiscal Status',
                'FBR USIN',
                'FBR Error',
            ]);

            $query->chunk(500, function ($sales) use ($handle) {
                foreach ($sales as $sale) {
                    fputcsv($handle, [
                        $sale->sale_number,
                        $sale->created_at?->setTimezone('Asia/Karachi')->format('Y-m-d H:i:s') ?? '',
                        $sale->location?->name ?? 'Main Branch',
                        $sale->user?->name ?? 'Unknown',
                        strtoupper($sale->payment_method),
                        $sale->items->count(),
                        number_format($sale->subtotal_minor / 100, 2, '.', ''),
                        number_format($sale->tax_minor / 100, 2, '.', ''),
                        number_format($sale->total_minor / 100, 2, '.', ''),
                        ucfirst($sale->status),
                        ucfirst($sale->fbr_status),
                        $sale->fbr_usin ?? 'N/A',
                        $sale->fbr_error ?? '',
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Assign, change, or update a subscription plan for this tenant.
     */
    public function assignPlan(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'status' => ['required', 'in:active,trialing,past_due,canceled'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Cancel previous active subscriptions
        Subscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trialing'])
            ->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $validated['status'],
            'starts_at' => $validated['starts_at'] ?? now(),
            'ends_at' => $validated['ends_at'] ?? null,
            'notes' => $validated['notes'] ?? 'Assigned by Super Administrator',
        ]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => Auth::id(),
            'event' => 'subscription_plan_assigned',
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->id,
            'new_values' => [
                'plan_name' => $plan->name,
                'plan_code' => $plan->code,
                'status' => $subscription->status,
                'ends_at' => $subscription->ends_at?->toDateString(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Subscription plan '{$plan->name}' assigned to '{$tenant->name}' successfully.");
    }

    /**
     * Generate an 8-character pairing code for a POS counter terminal for this tenant.
     */
    public function generatePairingCode(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'terminal_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        // Check uniqueness within tenant
        $exists = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('terminal_code', $validated['terminal_code'])
            ->exists();

        if ($exists) {
            return back()->with('error', "Terminal code '{$validated['terminal_code']}' already exists for this tenant.");
        }

        if (! $tenant->canAddTerminal()) {
            $limit = $tenant->currentPlan()?->max_terminals ?? 1;
            return back()->with('error', "Terminal quota exceeded. The tenant's plan allows a maximum of {$limit} active terminals.");
        }

        $pairingToken = strtoupper(Str::random(8));

        $terminal = PosTerminal::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $validated['location_id'] ?? null,
            'terminal_code' => $validated['terminal_code'],
            'name' => $validated['name'],
            'pairing_token' => $pairingToken,
            'pairing_token_expires_at' => now()->addHours(48),
            'pairing_status' => 'pending_pairing',
            'is_active' => true,
        ]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => Auth::id(),
            'event' => 'terminal_pairing_code_generated',
            'auditable_type' => PosTerminal::class,
            'auditable_id' => $terminal->id,
            'new_values' => [
                'terminal_code' => $terminal->terminal_code,
                'pairing_token' => $pairingToken,
                'expires_at' => $terminal->pairing_token_expires_at->toDateTimeString(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Pairing code '{$pairingToken}' generated for terminal '{$terminal->terminal_code}' (valid for 48 hours).");
    }

    /**
     * Revoke an active POS terminal for this tenant.
     */
    public function revokeTerminal(Request $request, string $id, string $terminalId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        $terminal = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($terminalId);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $terminal->update([
            'is_active' => false,
            'pairing_status' => 'revoked',
            'revocation_reason' => $validated['reason'] ?? 'Revoked by Platform Administrator',
        ]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => Auth::id(),
            'event' => 'terminal_revoked',
            'auditable_type' => PosTerminal::class,
            'auditable_id' => $terminal->id,
            'new_values' => ['revocation_reason' => $terminal->revocation_reason],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Terminal '{$terminal->terminal_code}' has been revoked.");
    }

    /**
     * Reactivate a revoked POS terminal for this tenant.
     */
    public function reactivateTerminal(Request $request, string $id, string $terminalId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        $terminal = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($terminalId);

        if (! $tenant->canAddTerminal()) {
            $limit = $tenant->currentPlan()?->max_terminals ?? 1;
            return back()->with('error', "Cannot reactivate: Terminal quota of {$limit} has been reached.");
        }

        $terminal->update([
            'is_active' => true,
            'pairing_status' => 'paired',
            'revocation_reason' => null,
        ]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => Auth::id(),
            'event' => 'terminal_reactivated',
            'auditable_type' => PosTerminal::class,
            'auditable_id' => $terminal->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Terminal '{$terminal->terminal_code}' has been reactivated.");
    }

    /**
     * Toggle suspend / reactivate status for a tenant with an optional reason.
     */
    public function toggleSuspend(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        if ($tenant->isSuspended()) {
            $tenant->update([
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
            $message = "Tenant '{$tenant->name}' has been reactivated.";
        } else {
            $validated = $request->validate([
                'suspension_reason' => ['nullable', 'string', 'max:500'],
            ]);

            $tenant->update([
                'suspended_at' => now(),
                'suspension_reason' => ! empty($validated['suspension_reason']) ? $validated['suspension_reason'] : 'Suspended by Platform Administrator',
            ]);
            $message = "Tenant '{$tenant->name}' has been suspended.";
        }

        return back()->with('success', $message);
    }

    /**
     * Add a new staff user (manager, cashier, or owner) to the tenant.
     */
    public function storeUser(Request $request, string $tenantId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (! $tenant->canAddStaff()) {
            $limit = $tenant->currentPlan()?->max_staff ?? 1;
            return back()->with('error', "Staff quota exceeded. The tenant's plan allows a maximum of {$limit} staff accounts.");
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:owner,manager,cashier'],
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'super_admin' => false,
        ]);

        return back()->with('success', "Staff user '{$user->name}' ({$user->role}) added successfully.");
    }

    /**
     * Remove a staff user from the tenant (with safeguard against removing the last owner).
     */
    public function destroyUser(string $tenantId, string $userId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $user = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->findOrFail($userId);

        if ($user->role === 'owner') {
            $ownerCount = User::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                return back()->with('error', 'Cannot remove the only owner account of this tenant.');
            }
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', "Staff user '{$userName}' removed successfully.");
    }

    /**
     * Reset a staff user's password directly from Super Admin.
     */
    public function resetUserPassword(Request $request, string $tenantId, string $userId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $user = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->findOrFail($userId);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Password for '{$user->name}' ({$user->email}) reset successfully.");
    }

    /**
     * Add an additional branch Location to the tenant.
     */
    public function storeLocation(Request $request, string $tenantId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (! $tenant->canAddLocation()) {
            $limit = $tenant->currentPlan()?->max_locations ?? 1;
            return back()->with('error', "Location quota exceeded. The tenant's plan allows a maximum of {$limit} branch locations.");
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'province' => ['required', 'in:punjab,sindh,kp,balochistan,federal'],
        ]);

        $location = Location::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'address' => $validated['address'],
            'province' => $validated['province'],
        ]);

        return back()->with('success', "Branch location '{$location->name}' added successfully.");
    }

    /**
     * Permanently delete / purge a tenant organization and associated child records.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        // Safeguard: Check if tenant has completed sales submitted to FBR
        $hasFbrSales = Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('fbr_status', 'submitted')
            ->exists();

        if ($hasFbrSales && ! $request->boolean('force_purge_fiscal_archive')) {
            return back()->with('error', "Cannot delete tenant '{$tenant->name}' because it contains official submitted FBR fiscal records. Use 'Suspend' instead to comply with statutory fiscal audit retention.");
        }

        $tenantName = $tenant->name;
        $tenantId = $tenant->id;

        DB::transaction(function () use ($tenant) {
            // Cascade cleanup child records
            PosTerminal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            Subscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            SaasInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();

            // Products, stock movements, and sales
            DB::table('stock_movements')->where('tenant_id', $tenant->id)->delete();
            DB::table('stock_levels')->where('tenant_id', $tenant->id)->delete();
            DB::table('sale_items')->whereIn('sale_id', function ($q) use ($tenant) {
                $q->select('id')->from('sales')->where('tenant_id', $tenant->id);
            })->delete();
            Sale::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id)->delete();
            DB::table('product_variants')->whereIn('product_id', function ($q) use ($tenant) {
                $q->select('id')->from('products')->where('tenant_id', $tenant->id);
            })->delete();
            DB::table('products')->where('tenant_id', $tenant->id)->delete();
            DB::table('tax_rules')->where('tenant_id', $tenant->id)->delete();
            DB::table('fbr_credentials')->where('tenant_id', $tenant->id)->delete();
            DB::table('support_tickets')->where('tenant_id', $tenant->id)->delete();

            // Staff users
            User::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id)->delete();

            // Delete tenant record
            $tenant->delete();
        });

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'tenant_purged',
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenantId,
            'old_values' => ['name' => $tenantName, 'id' => $tenantId],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('superadmin.tenants.index')
            ->with('success', "Tenant '{$tenantName}' and all associated records have been permanently purged.");
    }

    /**
     * Seed an industry catalog directly into this tenant.
     */
    public function seedCatalog(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'catalog_key' => ['required', 'string', 'in:grocery_fmcg,pharmacy'],
        ]);

        try {
            $result = StarterCatalogService::seedToTenant($tenant, $validated['catalog_key']);
            return back()->with('success', $result['message']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to seed catalog: ' . $e->getMessage());
        }
    }
}
