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
        Schema::table('users', function (Blueprint $table) {
            $table->string('cashier_pin_hash', 255)->nullable()->after('password');
            $table->jsonb('pos_permissions')->nullable()->after('cashier_pin_hash');
            $table->unsignedInteger('max_discount_pct')->default(10)->after('pos_permissions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cashier_pin_hash', 'pos_permissions', 'max_discount_pct']);
        });
    }
};
