<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryWebController extends Controller
{
    /**
     * Display listing of General Journal entries.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $sourceType = $request->query('source_type');
        $status = $request->query('status');
        $search = $request->query('search');

        $query = JournalEntry::where('tenant_id', $tenantId)
            ->with(['createdByUser:id,name', 'lines.account:id,code,name,type'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($sourceType) {
            $query->where('source_type', $sourceType);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $entries = $query->paginate(20)->withQueryString();

        $metrics = [
            'total_entries' => JournalEntry::where('tenant_id', $tenantId)->count(),
            'manual_entries' => JournalEntry::where('tenant_id', $tenantId)->where('source_type', 'manual')->count(),
            'automated_entries' => JournalEntry::where('tenant_id', $tenantId)->where('source_type', '!=', 'manual')->count(),
        ];

        return Inertia::render('Accounting/Journal/Index', [
            'entries' => $entries,
            'filters' => [
                'source_type' => $sourceType,
                'status' => $status,
                'search' => $search,
            ],
            'metrics' => $metrics,
        ]);
    }

    /**
     * Show form for creating a manual journal entry.
     */
    public function create(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $accounts = Account::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance']);

        return Inertia::render('Accounting/Journal/Create', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store and post a manual journal entry.
     */
    public function store(Request $request, AccountingService $accountingService): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:128'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'lines.*.debit_minor' => ['required', 'integer', 'min:0'],
            'lines.*.credit_minor' => ['required', 'integer', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        // Validate debits == credits
        $totalDebit = array_sum(array_column($validated['lines'], 'debit_minor'));
        $totalCredit = array_sum(array_column($validated['lines'], 'credit_minor'));

        if ($totalDebit !== $totalCredit) {
            $diffRs = abs($totalDebit - $totalCredit) / 100;
            return back()->withErrors([
                'lines' => "Journal entry is unbalanced. Total Debits (Rs. " . number_format($totalDebit / 100) . ") must equal Total Credits (Rs. " . number_format($totalCredit / 100) . "). Difference is Rs. {$diffRs}.",
            ])->withInput();
        }

        if ($totalDebit <= 0) {
            return back()->withErrors([
                'lines' => 'Total entry amount must be greater than zero.',
            ])->withInput();
        }

        $entry = $accountingService->postManualJournal($validated, $request->user());

        return redirect()->route('journal-entries.show', $entry->id)
            ->with('success', "Journal Entry #{$entry->entry_number} posted successfully.");
    }

    /**
     * Display a specific journal entry with lines.
     */
    public function show(Request $request, string $id): Response
    {
        $tenantId = $request->user()->tenant_id;

        $entry = JournalEntry::where('tenant_id', $tenantId)
            ->with(['createdByUser:id,name', 'lines.account:id,code,name,type,normal_balance'])
            ->findOrFail($id);

        return Inertia::render('Accounting/Journal/Show', [
            'entry' => $entry,
        ]);
    }
}
