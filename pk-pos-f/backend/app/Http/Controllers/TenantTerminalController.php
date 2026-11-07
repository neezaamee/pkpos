<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\PosTerminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantTerminalController extends Controller
{
    /**
     * Display all POS counter terminals belonging to the merchant's store.
     */
    public function index(Request $request): Response
    {
        $tenantId = Auth::user()->tenant_id;
        $tenant = Auth::user()->tenant;

        $fifteenMinutesAgo = now()->subMinutes(15);

        $terminals = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['location:id,name,province'])
            ->orderBy('terminal_code')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'terminal_code' => $t->terminal_code,
                'name' => $t->name,
                'location_id' => $t->location_id,
                'location_name' => $t->location?->name ?? 'Default Branch',
                'hardware_uuid' => $t->hardware_uuid,
                'hostname' => $t->hostname,
                'platform_os' => $t->platform_os ?? 'windows',
                'app_version' => $t->app_version ?? '0.1.0',
                'ip_address' => $t->ip_address,
                'pairing_status' => $t->pairing_status,
                'pairing_token' => $t->pairing_token,
                'pairing_token_expires_at' => $t->pairing_token_expires_at?->diffForHumans(),
                'is_active' => (bool) $t->is_active,
                'is_online' => (bool) ($t->last_heartbeat_at && $t->last_heartbeat_at->greaterThanOrEqualTo($fifteenMinutesAgo)),
                'last_heartbeat_at' => $t->last_heartbeat_at?->diffForHumans(),
                'last_sync_at' => $t->last_sync_at?->diffForHumans(),
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ]);

        $metrics = [
            'total' => $terminals->count(),
            'online' => $terminals->where('is_online', true)->count(),
            'offline' => $terminals->where('pairing_status', 'paired')->where('is_online', false)->count(),
            'pending_pairing' => $terminals->where('pairing_status', 'pending_pairing')->count(),
        ];

        $plan = $tenant?->currentPlan();
        $quota = [
            'can_add' => $tenant ? $tenant->canAddTerminal() : true,
            'max_terminals' => $plan?->max_terminals ?? 'Unlimited',
        ];

        $locations = Location::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Terminals/Index', [
            'terminals' => $terminals,
            'metrics' => $metrics,
            'quota' => $quota,
            'locations' => $locations,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
        ]);
    }

    /**
     * Generate a new pairing token for a new counter terminal.
     */
    public function generatePairingCode(Request $request): RedirectResponse
    {
        $tenant = Auth::user()->tenant;
        if (! $tenant) {
            abort(403, 'No merchant tenant associated with this account.');
        }

        if (! $tenant->canAddTerminal()) {
            $limit = $tenant->currentPlan()?->max_terminals ?? 1;
            return back()->with('error', "Terminal quota reached. Your current plan allows a maximum of {$limit} active terminals.");
        }

        $validated = $request->validate([
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'terminal_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        // Enforce uniqueness within this merchant
        $exists = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('terminal_code', $validated['terminal_code'])
            ->exists();

        if ($exists) {
            return back()->with('error', "Terminal code '{$validated['terminal_code']}' already exists in your store.");
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
            'event' => 'merchant_terminal_pairing_code_generated',
            'auditable_type' => PosTerminal::class,
            'auditable_id' => $terminal->id,
            'new_values' => [
                'terminal_code' => $terminal->terminal_code,
                'pairing_token' => $pairingToken,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Pairing code '{$pairingToken}' generated for counter '{$terminal->terminal_code}'.");
    }

    /**
     * Regenerate an active pairing code for an existing pending terminal.
     */
    public function regeneratePairingCode(Request $request, string $id): RedirectResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $terminal = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $newToken = strtoupper(Str::random(8));

        $terminal->update([
            'pairing_token' => $newToken,
            'pairing_token_expires_at' => now()->addHours(48),
            'pairing_status' => 'pending_pairing',
        ]);

        return back()->with('success', "New pairing code '{$newToken}' generated for counter '{$terminal->terminal_code}'.");
    }

    /**
     * Delete or unpair a counter terminal.
     */
    public function destroy(string $id): RedirectResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $terminal = PosTerminal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $code = $terminal->terminal_code;
        $terminal->delete();

        return back()->with('success', "Terminal '{$code}' removed successfully.");
    }
}
