<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('phone', 50);
            $table->string('email', 150)->nullable();
            $table->string('cnic', 20)->nullable(); // Required by FBR for B2C sales > 100k
            $table->string('ntn', 30)->nullable();  // For B2B buyers
            $table->string('strn', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();

            $table->bigInteger('credit_limit_minor')->default(0); // 0 = no credit ceiling (or cash only)
            $table->bigInteger('current_balance_minor')->default(0); // Positive = customer owes store (debit)
            $table->integer('loyalty_points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
            $table->index('cnic');
        });

        Schema::create('customer_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignUuid('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', [
                'debit_sale',        // Udhaar / credit purchase (+ balance)
                'credit_payment',    // Debt settlement payment (- balance)
                'debit_adjustment',  // Manual debit adjustment
                'credit_return',     // Refund credited to account (- balance)
            ]);

            $table->bigInteger('amount_minor');
            $table->bigInteger('running_balance_minor');
            $table->string('payment_method', 50)->nullable(); // cash, raast, bank_transfer, cheque
            $table->string('receipt_number', 50)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['customer_id', 'created_at']);
            $table->index('receipt_number');
        });

        // Add customer_id and loosen payment_method enum on sales table
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->nullable()->after('tenant_id')->constrained('customers')->nullOnDelete();
        });

        // If PostgreSQL, drop check constraint on payment_method to allow 'credit'
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_payment_method_check');
            DB::statement('ALTER TABLE sales ALTER COLUMN payment_method TYPE VARCHAR(50)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customer_transactions');
        Schema::dropIfExists('customers');
    }
};
