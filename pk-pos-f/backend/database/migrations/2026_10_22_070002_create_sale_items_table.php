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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->index('sale_id');
            $table->string('product_name');
            $table->string('hs_code');
            $table->integer('quantity');
            $table->bigInteger('unit_price_minor');
            $table->integer('tax_rate_basis_points');
            $table->bigInteger('tax_amount_minor');
            $table->bigInteger('line_total_minor');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
