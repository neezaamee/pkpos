<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Hardware, Building Materials & Sanitary Store Vertical.
     */
    public function up(): void
    {
        // 1. Contractor Projects & Construction Sites
        Schema::create('contractor_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('customer_id')->index(); // Contractor / Builder
            $table->string('name', 150); // e.g. "DHA Phase 6 Villa Plot 14", "Gulberg Commercial Plaza"
            $table->string('site_address', 255)->nullable();
            $table->string('plot_number', 50)->nullable();
            $table->string('city', 100)->nullable()->default('Lahore');
            $table->string('supervisor_name', 100)->nullable(); // Munshi / Site Engineer
            $table->string('supervisor_phone', 50)->nullable();
            $table->bigInteger('credit_limit_minor')->default(0);
            $table->string('status', 30)->default('active'); // active, completed, on_hold
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });

        // 2. Tiered Bulk / Wholesale Pricing Slabs
        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id')->index();
            $table->decimal('min_quantity', 12, 3)->default(1);
            $table->decimal('max_quantity', 12, 3)->nullable(); // null = and above
            $table->bigInteger('unit_price_minor'); // special slab rate
            $table->decimal('discount_percentage', 5, 2)->nullable()->default(0);
            $table->string('tier_label', 100)->nullable(); // e.g. "Wholesale 50+ Bags", "Truckload 600 Bags"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // 3. Delivery Challans & Gate Passes (Truck Dispatch / Bilty)
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('location_id')->index();
            $table->uuid('sale_id')->nullable()->index();
            $table->uuid('contractor_project_id')->nullable()->index();
            $table->string('challan_number', 50)->unique(); // CHL-YYMMDD-XXX
            $table->string('gate_pass_number', 50)->nullable();
            $table->string('vehicle_number', 50)->nullable(); // e.g. LES-4820 Mazda / Shahzore
            $table->string('vehicle_type', 50)->nullable(); // Mazda, Shahzore, Qingqi Loader, Dumper, Tractor
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 50)->nullable();
            $table->string('transporter_name', 100)->nullable();
            $table->bigInteger('freight_minor')->default(0);
            $table->string('freight_payment_type', 30)->default('to_pay_customer'); // paid_by_store, to_pay_customer
            $table->string('destination_site', 255)->nullable();
            $table->string('receiver_name', 100)->nullable();
            $table->string('receiver_phone', 50)->nullable();
            $table->string('status', 30)->default('pending'); // pending, dispatched, delivered, cancelled
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
            $table->foreign('contractor_project_id')->references('id')->on('contractor_projects')->onDelete('set null');
        });

        // 4. Delivery Challan Line Items
        Schema::create('delivery_challan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('delivery_challan_id')->index();
            $table->uuid('sale_item_id')->nullable()->index();
            $table->uuid('product_id')->nullable()->index();
            $table->string('product_name', 200);
            $table->decimal('ordered_quantity', 12, 3);
            $table->decimal('dispatched_quantity', 12, 3);
            $table->string('unit_code', 30)->default('pcs'); // bag, ton, cft, kg, feet, sqft
            $table->decimal('weight_kg', 12, 3)->nullable();
            $table->timestamps();

            $table->foreign('delivery_challan_id')->references('id')->on('delivery_challans')->onDelete('cascade');
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });

        // 5. Extend products with dimensional & weight attributes
        Schema::table('products', function (Blueprint $table) {
            $table->string('dimensional_unit', 30)->nullable()->after('unit'); // cft, bag, ton, kg, sqft, rft, length_ft, coil
            $table->decimal('weight_kg_per_unit', 10, 3)->nullable()->after('dimensional_unit'); // e.g. 50 for cement, weight per foot for saria
            $table->decimal('sqft_per_box', 10, 3)->nullable()->after('weight_kg_per_unit'); // for tiles
            $table->decimal('length_feet_per_piece', 10, 3)->nullable()->after('sqft_per_box'); // for pipes/rebar
            $table->boolean('requires_delivery_challan')->default(false)->after('length_feet_per_piece');
        });

        // 6. Extend sales with project attribution
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('contractor_project_id')->nullable()->after('customer_id');
            $table->string('project_site_name', 150)->nullable()->after('contractor_project_id');

            $table->foreign('contractor_project_id')->references('id')->on('contractor_projects')->onDelete('set null');
        });

        // 7. Extend sale_items with dimensional and project tracking
        Schema::table('sale_items', function (Blueprint $table) {
            $table->uuid('contractor_project_id')->nullable()->after('product_id');
            $table->decimal('dimensional_quantity', 12, 3)->nullable()->after('quantity');
            $table->string('dimensional_unit', 30)->nullable()->after('dimensional_quantity');
            $table->decimal('weight_kg', 12, 3)->nullable()->after('dimensional_unit');

            $table->foreign('contractor_project_id')->references('id')->on('contractor_projects')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['contractor_project_id']);
            $table->dropColumn(['contractor_project_id', 'dimensional_quantity', 'dimensional_unit', 'weight_kg']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['contractor_project_id']);
            $table->dropColumn(['contractor_project_id', 'project_site_name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'dimensional_unit',
                'weight_kg_per_unit',
                'sqft_per_box',
                'length_feet_per_piece',
                'requires_delivery_challan',
            ]);
        });

        Schema::dropIfExists('delivery_challan_items');
        Schema::dropIfExists('delivery_challans');
        Schema::dropIfExists('product_price_tiers');
        Schema::dropIfExists('contractor_projects');
    }
};
