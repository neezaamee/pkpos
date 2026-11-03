<?php

use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
        'staff_role' => 'super_admin',
    ]);

    $this->billingSpecialist = User::factory()->superAdmin()->create([
        'email' => 'billing@example.com',
        'staff_role' => 'billing_specialist',
    ]);

    $this->regularUser = User::factory()->create([
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Al-Fatah Department Store',
        'province' => 'punjab',
        'ntn' => '7192834-1',
        'strn' => '03-00-7192-834-11',
    ]);

    $this->plan = Plan::create([
        'code' => 'enterprise-pos',
        'name' => 'Enterprise POS Tier',
        'price_minor' => 1000000, // 10,000 PKR
        'billing_interval' => 'monthly',
        'max_locations' => 5,
        'max_terminals' => 10,
        'is_active' => true,
    ]);
});

test('non-staff user cannot access superadmin billing gateway', function () {
    $this->actingAs($this->regularUser)
        ->get(route('superadmin.billing.index'))
        ->assertForbidden();
});

test('billing specialist and super admin can view billing dashboard with financial telemetry', function () {
    SaasInvoice::create([
        'invoice_number' => 'INV-2026-00001',
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'paid',
        'billing_cycle' => 'monthly',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonth()->toDateString(),
        'subtotal_paisa' => 1000000,
        'tax_paisa' => 160000,
        'total_paisa' => 1160000,
        'currency' => 'PKR',
        'tax_rate' => 16.00,
        'tax_authority' => 'PRA',
        'onebill_consumer_number' => '1004226123456789',
        'due_date' => now()->addDays(7)->toDateString(),
        'paid_at' => now(),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Billing/Index')
            ->has('invoices.data', 1)
            ->where('metrics.total_invoices_count', 1)
            ->where('metrics.paid_invoices_count', 1)
            ->where('metrics.total_revenue_rs', 11600)
        );

    $this->actingAs($this->billingSpecialist)
        ->get(route('superadmin.billing.index'))
        ->assertOk();
});

test('super admin can issue B2B invoice with 1Bill and Kuickpay numbers and provincial tax', function () {
    $payload = [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'billing_cycle' => 'monthly',
        'subtotal_rs' => 10000,
        'tax_authority' => 'PRA',
        'tax_rate' => 16,
        'due_date' => now()->addDays(14)->toDateString(),
        'notes' => 'November 2026 Monthly Subscription Fee',
    ];

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.billing.invoices.store'), $payload);

    $response->assertRedirect();

    $this->assertDatabaseHas('saas_invoices', [
        'tenant_id' => $this->tenant->id,
        'subtotal_paisa' => 1000000,
        'tax_paisa' => 160000,
        'total_paisa' => 1160000,
        'tax_authority' => 'PRA',
        'status' => 'unpaid',
    ]);

    $invoice = SaasInvoice::where('tenant_id', $this->tenant->id)->first();
    expect($invoice->invoice_number)->toStartWith('INV-');
    expect($invoice->onebill_consumer_number)->not->toBeNull();
    expect($invoice->kuickpay_consumer_number)->not->toBeNull();
});

test('validation fails when invoice generation required parameters are missing', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.billing.invoices.store'), []);

    $response->assertSessionHasErrors(['tenant_id', 'billing_cycle', 'subtotal_rs', 'tax_authority', 'tax_rate', 'due_date']);
});

test('merchant or admin can submit bank wire IBFT transfer slip', function () {
    $invoice = SaasInvoice::create([
        'invoice_number' => 'INV-2026-00002',
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'unpaid',
        'billing_cycle' => 'monthly',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonth()->toDateString(),
        'subtotal_paisa' => 1000000,
        'tax_paisa' => 160000,
        'total_paisa' => 1160000,
        'tax_rate' => 16.00,
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.billing.invoices.pay-wire', $invoice->id), [
            'bank_name' => 'Meezan Bank Limited',
            'transaction_reference' => 'MEEZAN-FT-991284',
            'deposit_slip_url' => 'https://cdn.pkpos.pk/slips/meezan-991284.png',
            'proof_notes' => 'Transferred from corporate account.',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('payment_transactions', [
        'invoice_id' => $invoice->id,
        'gateway' => 'bank_wire',
        'status' => 'pending',
        'bank_name' => 'Meezan Bank Limited',
        'transaction_reference' => 'MEEZAN-FT-991284',
    ]);

    expect($invoice->fresh()->status)->toBe('pending_review');
});

test('admin can approve bank wire payment which activates subscription and writes audit log', function () {
    $invoice = SaasInvoice::create([
        'invoice_number' => 'INV-2026-00003',
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'pending_review',
        'billing_cycle' => 'monthly',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonth()->toDateString(),
        'subtotal_paisa' => 1000000,
        'tax_paisa' => 160000,
        'total_paisa' => 1160000,
        'tax_rate' => 16.00,
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    $transaction = PaymentTransaction::create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $this->tenant->id,
        'amount_paisa' => 1160000,
        'gateway' => 'bank_wire',
        'status' => 'pending',
        'bank_name' => 'Habib Bank Limited',
        'transaction_reference' => 'HBL-IBFT-481923',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.billing.transactions.review', $transaction->id), [
            'action' => 'approve',
        ]);

    $response->assertRedirect();

    // Verify transaction and invoice status
    expect($transaction->fresh()->status)->toBe('approved');
    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->paid_at)->not->toBeNull();

    // Verify tenant subscription activated
    $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe('active');
    expect($subscription->plan_id)->toBe($this->plan->id);

    // Verify audit log
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'bank_wire_payment_approved',
        'auditable_type' => PaymentTransaction::class,
        'auditable_id' => $transaction->id,
    ]);
});

test('admin can reject bank wire payment with reason and audit log', function () {
    $invoice = SaasInvoice::create([
        'invoice_number' => 'INV-2026-00004',
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'pending_review',
        'billing_cycle' => 'monthly',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonth()->toDateString(),
        'subtotal_paisa' => 1000000,
        'tax_paisa' => 160000,
        'total_paisa' => 1160000,
        'tax_rate' => 16.00,
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    $transaction = PaymentTransaction::create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $this->tenant->id,
        'amount_paisa' => 1160000,
        'gateway' => 'bank_wire',
        'status' => 'pending',
        'bank_name' => 'Standard Chartered Bank',
        'transaction_reference' => 'SCB-INVALID-REF',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.billing.transactions.review', $transaction->id), [
            'action' => 'reject',
            'rejection_reason' => 'Funds not cleared in Habib Metro account.',
        ]);

    $response->assertRedirect();

    expect($transaction->fresh()->status)->toBe('rejected');
    expect($transaction->fresh()->rejection_reason)->toBe('Funds not cleared in Habib Metro account.');
    expect($invoice->fresh()->status)->toBe('unpaid');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'bank_wire_payment_rejected',
        'auditable_type' => PaymentTransaction::class,
        'auditable_id' => $transaction->id,
    ]);
});

test('admin can mark unpaid invoice as void', function () {
    $invoice = SaasInvoice::create([
        'invoice_number' => 'INV-2026-00005',
        'tenant_id' => $this->tenant->id,
        'status' => 'unpaid',
        'billing_cycle' => 'monthly',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonth()->toDateString(),
        'subtotal_paisa' => 500000,
        'tax_paisa' => 80000,
        'total_paisa' => 580000,
        'tax_rate' => 16.00,
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->post(route('superadmin.billing.invoices.void', $invoice->id));

    $response->assertRedirect();
    expect($invoice->fresh()->status)->toBe('void');
});

