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
        // 1. Chart of Accounts table
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();

            $table->string('code', 50); // e.g. '1011', '2011', '4010'
            $table->string('name', 255);
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->string('sub_type', 100); // e.g. 'cash', 'bank', 'receivable', 'inventory', 'payable', 'tax', 'operating_revenue', 'direct_cost', 'operating_expense'
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('currency', 3)->default('PKR');

            $table->bigInteger('opening_balance_minor')->default(0);
            $table->bigInteger('current_balance_minor')->default(0);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'sub_type']);
            $table->index(['tenant_id', 'parent_id']);
            $table->index('code');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('accounts')->nullOnDelete();
        });

        // 2. Accounting Settings for default system account mapping
        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete()->unique();

            $table->foreignUuid('default_cash_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_bank_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_ar_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_inventory_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_ap_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_sales_tax_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_sales_revenue_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_sales_return_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_sales_discount_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_cogs_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('default_retained_earnings_account_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->timestamps();
        });

        // 3. General Journal Entries (Header)
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entry_number', 64);
            $table->date('entry_date');

            $table->string('source_type', 64)->nullable(); // 'sale', 'sale_return', 'grn_receipt', 'customer_payment', 'supplier_payment', 'cash_drawer', 'manual'
            $table->uuid('source_id')->nullable();
            $table->string('reference', 128)->nullable(); // e.g. 'INV-001', 'PO-002', 'RET-003'
            $table->text('description')->nullable();

            $table->enum('status', ['draft', 'posted', 'voided'])->default('posted');
            $table->bigInteger('total_debit_minor')->default(0);
            $table->bigInteger('total_credit_minor')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'entry_number']);
            $table->index(['tenant_id', 'entry_date']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
            $table->index(['tenant_id', 'status']);
        });

        // 4. Journal Entry Lines (Debits and Credits)
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();

            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->string('memo', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'account_id']);
            $table->index('journal_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('accounts');
    }
};
