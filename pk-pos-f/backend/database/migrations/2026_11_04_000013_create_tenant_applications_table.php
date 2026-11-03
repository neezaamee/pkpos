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
        Schema::create('tenant_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('application_number')->unique();
            $table->string('business_name');
            $table->string('trade_name')->nullable();
            $table->string('business_type')->default('retail');
            $table->string('sector')->default('general_retail'); // supermarket, pharmacy, apparel, restaurant, electronics, general_retail
            $table->string('province')->default('punjab'); // punjab, sindh, kpk, balochistan, islamabad
            $table->string('city');
            $table->text('address')->nullable();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('ntn')->nullable();
            $table->string('strn')->nullable();
            $table->boolean('is_atl_active')->default(false);
            $table->timestamp('atl_checked_at')->nullable();
            $table->string('atl_taxpayer_name')->nullable();
            $table->integer('pos_terminal_count')->default(1);
            $table->foreignUuid('requested_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('document_url')->nullable();
            $table->string('status')->default('pending_review'); // pending_review, under_investigation, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('provisioned_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'province']);
            $table->index('contact_email');
            $table->index('ntn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_applications');
    }
};

