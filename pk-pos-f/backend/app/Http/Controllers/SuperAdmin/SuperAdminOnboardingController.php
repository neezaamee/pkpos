<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\ProvisionTenantAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FbrCredential;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminOnboardingController extends Controller
{
    /**
     * Display the Merchant Self-Registration & Onboarding Queue.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('q');
        $status = $request->input('status');
        $province = $request->input('province');

        $query = TenantApplication::with([
            'requestedPlan:id,name,code,price_minor',
            'reviewer:id,name',
            'provisionedTenant:id,name',
        ])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'ilike', "%{$search}%")
                    ->orWhere('application_number', 'ilike', "%{$search}%")
                    ->orWhere('contact_name', 'ilike', "%{$search}%")
                    ->orWhere('contact_email', 'ilike', "%{$search}%")
                    ->orWhere('ntn', 'ilike', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['pending_review', 'under_investigation', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }

        if ($province) {
            $query->where('province', strtolower($province));
        }

        $applications = $query->paginate(15)->through(fn ($app) => [
            'id' => $app->id,
            'application_number' => $app->application_number,
            'business_name' => $app->business_name,
            'trade_name' => $app->trade_name,
            'sector' => $app->sector,
            'province' => $app->province,
            'city' => $app->city,
            'contact_name' => $app->contact_name,
            'contact_email' => $app->contact_email,
            'contact_phone' => $app->contact_phone,
            'ntn' => $app->ntn,
            'strn' => $app->strn,
            'is_atl_active' => (bool) $app->is_atl_active,
            'atl_checked_at' => $app->atl_checked_at?->toIso8601String(),
            'atl_taxpayer_name' => $app->atl_taxpayer_name,
            'pos_terminal_count' => $app->pos_terminal_count,
            'requested_plan' => $app->requestedPlan ? [
                'id' => $app->requestedPlan->id,
                'name' => $app->requestedPlan->name,
                'code' => $app->requestedPlan->code,
                'price_rs' => $app->requestedPlan->price_minor / 100,
            ] : null,
            'document_url' => $app->document_url,
            'status' => $app->status,
            'rejection_reason' => $app->rejection_reason,
            'reviewer' => $app->reviewer ? [
                'id' => $app->reviewer->id,
                'name' => $app->reviewer->name,
            ] : null,
            'provisioned_tenant' => $app->provisionedTenant ? [
                'id' => $app->provisionedTenant->id,
                'name' => $app->provisionedTenant->name,
            ] : null,
            'created_at' => $app->created_at?->toIso8601String(),
        ]);

        $metrics = [
            'pending_count' => TenantApplication::pending()->count(),
            'approved_count' => TenantApplication::approved()->count(),
            'rejected_count' => TenantApplication::rejected()->count(),
            'atl_active_count' => TenantApplication::where('is_atl_active', true)->count(),
            'total_applications' => TenantApplication::count(),
        ];

        return Inertia::render('SuperAdmin/Onboarding/Index', [
            'applications' => $applications,
            'metrics' => $metrics,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'province' => $province,
            ],
        ]);
    }

    /**
     * Show applicant dossier and 1-Click Provisioner.
     */
    public function show(string $id): Response
    {
        $app = TenantApplication::with([
            'requestedPlan',
            'reviewer:id,name,email',
            'provisionedTenant',
        ])->findOrFail($id);

        $plans = Plan::where('is_active', true)
            ->select('id', 'name', 'code', 'price_minor', 'max_locations', 'max_terminals')
            ->get();

        return Inertia::render('SuperAdmin/Onboarding/Show', [
            'application' => [
                'id' => $app->id,
                'application_number' => $app->application_number,
                'business_name' => $app->business_name,
                'trade_name' => $app->trade_name,
                'business_type' => $app->business_type,
                'sector' => $app->sector,
                'province' => $app->province,
                'city' => $app->city,
                'address' => $app->address,
                'contact_name' => $app->contact_name,
                'contact_email' => $app->contact_email,
                'contact_phone' => $app->contact_phone,
                'ntn' => $app->ntn,
                'strn' => $app->strn,
                'is_atl_active' => (bool) $app->is_atl_active,
                'atl_checked_at' => $app->atl_checked_at?->toIso8601String(),
                'atl_taxpayer_name' => $app->atl_taxpayer_name,
                'pos_terminal_count' => $app->pos_terminal_count,
                'requested_plan' => $app->requestedPlan,
                'document_url' => $app->document_url,
                'status' => $app->status,
                'rejection_reason' => $app->rejection_reason,
                'internal_notes' => $app->internal_notes,
                'reviewer' => $app->reviewer,
                'reviewed_at' => $app->reviewed_at?->toIso8601String(),
                'provisioned_tenant' => $app->provisionedTenant,
                'created_at' => $app->created_at?->toIso8601String(),
            ],
            'plans' => $plans,
        ]);
    }

    /**
     * Re-verify FBR ATL status for applicant.
     */
    public function checkAtl(string $id): RedirectResponse
    {
        $app = TenantApplication::findOrFail($id);
        $status = $app->verifyFbrAtlStatus();

        $statusStr = $status ? 'ACTIVE (Filer verified)' : 'INACTIVE / NON-FILER';

        return back()->with('success', "FBR Active Taxpayer List (ATL) inquiry returned: {$statusStr}.");
    }

    /**
     * 1-Click Provisioning Engine: Approves application and creates Tenant, User, Location, FbrCredential, Subscription.
     */
    public function approveAndProvision(Request $request, string $id): RedirectResponse
    {
        $app = TenantApplication::findOrFail($id);

        if ($app->status === 'approved' && $app->provisioned_tenant_id) {
            return back()->with('error', 'This application has already been provisioned.');
        }

        $validated = $request->validate([
            'plan_id' => ['nullable', 'exists:plans,id'],
            'temp_password' => ['nullable', 'string', 'min:8'],
            'internal_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $tempPassword = $validated['temp_password'] ?? 'Pos2026!' . Str::random(4);
        $planId = $validated['plan_id'] ?? $app->requested_plan_id ?? Plan::first()?->id;

        $tenant = app(ProvisionTenantAction::class)->execute(
            application: $app,
            tempPassword: $tempPassword,
            planId: $planId,
            staffUserId: Auth::id(),
            internalNotes: $validated['internal_notes'] ?? null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('superadmin.onboarding.show', $app->id)
            ->with('success', "Merchant '{$tenant->name}' has been provisioned! Owner account: {$app->contact_email} (Temp Password: {$tempPassword})");
    }

    /**
     * Reject applicant.
     */
    public function reject(Request $request, string $id): RedirectResponse
    {
        $app = TenantApplication::findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $app->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => Auth::id(),
            'event' => 'merchant_application_rejected',
            'auditable_type' => TenantApplication::class,
            'auditable_id' => $app->id,
            'new_values' => [
                'application_number' => $app->application_number,
                'business_name' => $app->business_name,
                'rejection_reason' => $validated['rejection_reason'],
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Application #{$app->application_number} rejected.");
    }
}

