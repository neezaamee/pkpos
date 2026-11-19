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
        // 1. Units of Measure Master Table
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100); // e.g. Piece, Kilogram, Meter, Liter, Box, Carton
            $table->string('code', 20); // e.g. pcs, kg, mtr, ltr, box, ctn
            $table->boolean('allows_decimals')->default(false); // True for kg, mtr, ltr
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uom_tenant_code_unique');
            $table->index(['tenant_id', 'is_active']);
        });

        // 2. Product UOM Packaging Conversions (e.g. 1 Carton = 24 Pieces)
        Schema::create('product_uom_conversions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->string('unit_name', 50); // e.g. Carton of 24
            $table->string('unit_code', 20); // e.g. ctn
            $table->string('barcode', 50)->nullable(); // Specific pack barcode for instant scanning
            $table->decimal('conversion_factor', 12, 3)->default(1.000); // 1 Carton = 24.000 Base Units
            $table->bigInteger('price_minor'); // Pack price in minor units
            $table->bigInteger('cost_minor')->nullable();
            $table->boolean('is_default_purchase_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'unit_code'], 'prod_uom_tenant_prod_code_unique');
            $table->index(['tenant_id', 'product_id']);
        });

        // Barcode index on product_uom_conversions
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX prod_uom_tenant_barcode_unique ON product_uom_conversions (tenant_id, barcode) WHERE barcode IS NOT NULL');
        } else {
            Schema::table('product_uom_conversions', function (Blueprint $table) {
                $table->index(['tenant_id', 'barcode'], 'prod_uom_tenant_barcode_idx');
            });
        }

        // 3. Price Lists (Retail, Wholesale, Distributor)
        Schema::create('price_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100); // e.g. Wholesale Price List
            $table->string('code', 30); // e.g. wholesale
            $table->string('description', 255)->nullable();
            $table->string('currency', 10)->default('PKR');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'price_lists_tenant_code_unique');
            $table->index(['tenant_id', 'is_active']);
        });

        // 4. Price List Items & Tiered Volume Thresholds
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('min_quantity', 12, 3)->default(1.000); // Min quantity to qualify for this tier
            $table->bigInteger('price_minor'); // Custom unit price
            $table->decimal('discount_pct', 5, 2)->nullable(); // Optional percentage discount off retail
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id', 'min_quantity'], 'price_list_item_tier_unique');
            $table->index(['tenant_id', 'product_id']);
        });

        // 5. Add price_list_id to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignUuid('price_list_id')->nullable()->after('credit_limit_minor')->constrained('price_lists')->nullOnDelete();
        });

        // 6. Add UOM fields to sale_items table
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('unit_code', 20)->default('pcs')->after('hs_code');
            $table->decimal('unit_conversion_factor', 12, 3)->default(1.000)->after('unit_code');
            $table->string('discount_type', 20)->default('none')->after('line_total_minor');
            $table->bigInteger('discount_amount_minor')->default(0)->after('discount_type');
        });

        // 7. Update quantity columns to decimal(12, 3) across sale_items, stock_levels, and stock_movements
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sale_items ALTER COLUMN quantity TYPE numeric(12, 3) USING quantity::numeric(12, 3)');
            DB::statement('ALTER TABLE stock_levels ALTER COLUMN quantity_on_hand TYPE numeric(12, 3) USING quantity_on_hand::numeric(12, 3)');
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN quantity_delta TYPE numeric(12, 3) USING quantity_delta::numeric(12, 3)');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE sale_items MODIFY COLUMN quantity DECIMAL(12, 3) NOT NULL');
            DB::statement('ALTER TABLE stock_levels MODIFY COLUMN quantity_on_hand DECIMAL(12, 3) NOT NULL DEFAULT 0.000');
            DB::statement('ALTER TABLE stock_movements MODIFY COLUMN quantity_delta DECIMAL(12, 3) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['unit_code', 'unit_conversion_factor', 'discount_type', 'discount_amount_minor']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn('price_list_id');
        });

        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('product_uom_conversions');
        Schema::dropIfExists('units_of_measure');
    }
};
