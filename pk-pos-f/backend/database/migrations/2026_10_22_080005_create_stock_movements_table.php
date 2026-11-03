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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('tenant_id');
            $table->index('product_id');
            $table->index('location_id');
            $table->index('created_by');

            $table->string('movement_type', 30); // sale, return, adjustment, restock, opening, in, out
            $table->integer('quantity_delta');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('note', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Composite indexes
            $table->index(['tenant_id', 'product_id', 'created_at'], 'stock_movements_history_idx');
            $table->index(['tenant_id', 'reference_type', 'reference_id'], 'stock_movements_ref_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
