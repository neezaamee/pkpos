<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PosTerminal;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportApiController extends Controller
{
    /**
     * Submit a support ticket from desktop POS client or merchant portal.
     * Automatically captures a diagnostic snapshot of the environment.
     */
    public function createTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:fbr_sync_failure,hardware_printer,tax_calculation,billing,general'],
            'priority' => ['required', 'string', 'in:critical_counter_down,high,medium,low'],
            'message' => ['required', 'string'],
            'terminal_code' => ['nullable', 'string', 'max:50'],
            'fbr_error' => ['nullable', 'string', 'max:1000'],
            'pending_outbox_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $tenant = $user->tenant;

        if (! $tenant) {
            return response()->json(['error' => 'No active tenant context'], 403);
        }

        $terminal = null;
        if (! empty($validated['terminal_code'])) {
            $terminal = PosTerminal::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('terminal_code', $validated['terminal_code'])
                ->first();
        }

        $snapshot = SupportTicket::buildDiagnosticSnapshot(
            $tenant,
            $terminal,
            $validated['fbr_error'] ?? null,
            (int) ($validated['pending_outbox_count'] ?? 0)
        );

        $ticketNumber = 'TCK-' . strtoupper(Str::random(6));

        $ticket = SupportTicket::withoutGlobalScopes()->create([
            'ticket_number' => $ticketNumber,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'diagnostic_snapshot' => $snapshot,
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_internal_note' => false,
        ]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'event' => 'support_ticket_created',
            'auditable_type' => SupportTicket::class,
            'auditable_id' => $ticket->id,
            'new_values' => [
                'ticket_number' => $ticket->ticket_number,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'subject' => $ticket->subject,
            'message' => 'Your ticket has been logged with our support engineering team.',
        ], 201);
    }

    /**
     * List tickets submitted by the authenticated tenant.
     */
    public function listTickets(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $tickets = SupportTicket::where('tenant_id', $tenant->id)
            ->with(['messages' => function ($q) {
                $q->where('is_internal_note', false)->latest();
            }])
            ->latest()
            ->paginate(15);

        return response()->json($tickets);
    }
}

