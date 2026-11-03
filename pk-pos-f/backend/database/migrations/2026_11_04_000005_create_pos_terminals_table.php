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
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('terminal_code', 50);
            $table->string('name', 150);
            $table->string('hardware_uuid', 100)->nullable();
            $table->string('hostname', 100)->nullable();
            $table->string('platform_os', 50)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('pairing_token', 64)->nullable()->unique();
            $table->timestamp('pairing_token_expires_at')->nullable();
            $table->string('pairing_status', 30)->default('paired');
            $table->boolean('is_active')->default(true);
            $table->text('revocation_reason')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('location_id');
            $table->index('hardware_uuid');
            $table->index('pairing_status');
            $table->unique(['tenant_id', 'terminal_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_terminals');
    }
};

