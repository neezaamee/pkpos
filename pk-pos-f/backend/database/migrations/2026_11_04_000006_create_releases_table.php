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
        Schema::create('releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('version', 30);
            $table->string('release_channel', 20)->default('stable'); // 'stable', 'beta', 'nightly'
            $table->string('target_platform', 40)->default('desktop_windows'); // 'desktop_windows', 'desktop_linux', 'desktop_macos', 'cloud_backend'
            $table->string('tauri_target', 50)->default('windows-x86_64'); // e.g. windows-x86_64, darwin-x86_64
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->longText('changelog_markdown')->nullable();
            $table->text('download_url')->nullable();
            $table->text('signature')->nullable(); // Tauri updater minisign/ed25519 signature
            $table->string('sha256_checksum', 64)->nullable();
            $table->string('min_supported_version', 30)->nullable();
            $table->boolean('is_critical')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['target_platform', 'release_channel', 'is_published']);
            $table->index('version');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};

