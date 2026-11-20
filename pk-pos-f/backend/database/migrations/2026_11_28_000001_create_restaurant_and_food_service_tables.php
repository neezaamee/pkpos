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
        // 1. Restaurant Floors / Dining Zones
        Schema::create('restaurant_floors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('location_id')->index();
            $table->string('name', 100);
            $table->integer('floor_number')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });

        // 2. Kitchen Preparation Stations (e.g. BBQ, Tandoor, Main, Drinks)
        Schema::create('kitchen_stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('location_id')->index();
            $table->string('name', 100);
            $table->string('printer_ip_or_name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });

        // 3. Restaurant Tables
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('location_id')->index();
            $table->uuid('floor_id')->nullable()->index();
            $table->string('table_number', 50);
            $table->integer('capacity')->default(4); // covers
            $table->string('status', 30)->default('available'); // available, occupied, billed, reserved, cleaning
            $table->uuid('current_sale_id')->nullable()->index();
            $table->string('shape', 20)->default('square'); // square, round, rectangle
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('floor_id')->references('id')->on('restaurant_floors')->onDelete('set null');
        });

        // 4. Modifier Groups (e.g. Spice Level, Add-on Sauces, Sides)
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name', 100);
            $table->integer('min_selections')->default(0); // 0 = optional, 1 = required single
            $table->integer('max_selections')->default(1); // 1 = radio, >1 = checkboxes
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 5. Modifier Options (e.g. Mild, Medium, Extra Spicy, Extra Cheese)
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('modifier_group_id')->index();
            $table->string('name', 100);
            $table->bigInteger('price_override_minor')->default(0); // extra charge in paisas
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('modifier_group_id')->references('id')->on('modifier_groups')->onDelete('cascade');
        });

        // 6. Product <-> Modifier Group pivot
        Schema::create('product_modifier_group', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('modifier_group_id');
            $table->primary(['product_id', 'modifier_group_id']);

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('modifier_group_id')->references('id')->on('modifier_groups')->onDelete('cascade');
        });

        // 7. Add restaurant attributes to products
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('kitchen_station_id')->nullable()->after('tax_category');
            $table->integer('preparation_time_minutes')->default(15)->after('kitchen_station_id');
            $table->boolean('is_kitchen_item')->default(false)->after('preparation_time_minutes');

            $table->foreign('kitchen_station_id')->references('id')->on('kitchen_stations')->onDelete('set null');
        });

        // 8. Add restaurant dining attributes to sales
        Schema::table('sales', function (Blueprint $table) {
            $table->string('order_type', 30)->default('dine_in')->after('status'); // dine_in, takeaway, delivery
            $table->uuid('restaurant_table_id')->nullable()->after('order_type');
            $table->string('table_number', 50)->nullable()->after('restaurant_table_id');
            $table->uuid('server_user_id')->nullable()->after('table_number');
            $table->string('server_name', 100)->nullable()->after('server_user_id');
            $table->integer('covers')->default(1)->after('server_name');
            $table->integer('service_charge_basis_points')->default(0)->after('covers');
            $table->bigInteger('service_charge_minor')->default(0)->after('service_charge_basis_points');
            $table->string('delivery_address', 255)->nullable()->after('service_charge_minor');
            $table->string('delivery_rider_name', 100)->nullable()->after('delivery_address');

            $table->foreign('restaurant_table_id')->references('id')->on('restaurant_tables')->onDelete('set null');
            $table->foreign('server_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 9. Add modifiers and cooking notes to sale_items
        Schema::table('sale_items', function (Blueprint $table) {
            $table->jsonb('selected_modifiers')->nullable()->after('line_total_minor');
            $table->string('cooking_notes', 255)->nullable()->after('selected_modifiers');
            $table->uuid('kitchen_station_id')->nullable()->after('cooking_notes');

            $table->foreign('kitchen_station_id')->references('id')->on('kitchen_stations')->onDelete('set null');
        });

        // 10. Kitchen Order Tickets (KOT)
        Schema::create('kitchen_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('location_id')->index();
            $table->uuid('sale_id')->index();
            $table->uuid('kitchen_station_id')->nullable()->index();
            $table->string('kot_number', 50);
            $table->string('order_type', 30)->default('dine_in');
            $table->string('table_number', 50)->nullable();
            $table->string('server_name', 100)->nullable();
            $table->string('status', 30)->default('pending'); // pending, preparing, ready, served, cancelled
            $table->text('special_instructions')->nullable();
            $table->timestamp('fired_at')->useCurrent();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('kitchen_station_id')->references('id')->on('kitchen_stations')->onDelete('set null');
        });

        // 11. Kitchen Order Items (KOT line items)
        Schema::create('kitchen_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('kitchen_order_id')->index();
            $table->uuid('sale_item_id')->nullable()->index();
            $table->uuid('product_id')->nullable()->index();
            $table->string('product_name', 150);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->jsonb('selected_modifiers')->nullable();
            $table->string('cooking_notes', 255)->nullable();
            $table->string('status', 30)->default('pending'); // pending, preparing, ready, served, cancelled
            $table->timestamps();

            $table->foreign('kitchen_order_id')->references('id')->on('kitchen_orders')->onDelete('cascade');
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_order_items');
        Schema::dropIfExists('kitchen_orders');

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['kitchen_station_id']);
            $table->dropColumn(['selected_modifiers', 'cooking_notes', 'kitchen_station_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['restaurant_table_id']);
            $table->dropForeign(['server_user_id']);
            $table->dropColumn([
                'order_type',
                'restaurant_table_id',
                'table_number',
                'server_user_id',
                'server_name',
                'covers',
                'service_charge_basis_points',
                'service_charge_minor',
                'delivery_address',
                'delivery_rider_name',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['kitchen_station_id']);
            $table->dropColumn(['kitchen_station_id', 'preparation_time_minutes', 'is_kitchen_item']);
        });

        Schema::dropIfExists('product_modifier_group');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('kitchen_stations');
        Schema::dropIfExists('restaurant_floors');
    }
};
