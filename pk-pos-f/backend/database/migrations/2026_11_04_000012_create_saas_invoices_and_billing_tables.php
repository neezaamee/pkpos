<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saas_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('status')->default('unpaid'); // unpaid, pending_review, paid, void
            $table->string('billing_cycle')->default('monthly'); // monthly, quarterly, annual
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('subtotal_paisa');
            $table->bigInteger('tax_paisa')->default(0);
            $table->bigInteger('total_paisa');
            $table->string('currency')->default('PKR');
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->string('tax_authority')->nullable(); // PRA, SRB, KPRA, BRA, FBR
            $table->string('onebill_consumer_number')->nullable();
            $table->string('kuickpay_consumer_number')->nullable();
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('onebill_consumer_number');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('saas_invoices')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->bigInteger('amount_paisa');
            $table->string('gateway'); // onebill, kuickpay, bank_wire, manual_cash
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->string('transaction_reference')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('deposit_slip_url')->nullable();
            $table->text('proof_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['gateway', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('saas_invoices');
    }
};

