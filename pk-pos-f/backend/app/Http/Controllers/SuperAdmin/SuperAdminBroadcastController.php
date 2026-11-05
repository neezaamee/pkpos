<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BroadcastAnnouncement;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminBroadcastController extends Controller
{
    /**
     * Display the Platform Broadcasts & Emergency Alerts Directory.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('q');
        $level = $request->input('level');
        $status = $request->input('status');

        $query = BroadcastAnnouncement::with([
            'creator:id,name,email',
            'targetTenant:id,name,province',
        ])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('title', "%{$search}%")
                    ->orWhereLike('message', "%{$search}%");
            });
        }

        if ($level && in_array($level, ['info', 'warning', 'critical_emergency'])) {
            $query->where('level', $level);
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'expired') {
            $query->whereNotNull('expires_at')->where('expires_at', '<', now());
        }

        $broadcasts = $query->paginate(15)->through(fn ($b) => [
            'id' => $b->id,
            'title' => $b->title,
            'message' => $b->message,
            'level' => $b->level,
            'target_audience' => $b->target_audience,
            'target_province' => $b->target_province,
            'target_tenant' => $b->targetTenant ? [
                'id' => $b->targetTenant->id,
                'name' => $b->targetTenant->name,
                'province' => $b->targetTenant->province,
            ] : null,
            'dismissible' => (bool) $b->dismissible,
            'is_active' => (bool) $b->is_active,
            'is_live' => (bool) ($b->is_active && (! $b->starts_at || $b->starts_at->isPast()) && (! $b->expires_at || $b->expires_at->isFuture())),
            'starts_at' => $b->starts_at?->toIso8601String(),
            'expires_at' => $b->expires_at?->toIso8601String(),
            'creator' => $b->creator ? [
                'id' => $b->creator->id,
                'name' => $b->creator->name,
            ] : null,
            'created_at' => $b->created_at?->toIso8601String(),
        ]);

        $metrics = [
            'total_broadcasts' => BroadcastAnnouncement::count(),
            'active_live' => BroadcastAnnouncement::active()->count(),
            'critical_emergency' => BroadcastAnnouncement::active()->where('level', 'critical_emergency')->count(),
            'desktop_targeted' => BroadcastAnnouncement::active()->where('target_audience', 'desktop_terminals_only')->count(),
        ];

        $tenants = Tenant::select('id', 'name', 'province')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return Inertia::render('SuperAdmin/Broadcasts/Index', [
            'broadcasts' => $broadcasts,
            'metrics' => $metrics,
            'tenants' => $tenants,
            'filters' => [
                'q' => $search,
                'level' => $level,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Store a new platform broadcast announcement.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'level' => ['required', 'string', 'in:info,warning,critical_emergency'],
            'target_audience' => ['required', 'string', 'in:all,province,desktop_terminals_only,tenant'],
            'target_province' => ['nullable', 'required_if:target_audience,province', 'string', 'in:punjab,sindh,kpk,balochistan,islamabad'],
            'target_tenant_id' => ['nullable', 'required_if:target_audience,tenant', 'exists:tenants,id'],
            'dismissible' => ['boolean'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $broadcast = BroadcastAnnouncement::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'level' => $validated['level'],
            'target_audience' => $validated['target_audience'],
            'target_province' => $validated['target_audience'] === 'province' ? strtolower($validated['target_province']) : null,
            'target_tenant_id' => $validated['target_audience'] === 'tenant' ? $validated['target_tenant_id'] : null,
            'dismissible' => $validated['dismissible'] ?? true,
            'is_active' => $validated['is_active'] ?? true,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', "Broadcast announcement '{$broadcast->title}' published successfully.");
    }

    /**
     * Update an existing broadcast announcement.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $broadcast = BroadcastAnnouncement::findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'level' => ['required', 'string', 'in:info,warning,critical_emergency'],
            'target_audience' => ['required', 'string', 'in:all,province,desktop_terminals_only,tenant'],
            'target_province' => ['nullable', 'required_if:target_audience,province', 'string', 'in:punjab,sindh,kpk,balochistan,islamabad'],
            'target_tenant_id' => ['nullable', 'required_if:target_audience,tenant', 'exists:tenants,id'],
            'dismissible' => ['boolean'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $broadcast->update([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'level' => $validated['level'],
            'target_audience' => $validated['target_audience'],
            'target_province' => $validated['target_audience'] === 'province' ? strtolower($validated['target_province']) : null,
            'target_tenant_id' => $validated['target_audience'] === 'tenant' ? $validated['target_tenant_id'] : null,
            'dismissible' => $validated['dismissible'] ?? true,
            'is_active' => $validated['is_active'] ?? true,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return back()->with('success', "Broadcast announcement '{$broadcast->title}' updated successfully.");
    }

    /**
     * Quickly toggle an announcement's active status.
     */
    public function toggleActive(string $id): RedirectResponse
    {
        $broadcast = BroadcastAnnouncement::findOrFail($id);
        $broadcast->is_active = ! $broadcast->is_active;
        $broadcast->save();

        $statusStr = $broadcast->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Broadcast '{$broadcast->title}' {$statusStr}.");
    }

    /**
     * Delete an announcement.
     */
    public function destroy(string $id): RedirectResponse
    {
        $broadcast = BroadcastAnnouncement::findOrFail($id);
        $title = $broadcast->title;
        $broadcast->delete();

        return back()->with('success', "Broadcast '{$title}' deleted.");
    }
}

