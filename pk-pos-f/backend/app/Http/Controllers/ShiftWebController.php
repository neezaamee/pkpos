<?php

namespace App\Http\Controllers;

use App\Models\RegisterSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftWebController extends Controller
{
    /**
     * Display a listing of register shift sessions and Z-Reports for the tenant.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $shifts = RegisterSession::where('tenant_id', $tenantId)
            ->with(['user:id,name', 'closedByUser:id,name', 'location:id,name', 'terminal:id,name,device_id'])
            ->latest('opened_at')
            ->paginate(15);

        $activeShift = RegisterSession::where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->with(['user:id,name', 'location:id,name', 'terminal:id,name'])
            ->first();

        return Inertia::render('Shifts/Index', [
            'shifts' => $shifts,
            'active_shift' => $activeShift ? $activeShift->toReportSummary('X') : null,
        ]);
    }

    /**
     * Display detailed Z-Report or mid-shift audit for a specific session.
     */
    public function show(Request $request, string $id): Response
    {
        $tenantId = $request->user()->tenant_id;

        $shift = RegisterSession::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with([
                'user:id,name,email',
                'closedByUser:id,name',
                'location:id,name,address',
                'terminal:id,name,device_id',
                'transactions.user:id,name',
                'transactions.authorizedBy:id,name',
            ])
            ->firstOrFail();

        return Inertia::render('Shifts/Show', [
            'shift' => $shift,
            'report_summary' => $shift->toReportSummary($shift->status === 'closed' ? 'Z' : 'X'),
        ]);
    }
}
