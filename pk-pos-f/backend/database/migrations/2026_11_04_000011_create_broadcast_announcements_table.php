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
        Schema::create('broadcast_announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('message');
            $table->string('level')->default('info'); // info, warning, critical_emergency
            $table->string('target_audience')->default('all'); // all, province, desktop_terminals_only, tenant
            $table->string('target_province')->nullable(); // punjab, sindh, kpk, balochistan, islamabad
            $table->foreignUuid('target_tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('dismissible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'level']);
            $table->index(['target_audience', 'target_province']);
            $table->index(['starts_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_announcements');
    }
};

