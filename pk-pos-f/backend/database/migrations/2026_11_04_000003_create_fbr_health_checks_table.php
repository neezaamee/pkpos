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
        Schema::create('fbr_health_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('environment', 20)->default('sandbox'); // sandbox, production
            $table->string('endpoint_url');
            $table->integer('status_code')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->enum('status', ['operational', 'degraded', 'offline'])->default('operational');
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fbr_health_checks');
    }
};

