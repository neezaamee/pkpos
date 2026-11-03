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
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();

            $table->index('tenant_id');
            $table->index('product_id');
            $table->index('location_id');

            $table->integer('quantity_on_hand')->default(0);
            $table->timestamp('updated_at')->useCurrent();

            // Composite unique constraint: 1 stock level per tenant + product + location
            $table->unique(['tenant_id', 'product_id', 'location_id'], 'stock_levels_tenant_prod_loc_unique');
            $table->index(['tenant_id', 'product_id'], 'stock_levels_tenant_prod_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
