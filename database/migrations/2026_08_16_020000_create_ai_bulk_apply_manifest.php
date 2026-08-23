<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bulk_apply_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('apply_batch_uuid')->unique();
            $table->uuid('generation_batch_uuid')->nullable()->index();
            $table->json('manifest_json');
            $table->char('manifest_hash', 64)->index();
            $table->string('status', 32)->default('READY');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('ai_bulk_apply_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_bulk_apply_batches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draft_id')->constrained('ai_product_drafts')->cascadeOnDelete();
            $table->json('approved_fields');
            $table->char('payload_hash', 64);
            $table->char('technical_context_hash', 64);
            $table->char('before_product_hash', 64);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 32)->default('READY');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bulk_apply_items');
        Schema::dropIfExists('ai_bulk_apply_batches');
    }
};
