<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminBillingController extends Controller
{
    /**
     * Display the SaaS Invoicing & Payment Gateway Command Center.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('q');
        $status = $request->input('status');
        $tab = $request->input('tab', 'invoices'); // invoices, pending_wires

        $query = SaasInvoice::with([
            'tenant:id,name,province,ntn,strn',
            'plan:id,name,code,price_minor',
            'transactions',
        ])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('invoice_number', "%{$search}%")
                    ->orWhereLike('onebill_consumer_number', "%{$search}%")
                    ->orWhereHas('tenant', fn ($t) => $t->whereLike('name', "%{$search}%"));
            });
        }

        if ($status && in_array($status, ['unpaid', 'pending_review', 'paid', 'void'])) {
            $query->where('status', $status);
        }

        $invoices = $query->paginate(15)->through(fn ($inv) => [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'tenant' => $inv->tenant ? [
                'id' => $inv->tenant->id,
                'name' => $inv->tenant->name,
                'province' => $inv->tenant->province,
                'ntn' => $inv->tenant->ntn,
                'strn' => $inv->tenant->strn,
            ] : null,
            'plan' => $inv->plan ? [
                'id' => $inv->plan->id,
                'name' => $inv->plan->name,
                'code' => $inv->plan->code,
            ] : null,
            'status' => $inv->status,
            'billing_cycle' => $inv->billing_cycle,
            'period_start' => $inv->period_start?->format('Y-m-d'),
            'period_end' => $inv->period_end?->format('Y-m-d'),
            'subtotal_paisa' => $inv->subtotal_paisa,
            'subtotal_rs' => round($inv->subtotal_paisa / 100, 2),
            'tax_paisa' => $inv->tax_paisa,
            'tax_rs' => round($inv->tax_paisa / 100, 2),
            'total_paisa' => $inv->total_paisa,
            'total_rs' => round($inv->total_paisa / 100, 2),
            'tax_rate' => (float) $inv->tax_rate,
            'tax_authority' => $inv->tax_authority,
            'onebill_consumer_number' => $inv->onebill_consumer_number,
            'kuickpay_consumer_number' => $inv->kuickpay_consumer_number,
            'due_date' => $inv->due_date?->format('Y-m-d'),
            'paid_at' => $inv->paid_at?->toIso8601String(),
            'notes' => $inv->notes,
            'has_pending_transaction' => $inv->transactions->where('status', 'pending')->count() > 0,
            'created_at' => $inv->created_at?->toIso8601String(),
        ]);

        $pendingWires = PaymentTransaction::with([
            'invoice:id,invoice_number,total_paisa,due_date',
            'tenant:id,name,province,ntn',
        ])
            ->pending()
            ->latest()
            ->get()
            ->map(fn ($pt) => [
                'id' => $pt->id,
                'invoice_id' => $pt->invoice_id,
                'invoice_number' => $pt->invoice?->invoice_number,
                'tenant' => [
                    'id' => $pt->tenant?->id,
                    'name' => $pt->tenant?->name,
                    'province' => $pt->tenant?->province,
                    'ntn' => $pt->tenant?->ntn,
                ],
                'amount_paisa' => $pt->amount_paisa,
                'amount_rs' => round($pt->amount_paisa / 100, 2),
                'gateway' => $pt->gateway,
                'transaction_reference' => $pt->transaction_reference,
                'bank_name' => $pt->bank_name,
                'deposit_slip_url' => $pt->deposit_slip_url,
                'proof_notes' => $pt->proof_notes,
                'status' => $pt->status,
                'created_at' => $pt->created_at?->toIso8601String(),
            ]);

        $metrics = [
            'total_revenue_rs' => round(SaasInvoice::paid()->sum('total_paisa') / 100, 2),
            'pending_reviews_count' => PaymentTransaction::pending()->count(),
            'unpaid_receivables_rs' => round(SaasInvoice::unpaid()->sum('total_paisa') / 100, 2),
            'paid_invoices_count' => SaasInvoice::paid()->count(),
            'total_invoices_count' => SaasInvoice::count(),
        ];

        $tenants = Tenant::select('id', 'name', 'province', 'ntn', 'strn')
            ->orderBy('name')
            ->limit(200)
            ->get();

        $plans = Plan::where('is_active', true)
            ->select('id', 'name', 'code', 'price_minor', 'billing_interval')
            ->get();

        return Inertia::render('SuperAdmin/Billing/Index', [
            'invoices' => $invoices,
            'pendingWires' => $pendingWires,
            'metrics' => $metrics,
            'tenants' => $tenants,
            'plans' => $plans,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'tab' => $tab,
            ],
        ]);
    }

    /**
     * Generate a new B2B subscription invoice.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'billing_cycle' => ['required', 'string', 'in:monthly,quarterly,annual'],
            'subtotal_rs' => ['required', 'numeric', 'min:0'],
            'tax_authority' => ['required', 'string', 'in:PRA,SRB,KPRA,BRA,FBR'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:30'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $subtotalPaisa = (int) round($validated['subtotal_rs'] * 100);
        $taxPaisa = (int) round($subtotalPaisa * ($validated['tax_rate'] / 100));
        $totalPaisa = $subtotalPaisa + $taxPaisa;

        $periodStart = now();
        $periodEnd = match ($validated['billing_cycle']) {
            'quarterly' => now()->addMonths(3),
            'annual' => now()->addYear(),
            default => now()->addMonth(),
        };

        $invoice = SaasInvoice::create([
            'invoice_number' => SaasInvoice::generateInvoiceNumber(),
            'tenant_id' => $validated['tenant_id'],
            'plan_id' => $validated['plan_id'] ?? null,
            'status' => 'unpaid',
            'billing_cycle' => $validated['billing_cycle'],
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'subtotal_paisa' => $subtotalPaisa,
            'tax_paisa' => $taxPaisa,
            'total_paisa' => $totalPaisa,
            'currency' => 'PKR',
            'tax_rate' => $validated['tax_rate'],
            'tax_authority' => $validated['tax_authority'],
            'onebill_consumer_number' => SaasInvoice::generateOneBillNumber(),
            'kuickpay_consumer_number' => SaasInvoice::generateKuickpayNumber(),
            'due_date' => $validated['due_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', "B2B SaaS Invoice '{$invoice->invoice_number}' issued successfully with 1Bill Consumer # {$invoice->onebill_consumer_number}.");
    }

    /**
     * Submit Bank Wire / Interbank IBFT Payment Proof.
     */
    public function submitBankWire(Request $request, string $id): RedirectResponse
    {
        $invoice = SaasInvoice::findOrFail($id);

        $validated = $request->validate([
            'transaction_reference' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:100'],
            'deposit_slip_url' => ['nullable', 'string', 'max:500'],
            'proof_notes' => ['nullable', 'string', 'max:500'],
        ]);

        PaymentTransaction::create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount_paisa' => $invoice->total_paisa,
            'gateway' => 'bank_wire',
            'status' => 'pending',
            'transaction_reference' => $validated['transaction_reference'],
            'bank_name' => $validated['bank_name'],
            'deposit_slip_url' => $validated['deposit_slip_url'] ?? null,
            'proof_notes' => $validated['proof_notes'] ?? null,
        ]);

        $invoice->update(['status' => 'pending_review']);

        return back()->with('success', "Bank wire transfer proof submitted for invoice {$invoice->invoice_number}. Awaiting platform accounting review.");
    }

    /**
     * Review Bank Wire (Approve or Reject).
     */
    public function reviewPayment(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = PaymentTransaction::with(['invoice', 'tenant'])->findOrFail($transactionId);

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'required_if:action,reject', 'string', 'max:500'],
        ]);

        $invoice = $transaction->invoice;
        $tenant = $transaction->tenant;

        if ($validated['action'] === 'approve') {
            $transaction->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Extend or activate tenant subscription
            if ($invoice->plan_id) {
                $sub = Subscription::firstOrNew(['tenant_id' => $tenant->id]);
                $sub->plan_id = $invoice->plan_id;
                $sub->status = 'active';
                $sub->starts_at = now();
                $sub->ends_at = $invoice->period_end ? $invoice->period_end->endOfDay() : now()->addMonth();
                $sub->save();
            }

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => Auth::id(),
                'event' => 'bank_wire_payment_approved',
                'auditable_type' => PaymentTransaction::class,
                'auditable_id' => $transaction->id,
                'new_values' => [
                    'invoice_number' => $invoice->invoice_number,
                    'amount_paisa' => $transaction->amount_paisa,
                    'transaction_reference' => $transaction->transaction_reference,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->with('success', "Payment of PKR " . number_format($transaction->amount_paisa / 100, 2) . " approved! Invoice {$invoice->invoice_number} marked as PAID and tenant subscription updated.");
        } else {
            $transaction->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            $invoice->update(['status' => 'unpaid']);

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => Auth::id(),
                'event' => 'bank_wire_payment_rejected',
                'auditable_type' => PaymentTransaction::class,
                'auditable_id' => $transaction->id,
                'new_values' => [
                    'invoice_number' => $invoice->invoice_number,
                    'rejection_reason' => $validated['rejection_reason'],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->with('success', "Payment transaction for {$invoice->invoice_number} was rejected.");
        }
    }

    /**
     * Mark an invoice as void.
     */
    public function voidInvoice(string $id): RedirectResponse
    {
        $invoice = SaasInvoice::findOrFail($id);
        $invoice->update(['status' => 'void']);

        return back()->with('success', "Invoice {$invoice->invoice_number} marked as void.");
    }
}
