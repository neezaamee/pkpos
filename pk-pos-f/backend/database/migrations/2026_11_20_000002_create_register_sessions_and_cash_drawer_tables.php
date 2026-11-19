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
        Schema::create('register_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('terminal_id')->nullable()->constrained('pos_terminals')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('z_report_number', 50)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            $table->bigInteger('opening_float_minor')->default(0);
            $table->bigInteger('cash_sales_minor')->default(0);
            $table->bigInteger('card_sales_minor')->default(0);
            $table->bigInteger('raast_sales_minor')->default(0);
            $table->bigInteger('wallet_sales_minor')->default(0);
            $table->bigInteger('cash_in_minor')->default(0);
            $table->bigInteger('cash_drop_minor')->default(0);
            $table->bigInteger('payout_minor')->default(0);
            $table->bigInteger('refund_cash_minor')->default(0);

            $table->bigInteger('expected_cash_minor')->default(0);
            $table->bigInteger('closing_cash_minor')->nullable();
            $table->bigInteger('variance_minor')->nullable();

            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('refunds_count')->default(0);
            $table->bigInteger('tax_collected_minor')->default(0);
            $table->bigInteger('gross_sales_minor')->default(0);

            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'location_id', 'status']);
            $table->index(['tenant_id', 'terminal_id', 'status']);
            $table->index('opened_at');
        });

        Schema::create('cash_drawer_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('register_session_id')->constrained('register_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('sale_id')->nullable()->constrained('sales')->nullOnDelete();

            $table->enum('type', [
                'opening_float',
                'cash_in',
                'cash_drop',
                'payout_expense',
                'sale',
                'refund',
            ]);

            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor')->default(0);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'register_session_id']);
            $table->index(['register_session_id', 'type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_transactions');
        Schema::dropIfExists('register_sessions');
    }
};
