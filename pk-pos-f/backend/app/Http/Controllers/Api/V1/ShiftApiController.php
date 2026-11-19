<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashDrawerTransaction;
use App\Models\RegisterSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftApiController extends Controller
{
    /**
     * Authenticate cashier by 4-6 digit numeric PIN for swift terminal switching.
     */
    public function authenticatePin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:8'],
        ]);

        $currentUser = $request->user();
        $tenantId = $currentUser->tenant_id;

        // Search active users within tenant who match this PIN
        $users = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $matchedUser = null;
        foreach ($users as $u) {
            if ($u->verifyPin($validated['pin'])) {
                $matchedUser = $u;
                break;
            }
        }

        if (! $matchedUser) {
            return response()->json([
                'message' => 'Invalid cashier PIN.',
            ], 401);
        }

        return response()->json([
            'user' => [
                'id' => $matchedUser->id,
                'name' => $matchedUser->name,
                'email' => $matchedUser->email,
                'role' => $matchedUser->role,
                'max_discount_pct' => $matchedUser->max_discount_pct ?? 10,
                'pos_permissions' => $matchedUser->pos_permissions ?? [
                    'apply_discount',
                    'open_drawer',
                    'reprint_receipt',
                ],
                'is_supervisor' => $matchedUser->isPosSupervisor(),
            ],
            'message' => "Welcome, {$matchedUser->name}",
        ]);
    }

    /**
     * Verify supervisor PIN for privileged POS actions (excessive discount, void, refund).
     */
    public function supervisorOverride(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:8'],
            'action' => ['required', 'string'],
        ]);

        $currentUser = $request->user();
        $tenantId = $currentUser->tenant_id;

        $supervisors = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn(User $u) => $u->isPosSupervisor());

        $matchedSupervisor = null;
        foreach ($supervisors as $s) {
            if ($s->verifyPin($validated['pin'])) {
                $matchedSupervisor = $s;
                break;
            }
        }

        if (! $matchedSupervisor) {
            return response()->json([
                'authorized' => false,
                'message' => 'Invalid supervisor PIN or unauthorized personnel.',
            ], 403);
        }

        return response()->json([
            'authorized' => true,
            'supervisor' => [
                'id' => $matchedSupervisor->id,
                'name' => $matchedSupervisor->name,
                'role' => $matchedSupervisor->role,
            ],
            'action' => $validated['action'],
            'message' => "Authorized by {$matchedSupervisor->name}",
        ]);
    }

    /**
     * Get active register shift for the terminal/location.
     */
    public function currentShift(Request $request): JsonResponse
    {
        $user = $request->user();
        $locationId = $request->query('location_id');
        $terminalId = $request->query('terminal_id');

        $query = RegisterSession::where('tenant_id', $user->tenant_id)
            ->where('status', 'open')
            ->with(['user:id,name,role', 'terminal:id,name,device_id', 'location:id,name']);

        if ($terminalId) {
            $query->where('terminal_id', $terminalId);
        } elseif ($locationId) {
            $query->where('location_id', $locationId);
        }

        $session = $query->latest('opened_at')->first();

        if (! $session) {
            return response()->json([
                'active' => false,
                'message' => 'No active shift is currently open on this terminal.',
            ]);
        }

        $session->recalculateExpectedCash();

        return response()->json([
            'active' => true,
            'session' => $session,
            'summary' => $session->toReportSummary('X'),
        ]);
    }

    /**
     * Open a new register shift with starting float.
     */
    public function openShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'terminal_id' => ['nullable', 'uuid', 'exists:pos_terminals,id'],
            'opening_float_minor' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        // Check if an open shift already exists for this terminal/location
        $existing = RegisterSession::where('tenant_id', $user->tenant_id)
            ->where('status', 'open')
            ->when($validated['terminal_id'] ?? null, fn($q, $tid) => $q->where('terminal_id', $tid))
            ->when(! ($validated['terminal_id'] ?? null), fn($q) => $q->where('location_id', $validated['location_id']))
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'An active shift is already open for this terminal. Please close the active shift first.',
                'session' => $existing,
            ], 422);
        }

        $session = RegisterSession::create([
            'tenant_id' => $user->tenant_id,
            'location_id' => $validated['location_id'],
            'terminal_id' => $validated['terminal_id'] ?? null,
            'user_id' => $user->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_float_minor' => $validated['opening_float_minor'],
            'expected_cash_minor' => $validated['opening_float_minor'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Record opening float transaction in cash drawer ledger
        CashDrawerTransaction::create([
            'tenant_id' => $user->tenant_id,
            'register_session_id' => $session->id,
            'user_id' => $user->id,
            'type' => 'opening_float',
            'amount_minor' => $validated['opening_float_minor'],
            'balance_after_minor' => $validated['opening_float_minor'],
            'reason' => 'Starting Register Opening Float',
        ]);

        return response()->json([
            'message' => 'Shift opened successfully. Register is now operational.',
            'session' => $session->load(['user:id,name', 'location:id,name', 'terminal:id,name']),
        ], 201);
    }

    /**
     * Record cash movement in drawer (cash in, safe drop, payout expense).
     */
    public function recordCashMovement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'register_session_id' => ['required', 'uuid', 'exists:register_sessions,id'],
            'type' => ['required', 'string', 'in:cash_in,cash_drop,payout_expense'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'supervisor_pin' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $session = RegisterSession::where('tenant_id', $user->tenant_id)
            ->where('id', $validated['register_session_id'])
            ->where('status', 'open')
            ->firstOrFail();

        $authorizedBy = null;
        if (! empty($validated['supervisor_pin'])) {
            $supervisors = User::where('tenant_id', $user->tenant_id)->get()->filter(fn(User $u) => $u->isPosSupervisor());
            foreach ($supervisors as $s) {
                if ($s->verifyPin($validated['supervisor_pin'])) {
                    $authorizedBy = $s;
                    break;
                }
            }
        }

        $type = $validated['type'];
        $amount = $validated['amount_minor'];

        if ($type === 'cash_in') {
            $session->cash_in_minor += $amount;
        } elseif ($type === 'cash_drop') {
            $session->cash_drop_minor += $amount;
        } elseif ($type === 'payout_expense') {
            $session->payout_minor += $amount;
        }

        $session->recalculateExpectedCash();
        $session->save();

        $transaction = CashDrawerTransaction::create([
            'tenant_id' => $user->tenant_id,
            'register_session_id' => $session->id,
            'user_id' => $user->id,
            'authorized_by_user_id' => $authorizedBy?->id,
            'type' => $type,
            'amount_minor' => $amount,
            'balance_after_minor' => $session->expected_cash_minor,
            'reason' => $validated['reason'],
        ]);

        return response()->json([
            'message' => 'Cash movement recorded successfully.',
            'transaction' => $transaction,
            'expected_cash_minor' => $session->expected_cash_minor,
        ]);
    }

    /**
     * Generate real-time X-Report summary (mid-shift snapshot without closing).
     */
    public function generateXReport(Request $request, string $sessionId): JsonResponse
    {
        $user = $request->user();
        $session = RegisterSession::where('tenant_id', $user->tenant_id)
            ->where('id', $sessionId)
            ->with(['user', 'closedByUser', 'location', 'terminal'])
            ->firstOrFail();

        return response()->json([
            'report' => $session->toReportSummary('X'),
        ]);
    }

    /**
     * Close the register shift, perform cash reconciliation, and generate Z-Report.
     */
    public function closeShift(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'closing_cash_minor' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $session = RegisterSession::where('tenant_id', $user->tenant_id)
            ->where('id', $sessionId)
            ->where('status', 'open')
            ->firstOrFail();

        $session->closeSession($validated['closing_cash_minor'], $user, $validated['notes'] ?? null);

        return response()->json([
            'message' => 'Shift closed successfully. Official Z-Report generated.',
            'session' => $session->load(['user:id,name', 'closedByUser:id,name', 'location:id,name']),
            'z_report' => $session->toReportSummary('Z'),
        ]);
    }

    /**
     * Get shift history for terminal or location.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 15), 50);

        $sessions = RegisterSession::where('tenant_id', $user->tenant_id)
            ->with(['user:id,name', 'closedByUser:id,name', 'location:id,name', 'terminal:id,name,device_id'])
            ->latest('opened_at')
            ->paginate($limit);

        return response()->json($sessions);
    }
}
