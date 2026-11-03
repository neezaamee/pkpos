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
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->index('tenant_id');

            $table->string('name', 100);
            $table->string('tax_category', 50);
            $table->enum('province', ['punjab', 'sindh', 'kp', 'balochistan', 'federal']);
            $table->enum('payment_method', ['cash', 'card', 'raast', 'wallet', 'any'])->default('any');
            $table->integer('rate_basis_points');
            $table->enum('authority', ['fbr', 'pra', 'srb', 'kpra', 'bra']);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tax_category');
            $table->index(
                ['tenant_id', 'tax_category', 'province', 'payment_method', 'effective_from'],
                'tax_rules_lookup_idx'
            );
        });

        // Partial unique index for active tax rules
        DB::statement('CREATE UNIQUE INDEX tax_rules_active_unique ON tax_rules (tenant_id, tax_category, province, payment_method, effective_from) WHERE is_active = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
