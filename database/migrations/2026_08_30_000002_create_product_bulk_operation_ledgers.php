<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bulk_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_uuid')->unique();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 48)->index();
            $table->string('selection_mode', 32);
            $table->unsignedInteger('selected_count');
            $table->char('product_ids_hash', 64)->index();
            $table->json('product_ids_json');
            $table->json('filters_json')->nullable();
            $table->string('status', 24)->default('PREFLIGHT');
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('summary_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_bulk_operation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_id')->constrained('product_bulk_operations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('draft_id')->nullable()->constrained('ai_product_drafts')->nullOnDelete();
            $table->string('before_state', 32);
            $table->string('after_state', 32)->nullable();
            $table->string('result', 16)->default('PENDING');
            $table->string('reason', 160)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->unique(['operation_id', 'product_id']);
            $table->index(['result', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bulk_operation_items');
        Schema::dropIfExists('product_bulk_operations');
    }
};
