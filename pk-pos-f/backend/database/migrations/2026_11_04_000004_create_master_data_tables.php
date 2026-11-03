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
        // 1. Pakistan Customs / FBR HS Codes Catalog
        Schema::create('master_hs_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('hs_code', 20)->unique();
            $table->text('description');
            $table->string('tax_category', 50)->default('general_goods'); // general_goods, tier_1_retail, reduced_goods, services, exempt
            $table->integer('default_rate_basis_points')->default(1800); // 1800 = 18.00%
            $table->string('authority', 20)->default('fbr'); // fbr, pra, srb, kpra, bra
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tax_category', 'is_active']);
            $table->index('authority');
        });

        // 2. Provincial & Federal Tax Templates
        Schema::create('provincial_tax_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('province', 20); // federal, punjab, sindh, kp, balochistan
            $table->string('name', 150);
            $table->string('authority', 20)->default('fbr'); // fbr, pra, srb, kpra, bra
            $table->string('tax_category', 50)->default('general_goods');
            $table->string('payment_method', 20)->default('any'); // any, cash, card, digital
            $table->integer('rate_basis_points')->default(1800); // 1800 = 18.00%
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['province', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provincial_tax_templates');
        Schema::dropIfExists('master_hs_codes');
    }
};

