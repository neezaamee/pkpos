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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number', 50)->unique();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 255);
            $table->string('category', 50)->default('general'); // fbr_sync_failure, hardware_printer, tax_calculation, billing, general
            $table->string('priority', 50)->default('medium'); // critical_counter_down, high, medium, low
            $table->string('status', 30)->default('open'); // open, in_progress, resolved, closed
            $table->json('diagnostic_snapshot')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('priority');
            $table->index('category');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->longText('message');
            $table->boolean('is_internal_note')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
        });

        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category', 50)->default('general');
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->longText('content_markdown');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_articles');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};

