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
        Schema::create('platform_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('filename');
            $table->string('backup_type')->default('manual_superadmin'); // automated_daily, manual_superadmin, pre_migration
            $table->string('status')->default('completed'); // in_progress, completed, failed
            $table->bigInteger('file_size_bytes')->default(0);
            $table->string('sha256_checksum')->nullable();
            $table->string('disk')->default('local');
            $table->string('storage_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'backup_type']);
        });

        Schema::create('fiscal_retention_archives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('archive_code')->unique();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('financial_year'); // e.g. 2025-2026
            $table->integer('record_count')->default(0);
            $table->bigInteger('file_size_bytes')->default(0);
            $table->string('sha256_checksum')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('status')->default('ready'); // generating, ready, failed
            $table->date('statutory_retention_until');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'financial_year']);
            $table->index('statutory_retention_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_retention_archives');
        Schema::dropIfExists('platform_backups');
    }
};

