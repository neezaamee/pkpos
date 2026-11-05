<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminAuditController extends Controller
{
    /**
     * Display the paginated, searchable audit log stream across the platform.
     */
    public function index(Request $request): Response
    {
        $event = $request->input('event');
        $tenantId = $request->input('tenant_id');
        $auditableType = $request->input('auditable_type');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = AuditLog::withoutGlobalScopes()
            ->with([
                'user' => fn($q) => $q->withoutGlobalScopes(),
                'tenant' => fn($q) => $q->withoutGlobalScopes(),
            ])
            ->latest('created_at');

        if ($event) {
            $query->where('event', $event);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($auditableType) {
            $query->where('auditable_type', 'LIKE', "%{$auditableType}%");
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('auditable_id', "%{$search}%")
                    ->orWhereLike('ip_address', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->whereLike('name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%");
                    })
                    ->orWhereHas('tenant', function ($tq) use ($search) {
                        $tq->whereLike('name', "%{$search}%");
                    });
            });
        }

        $logs = $query->paginate(20)
            ->withQueryString()
            ->through(function ($log) {
                $typeBasename = class_basename($log->auditable_type);

                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'auditable_type' => $typeBasename,
                    'auditable_full_type' => $log->auditable_type,
                    'auditable_id' => $log->auditable_id,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at?->format('Y-m-d H:i:s') ?? '',
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                        'role' => $log->user->role,
                        'is_super_admin' => (bool) $log->user->super_admin,
                    ] : null,
                    'tenant' => $log->tenant ? [
                        'id' => $log->tenant->id,
                        'name' => $log->tenant->name,
                        'province' => $log->tenant->province,
                    ] : null,
                ];
            });

        $tenants = Tenant::orderBy('name')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/AuditLogs/Index', [
            'logs' => $logs,
            'tenants' => $tenants,
            'filters' => [
                'event' => $event,
                'tenant_id' => $tenantId,
                'auditable_type' => $auditableType,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}

