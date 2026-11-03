<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminTerminalController extends Controller
{
    /**
     * Display the fleet of POS terminals across all tenants.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->input('tenant_id');
        $status = $request->input('status');
        $search = $request->input('search');
        $appVersion = $request->input('app_version');

        $query = PosTerminal::withoutGlobalScopes()
            ->with(['tenant:id,name,province', 'location:id,name,province'])
            ->latest('last_heartbeat_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('terminal_code', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%")
                    ->orWhere('hardware_uuid', 'ILIKE', "%{$search}%")
                    ->orWhere('hostname', 'ILIKE', "%{$search}%");
            });
        }

        if ($appVersion) {
            $query->where('app_version', $appVersion);
        }

        if ($status === 'online') {
            $query->where('is_active', true)
                ->where('pairing_status', 'paired')
                ->where('last_heartbeat_at', '>=', now()->subMinutes(15));
        } elseif ($status === 'offline') {
            $query->where('is_active', true)
                ->where('pairing_status', 'paired')
                ->where(function ($q) {
                    $q->whereNull('last_heartbeat_at')
                        ->orWhere('last_heartbeat_at', '<', now()->subMinutes(15));
                });
        } elseif ($status === 'revoked') {
            $query->where(function ($q) {
                $q->where('pairing_status', 'revoked')
                    ->orWhere('is_active', false);
            });
        } elseif ($status === 'pending') {
            $query->where('pairing_status', 'pending_pairing');
        }

        $terminals = $query->paginate(20)
            ->withQueryString()
            ->through(fn ($t) => [
                'id' => $t->id,
                'terminal_code' => $t->terminal_code,
                'name' => $t->name,
                'tenant_id' => $t->tenant_id,
                'tenant_name' => $t->tenant?->name ?? 'Unknown Merchant',
                'location_id' => $t->location_id,
                'location_name' => $t->location?->name ?? 'Unassigned Branch',
                'hardware_uuid' => $t->hardware_uuid,
                'hostname' => $t->hostname,
                'platform_os' => $t->platform_os ?? 'windows',
                'app_version' => $t->app_version ?? '0.1.0',
                'ip_address' => $t->ip_address,
                'pairing_status' => $t->pairing_status,
                'pairing_token' => $t->pairing_token,
                'is_active' => (bool) $t->is_active,
                'is_online' => (bool) ($t->last_heartbeat_at && $t->last_heartbeat_at->greaterThanOrEqualTo(now()->subMinutes(15))),
                'revocation_reason' => $t->revocation_reason,
                'last_heartbeat_at' => $t->last_heartbeat_at?->diffForHumans(),
                'last_heartbeat_raw' => $t->last_heartbeat_at?->toISOString(),
                'last_sync_at' => $t->last_sync_at?->diffForHumans(),
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ]);

        // Platform-wide Fleet Metrics
        $now = now();
        $fifteenMinutesAgo = $now->copy()->subMinutes(15);

        $metrics = [
            'total' => PosTerminal::withoutGlobalScopes()->count(),
            'online' => PosTerminal::withoutGlobalScopes()
                ->where('is_active', true)
                ->where('pairing_status', 'paired')
                ->where('last_heartbeat_at', '>=', $fifteenMinutesAgo)
                ->count(),
            'revoked' => PosTerminal::withoutGlobalScopes()
                ->where(function ($q) {
                    $q->where('pairing_status', 'revoked')
                        ->orWhere('is_active', false);
                })
                ->count(),
            'pending_pairing' => PosTerminal::withoutGlobalScopes()
                ->where('pairing_status', 'pending_pairing')
                ->count(),
        ];

        // Fetch tenants for dropdown
        $tenants = Tenant::withoutGlobalScopes()
            ->whereNull('suspended_at')
            ->orderBy('name')
            ->get(['id', 'name', 'province']);

        // Fetch locations for pairing modal
        $locations = Location::withoutGlobalScopes()
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'province']);

        return Inertia::render('SuperAdmin/Terminals/Index', [
            'terminals' => $terminals,
            'metrics' => $metrics,
            'tenants' => $tenants,
            'locations' => $locations,
            'filters' => [
                'tenant_id' => $tenantId,
                'status' => $status,
                'search' => $search,
                'app_version' => $appVersion,
            ],
        ]);
    }

    /**
     * Generate a new pairing token for a POS counter terminal.
     */
    public function generatePairingCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'terminal_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        // Check uniqueness within tenant
        $exists = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $validated['tenant_id'])
            ->where('terminal_code', $validated['terminal_code'])
            ->exists();

        if ($exists) {
            return back()->with('error', "Terminal code '{$validated['terminal_code']}' already exists for this tenant.");
        }

        // Quota check
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($validated['tenant_id']);
        if (! $tenant->canAddTerminal()) {
            $limit = $tenant->currentPlan()?->max_terminals ?? 1;
            return back()->with('error', "Terminal quota exceeded. Tenant's plan allows a maximum of {$limit} active terminals.");
        }

        $pairingToken = strtoupper(Str::random(8));

        $terminal = PosTerminal::withoutGlobalScopes()->create([
            'tenant_id' => $validated['tenant_id'],
            'location_id' => $validated['location_id'] ?? null,
            'terminal_code' => $validated['terminal_code'],
            'name' => $validated['name'],
            'pairing_token' => $pairingToken,
            'pairing_token_expires_at' => now()->addHours(48),
            'pairing_status' => 'pending_pairing',
            'is_active' => true,
        ]);

        AuditLog::create([
            'tenant_id' => $validated['tenant_id'],
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
     * Update an existing terminal's details.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $terminal = PosTerminal::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'terminal_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
        ]);

        // Check unique terminal_code within tenant excluding current
        $exists = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $terminal->tenant_id)
            ->where('terminal_code', $validated['terminal_code'])
            ->where('id', '!=', $terminal->id)
            ->exists();

        if ($exists) {
            return back()->with('error', "Terminal code '{$validated['terminal_code']}' is already assigned to another terminal.");
        }

        $terminal->update($validated);

        return back()->with('success', "Terminal '{$terminal->terminal_code}' updated successfully.");
    }

    /**
     * Remotely revoke a terminal to prevent further synchronizations and sales.
     */
    public function revoke(Request $request, string $id): RedirectResponse
    {
        $terminal = PosTerminal::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $terminal->update([
            'is_active' => false,
            'pairing_status' => 'revoked',
            'revocation_reason' => $validated['reason'],
        ]);

        AuditLog::create([
            'tenant_id' => $terminal->tenant_id,
            'user_id' => Auth::id(),
            'event' => 'terminal_revoked',
            'auditable_type' => PosTerminal::class,
            'auditable_id' => $terminal->id,
            'new_values' => [
                'terminal_code' => $terminal->terminal_code,
                'reason' => $validated['reason'],
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Terminal '{$terminal->terminal_code}' has been remotely revoked.");
    }

    /**
     * Reactivate a previously revoked terminal.
     */
    public function reactivate(Request $request, string $id): RedirectResponse
    {
        $terminal = PosTerminal::withoutGlobalScopes()->findOrFail($id);

        $terminal->update([
            'is_active' => true,
            'pairing_status' => 'paired',
            'revocation_reason' => null,
        ]);

        AuditLog::create([
            'tenant_id' => $terminal->tenant_id,
            'user_id' => Auth::id(),
            'event' => 'terminal_reactivated',
            'auditable_type' => PosTerminal::class,
            'auditable_id' => $terminal->id,
            'new_values' => [
                'terminal_code' => $terminal->terminal_code,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Terminal '{$terminal->terminal_code}' has been reactivated successfully.");
    }

    /**
     * Delete a terminal record from fleet management.
     */
    public function destroy(string $id): RedirectResponse
    {
        $terminal = PosTerminal::withoutGlobalScopes()->findOrFail($id);
        $code = $terminal->terminal_code;

        $terminal->delete();

        return back()->with('success', "Terminal '{$code}' deleted successfully.");
    }
}

