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
        // 1. Sales Returns & Credit Notes Table
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('register_session_id')->nullable()->constrained('register_sessions')->nullOnDelete();
            $table->string('return_number', 50); // e.g. RET-20261124-0001
            $table->string('reason', 50)->default('customer_change_of_mind'); // defective, wrong_item, customer_change_of_mind, expired, other
            $table->string('refund_method', 30)->default('cash'); // cash, credit_note_khata, original_payment
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_refund_minor')->default(0);
            $table->string('fbr_status', 30)->default('pending'); // pending, submitted, failed, exempt
            $table->string('fbr_credit_note_number', 100)->nullable(); // Statutory FBR Credit Note USIN
            $table->text('fbr_credit_note_qr')->nullable();
            $table->jsonb('fbr_response')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'return_number'], 'sale_returns_tenant_return_num_unique');
            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'fbr_status']);
        });

        // 2. Sales Return Items Table
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignUuid('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('unit_code', 20)->default('pcs');
            $table->decimal('unit_conversion_factor', 12, 3)->default(1.000);
            $table->decimal('quantity_returned', 12, 3);
            $table->bigInteger('unit_price_minor');
            $table->integer('tax_rate_basis_points')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('refund_amount_minor')->default(0);
            $table->string('restock_condition', 30)->default('restock_shelf'); // restock_shelf, damaged_quarantine, discard
            $table->timestamps();

            $table->index(['sale_return_id', 'sale_item_id'], 'return_items_return_item_idx');
            $table->index(['product_id'], 'return_items_product_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
