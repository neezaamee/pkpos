<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PosTerminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    /**
     * Pair a newly installed desktop Tauri POS client with a terminal registration.
     */
    public function pair(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pairing_token' => ['required', 'string', 'max:20'],
            'hardware_uuid' => ['required', 'string', 'max:100'],
            'hostname' => ['nullable', 'string', 'max:100'],
            'platform_os' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        $token = strtoupper(trim($validated['pairing_token']));

        $terminal = PosTerminal::withoutGlobalScopes()
            ->with(['tenant.fbrCredential', 'location'])
            ->where('pairing_token', $token)
            ->where('pairing_status', 'pending_pairing')
            ->where('is_active', true)
            ->first();

        if (! $terminal) {
            return response()->json([
                'message' => 'Invalid pairing code. Please verify the 8-character code from your administrator.',
            ], 422);
        }

        if ($terminal->pairing_token_expires_at && $terminal->pairing_token_expires_at->isPast()) {
            return response()->json([
                'message' => 'This pairing code has expired. Please request a new pairing code.',
            ], 422);
        }

        $tenant = $terminal->tenant;
        if (! $tenant || $tenant->isSuspended()) {
            return response()->json([
                'message' => 'This merchant account is suspended. Pairing cannot continue.',
            ], 403);
        }

        $plan = $tenant->currentPlan();
        if ($plan && $plan->max_terminals !== null) {
            $alreadyPaired = $tenant->terminals()
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->where('pairing_status', 'paired')
                ->where('id', '!=', $terminal->id)
                ->count();

            if ($alreadyPaired >= $plan->max_terminals) {
                return response()->json([
                    'message' => "Terminal quota exceeded. The merchant's plan allows a maximum of {$plan->max_terminals} active terminals.",
                ], 403);
            }
        }

        // Complete the pairing
        $terminal->update([
            'hardware_uuid' => $validated['hardware_uuid'],
            'hostname' => $validated['hostname'] ?? null,
            'platform_os' => $validated['platform_os'] ?? 'windows',
            'app_version' => $validated['app_version'] ?? '0.1.0',
            'ip_address' => $request->ip(),
            'pairing_status' => 'paired',
            'pairing_token' => null,
            'pairing_token_expires_at' => null,
            'last_heartbeat_at' => now(),
            'last_sync_at' => now(),
        ]);

        // Generate Sanctum access token for the terminal using the tenant's primary user
        $user = $tenant->users()->where('role', 'owner')->first() ?? $tenant->users()->first();
        $apiToken = null;
        if ($user) {
            $tokenResult = $user->createToken("pos-terminal:{$terminal->terminal_code}");
            $apiToken = $tokenResult->plainTextToken;
        }

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'event' => 'terminal_paired',
            'auditable_type' => PosTerminal::class,
            'auditable_id' => $terminal->id,
            'new_values' => [
                'terminal_code' => $terminal->terminal_code,
                'hardware_uuid' => $validated['hardware_uuid'],
                'hostname' => $validated['hostname'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'terminal_id' => $terminal->id,
            'terminal_code' => $terminal->terminal_code,
            'name' => $terminal->name,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'location_id' => $terminal->location_id,
            'location_name' => $terminal->location?->name,
            'token' => $apiToken,
            'fbr_pos_id' => $tenant->fbrCredential?->pos_id ?? $tenant->fbr_pos_id ?? 'POS-001',
            'ntn' => $tenant->fbrCredential?->ntn ?? $tenant->ntn ?? '',
            'strn' => $tenant->fbrCredential?->strn ?? $tenant->strn ?? '',
        ]);
    }

    /**
     * POS terminal heartbeat ping to communicate status, check revocation, and report diagnostics.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'terminal_id' => ['nullable', 'uuid'],
            'hardware_uuid' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        $query = PosTerminal::withoutGlobalScopes();

        if (! empty($validated['terminal_id'])) {
            $query->where('id', $validated['terminal_id']);
        } elseif (! empty($validated['hardware_uuid'])) {
            $query->where('hardware_uuid', $validated['hardware_uuid']);
        } else {
            return response()->json(['message' => 'Either terminal_id or hardware_uuid must be provided.'], 422);
        }

        $terminal = $query->first();

        if (! $terminal) {
            return response()->json(['message' => 'Terminal record not found.'], 404);
        }

        // Check if terminal has been remotely revoked
        if ($terminal->pairing_status === 'revoked' || ! $terminal->is_active) {
            return response()->json([
                'error' => 'Terminal revoked',
                'reason' => $terminal->revocation_reason ?? 'Hardware access revoked by platform administrator.',
            ], 403);
        }

        // Update heartbeat
        $terminal->update([
            'last_heartbeat_at' => now(),
            'app_version' => $validated['app_version'] ?? $terminal->app_version,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'healthy',
            'terminal_code' => $terminal->terminal_code,
            'is_active' => true,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}

