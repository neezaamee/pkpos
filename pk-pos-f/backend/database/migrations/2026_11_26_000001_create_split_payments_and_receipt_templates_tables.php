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
        // 1. Sale Payments (Multi-tender / Split Payments)
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();

            $table->string('payment_method', 50); // 'cash', 'card', 'raast', 'wallet', 'credit', 'cheque'
            $table->bigInteger('amount_minor');
            $table->bigInteger('tendered_minor')->nullable();
            $table->bigInteger('change_minor')->default(0);
            $table->string('reference', 100)->nullable(); // Authorization code, transaction reference, cheque #
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'payment_method']);
            $table->index('created_at');
        });

        // 2. Receipt Templates (Branding, Layout, Policies & Thermal Width)
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete()->unique();

            $table->string('business_name', 150)->nullable();
            $table->string('branch_name', 150)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 100)->nullable();

            $table->string('ntn_label', 30)->default('NTN:');
            $table->string('strn_label', 30)->default('STRN:');
            $table->string('pos_id_label', 30)->default('FBR POS ID:');
            $table->string('paper_width', 20)->default('80mm'); // '80mm' or '58mm'

            $table->boolean('show_tax_breakdown')->default(true);
            $table->boolean('show_customer_info')->default(true);
            $table->boolean('show_fbr_qr')->default(true);

            $table->text('header_note')->nullable(); // e.g. "Wholesale & Retail Supermarket"
            $table->text('footer_policy')->nullable(); // e.g. "Goods once sold can only be exchanged within 3 days with original receipt."
            $table->text('footer_greeting')->nullable(); // e.g. "Thank you for shopping with us! JazakAllah Khair."
            $table->string('logo_url', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_templates');
        Schema::dropIfExists('sale_payments');
    }
};
