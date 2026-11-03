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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->index('tenant_id');

            $table->string('name', 150);
            $table->string('sku', 50)->nullable();
            $table->string('barcode', 50)->nullable();
            $table->string('hs_code', 8);
            $table->string('unit', 20)->default('piece');
            $table->bigInteger('price_minor');
            $table->bigInteger('cost_minor')->nullable();
            $table->string('tax_category', 50);
            $table->boolean('is_active')->default(true);
            $table->jsonb('product_meta')->nullable();
            $table->timestamps();

            // Explicit indexes
            $table->index('hs_code');
            $table->index('tax_category');
            $table->index(['tenant_id', 'tax_category']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Partial unique index: barcode unique per tenant when not null
        DB::statement('CREATE UNIQUE INDEX products_tenant_barcode_unique ON products (tenant_id, barcode) WHERE barcode IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
