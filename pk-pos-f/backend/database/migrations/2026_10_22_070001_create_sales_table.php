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
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('location_id');
            $table->index('user_id');
            $table->string('sale_number');
            $table->enum('payment_method', ['cash', 'card', 'raast', 'wallet']);
            $table->string('currency')->default('PKR');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('total_minor');
            $table->enum('status', ['completed', 'refunded', 'voided']);
            $table->string('fbr_usin')->nullable();
            $table->enum('fbr_status', ['pending', 'submitted', 'failed', 'offline', 'not_applicable'])->default('pending');
            $table->text('fbr_error')->nullable();
            $table->timestamp('fbr_submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sale_number']);
            $table->index(['tenant_id', 'fbr_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
