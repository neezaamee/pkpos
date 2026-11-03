<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KnowledgeBaseArticle;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminSupportController extends Controller
{
    /**
     * Display the centralized helpdesk ticket stream and diagnostics.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $priority = $request->input('priority');
        $category = $request->input('category');
        $tenantId = $request->input('tenant_id');
        $search = $request->input('search');

        $query = SupportTicket::withoutGlobalScopes()
            ->with(['tenant:id,name,province', 'user:id,name,email', 'assignedTo:id,name'])
            ->latest('updated_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'ILIKE', "%{$search}%")
                    ->orWhere('subject', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($sub) use ($search) {
                        $sub->where('name', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $tickets = $query->paginate(20)
            ->withQueryString()
            ->through(fn ($t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'subject' => $t->subject,
                'category' => $t->category,
                'priority' => $t->priority,
                'status' => $t->status,
                'tenant_id' => $t->tenant_id,
                'tenant_name' => $t->tenant?->name ?? 'Unknown Tenant',
                'tenant_province' => $t->tenant?->province ?? '',
                'user_name' => $t->user?->name ?? 'Merchant User',
                'user_email' => $t->user?->email,
                'assigned_to_name' => $t->assignedTo?->name ?? 'Unassigned',
                'has_snapshot' => ! empty($t->diagnostic_snapshot),
                'created_at' => $t->created_at->diffForHumans(),
                'updated_at' => $t->updated_at->diffForHumans(),
            ]);

        $metrics = [
            'open_count' => SupportTicket::withoutGlobalScopes()->whereIn('status', ['open', 'in_progress'])->count(),
            'critical_count' => SupportTicket::withoutGlobalScopes()->where('priority', 'critical_counter_down')->whereIn('status', ['open', 'in_progress'])->count(),
            'resolved_today_count' => SupportTicket::withoutGlobalScopes()->where('status', 'resolved')->whereDate('resolved_at', today())->count(),
            'total_tickets' => SupportTicket::withoutGlobalScopes()->count(),
        ];

        $tenants = Tenant::withoutGlobalScopes()
            ->whereNull('suspended_at')
            ->orderBy('name')
            ->get(['id', 'name', 'province']);

        return Inertia::render('SuperAdmin/Support/Index', [
            'tickets' => $tickets,
            'metrics' => $metrics,
            'tenants' => $tenants,
            'filters' => [
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'tenant_id' => $tenantId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show ticket conversation thread, diagnostic snapshot, and escalation controls.
     */
    public function show(string $id): Response
    {
        $ticket = SupportTicket::withoutGlobalScopes()
            ->with(['tenant.fbrCredential', 'user', 'assignedTo', 'messages.user'])
            ->findOrFail($id);

        $messages = $ticket->messages->map(fn ($m) => [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'user_name' => $m->user?->name ?? 'Unknown',
            'is_staff' => (bool) $m->user?->super_admin,
            'message' => $m->message,
            'is_internal_note' => (bool) $m->is_internal_note,
            'created_at' => $m->created_at->format('Y-m-d H:i:s'),
            'created_ago' => $m->created_at->diffForHumans(),
        ]);

        $staffUsers = User::where('super_admin', true)->get(['id', 'name', 'email']);

        return Inertia::render('SuperAdmin/Support/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'tenant_id' => $ticket->tenant_id,
                'tenant_name' => $ticket->tenant?->name ?? 'Unknown Tenant',
                'tenant_province' => $ticket->tenant?->province ?? '',
                'user_id' => $ticket->user_id,
                'user_name' => $ticket->user?->name ?? 'Merchant User',
                'user_email' => $ticket->user?->email,
                'assigned_to_user_id' => $ticket->assigned_to_user_id,
                'assigned_to_name' => $ticket->assignedTo?->name,
                'diagnostic_snapshot' => $ticket->diagnostic_snapshot,
                'created_at' => $ticket->created_at->format('Y-m-d H:i'),
                'resolved_at' => $ticket->resolved_at?->format('Y-m-d H:i'),
            ],
            'messages' => $messages,
            'staffUsers' => $staffUsers,
        ]);
    }

    /**
     * Reply to the ticket or add an internal staff note.
     */
    public function reply(Request $request, string $id): RedirectResponse
    {
        $ticket = SupportTicket::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'message' => ['required', 'string'],
            'is_internal_note' => ['boolean'],
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_internal_note' => ! empty($validated['is_internal_note']),
        ]);

        // If public reply and ticket was open, mark as in_progress
        if (empty($validated['is_internal_note']) && $ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        AuditLog::create([
            'tenant_id' => $ticket->tenant_id,
            'user_id' => Auth::id(),
            'event' => ! empty($validated['is_internal_note']) ? 'support_internal_note_added' : 'support_reply_sent',
            'auditable_type' => SupportTicket::class,
            'auditable_id' => $ticket->id,
            'new_values' => [
                'ticket_number' => $ticket->ticket_number,
                'is_internal' => ! empty($validated['is_internal_note']),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', ! empty($validated['is_internal_note']) ? 'Internal note added.' : 'Reply sent to merchant.');
    }

    /**
     * Update the status of a support ticket.
     */
    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $ticket = SupportTicket::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,resolved,closed'],
        ]);

        $status = $validated['status'];
        $resolvedAt = in_array($status, ['resolved', 'closed']) ? now() : null;

        $ticket->update([
            'status' => $status,
            'resolved_at' => $resolvedAt,
        ]);

        AuditLog::create([
            'tenant_id' => $ticket->tenant_id,
            'user_id' => Auth::id(),
            'event' => 'support_ticket_status_changed',
            'auditable_type' => SupportTicket::class,
            'auditable_id' => $ticket->id,
            'new_values' => [
                'ticket_number' => $ticket->ticket_number,
                'new_status' => $status,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Ticket '{$ticket->ticket_number}' status changed to {$status}.");
    }

    /**
     * Assign ticket to a platform staff member.
     */
    public function assignStaff(Request $request, string $id): RedirectResponse
    {
        $ticket = SupportTicket::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $ticket->update($validated);

        return back()->with('success', 'Ticket assignment updated successfully.');
    }

    /**
     * Display the Support Knowledge Base repository.
     */
    public function knowledgeBase(Request $request): Response
    {
        $category = $request->input('category');
        $search = $request->input('search');

        $query = KnowledgeBaseArticle::query()->latest();

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('content_markdown', 'ILIKE', "%{$search}%");
            });
        }

        $articles = $query->get()->map(fn ($a) => [
            'id' => $a->id,
            'category' => $a->category,
            'title' => $a->title,
            'slug' => $a->slug,
            'content_markdown' => $a->content_markdown,
            'is_published' => (bool) $a->is_published,
            'updated_at' => $a->updated_at->format('Y-m-d'),
        ]);

        return Inertia::render('SuperAdmin/Support/KnowledgeBase', [
            'articles' => $articles,
            'filters' => [
                'category' => $category,
                'search' => $search,
            ],
        ]);
    }
}

