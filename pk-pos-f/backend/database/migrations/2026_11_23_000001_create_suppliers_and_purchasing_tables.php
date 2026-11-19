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
        // 1. Suppliers Table
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150); // Company or Supplier Name
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 50);
            $table->string('email', 150)->nullable();
            $table->string('cnic', 20)->nullable();
            $table->string('ntn', 30)->nullable(); // Statutory NTN for procurement
            $table->string('strn', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->integer('payment_terms_days')->default(0); // 0 = Cash, 15, 30 days
            $table->bigInteger('current_balance_minor')->default(0); // Positive = store owes supplier (payable)
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'name']);
        });

        // 2. Purchase Orders Table
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('po_number', 50); // e.g. PO-20261122-0001
            $table->string('status', 30)->default('draft'); // draft, ordered, partial_received, received, canceled
            $table->date('expected_delivery_date')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'po_number'], 'purchase_orders_tenant_po_number_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        // 3. Purchase Order Items Table
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('unit_code', 20)->default('pcs');
            $table->decimal('unit_conversion_factor', 12, 3)->default(1.000);
            $table->decimal('ordered_quantity', 12, 3);
            $table->decimal('received_quantity', 12, 3)->default(0.000);
            $table->bigInteger('cost_price_minor'); // Unit purchase cost
            $table->integer('tax_rate_basis_points')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor')->default(0);
            $table->timestamps();

            $table->index(['purchase_order_id', 'product_id'], 'po_items_po_prod_idx');
        });

        // 4. Supplier Transactions Ledger (Accounts Payable / Khata)
        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('type', 30); // bill_received, payment_made, adjustment
            $table->bigInteger('amount_minor'); // Transaction amount
            $table->bigInteger('balance_after_minor'); // Running balance after this transaction
            $table->string('payment_method', 30)->nullable(); // cash, bank_transfer, cheque, raast
            $table->string('receipt_number', 50)->nullable(); // e.g. VEND-PAY-20261122-0001
            $table->string('reference_number', 50)->nullable(); // Cheque # or Bank Transaction ID
            $table->foreignUuid('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_id', 'created_at'], 'supplier_tx_tenant_supp_created_idx');
            $table->index(['tenant_id', 'receipt_number'], 'supplier_tx_receipt_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_transactions');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
