<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\TenantWelcomeMail;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminTenantController extends Controller
{
    /**
     * Display a paginated list of tenants with search.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $tenants = Tenant::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('ntn', 'ILIKE', "%{$search}%");
                });
            })
            ->with(['users' => function ($q) {
                $q->withoutGlobalScope(TenantScope::class)->where('role', 'owner');
            }])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function ($tenant) {
                $owner = $tenant->users->first();

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'ntn' => $tenant->ntn,
                    'strn' => $tenant->strn,
                    'province' => $tenant->province,
                    'owner_email' => $owner?->email ?? 'N/A',
                    'owner_id' => $owner?->id,
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
     * Display tenant detail, all staff users, locations, sales stats, and FBR rates.
     */
    public function show(string $id): Response
    {
        $tenant = Tenant::with([
            'locations',
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

        $sevenDaysAgo = now()->subDays(7);

        // Recent sales count (last 7 days)
        $recentSalesCount = Sale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        // FBR submission success rate
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
            'recent_sales_count' => $recentSalesCount,
            'fbr_success_rate' => $fbrSuccessRate,
            'fbr_submitted_count' => $fbrSubmitted,
            'fbr_failed_count' => $fbrFailed,
        ]);
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
}
