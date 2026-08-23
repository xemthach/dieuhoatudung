<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bulk_runtime_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_uuid')->unique();
            $table->foreignId('ai_product_job_id')->nullable()->constrained('ai_product_jobs')->nullOnDelete();
            $table->string('status', 32)->default('QUEUED');
            $table->string('status_reason', 120)->nullable();
            $table->unsignedInteger('concurrency_limit')->default(1);
            $table->unsignedBigInteger('token_budget_total')->nullable();
            $table->unsignedBigInteger('token_reserved')->default(0);
            $table->unsignedBigInteger('token_consumed')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->timestamp('pause_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });

        Schema::create('ai_bulk_runtime_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('runtime_batch_id')->constrained('ai_bulk_runtime_batches')->cascadeOnDelete();
            $table->unsignedInteger('slot_no');
            $table->string('owner_worker', 120)->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('status', 20)->default('FREE');
            $table->timestamp('acquired_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamps();
            $table->unique(['runtime_batch_id', 'slot_no'], 'ai_bulk_runtime_slot_uq');
            $table->index(['runtime_batch_id', 'status', 'expires_at'], 'ai_bulk_runtime_slot_lookup');
        });

        Schema::create('ai_bulk_runtime_leases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('runtime_batch_id')->constrained('ai_bulk_runtime_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->string('worker_id', 120);
            $table->string('status', 20)->default('CLAIMED');
            $table->timestamp('claimed_at');
            $table->timestamp('expires_at');
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamps();
            $table->unique(['runtime_batch_id', 'item_id'], 'ai_bulk_runtime_lease_uq');
            $table->index(['status', 'expires_at'], 'ai_bulk_runtime_lease_lookup');
        });

        Schema::create('ai_bulk_field_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('runtime_batch_id')->constrained('ai_bulk_runtime_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('product_id');
            $table->string('field', 40);
            $table->string('status', 24)->default('QUEUED');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->unsignedInteger('tokens_reserved')->default(0);
            $table->unsignedInteger('tokens_consumed')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
            $table->unique(['runtime_batch_id', 'item_id', 'field'], 'ai_bulk_field_op_uq');
            $table->index(['status', 'next_retry_at'], 'ai_bulk_field_op_queue');
        });

        Schema::create('ai_bulk_apply_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('apply_batch_id')->constrained('ai_bulk_apply_batches')->cascadeOnDelete();
            $table->foreignId('apply_item_id')->constrained('ai_bulk_apply_items')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('chunk_no')->default(1);
            $table->json('before_payload_json');
            $table->char('before_hash', 64);
            $table->char('after_hash', 64)->nullable();
            $table->string('status', 24)->default('CAPTURED');
            $table->timestamps();
            $table->unique('apply_item_id', 'ai_bulk_snapshot_item_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bulk_apply_snapshots');
        Schema::dropIfExists('ai_bulk_field_operations');
        Schema::dropIfExists('ai_bulk_runtime_leases');
        Schema::dropIfExists('ai_bulk_runtime_slots');
        Schema::dropIfExists('ai_bulk_runtime_batches');
    }
};
