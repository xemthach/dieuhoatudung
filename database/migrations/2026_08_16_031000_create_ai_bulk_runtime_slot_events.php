<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bulk_runtime_slot_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('runtime_batch_id')->constrained('ai_bulk_runtime_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('slot_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('worker_id', 120)->nullable();
            $table->string('event', 20);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['runtime_batch_id', 'occurred_at'], 'ai_bulk_slot_event_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bulk_runtime_slot_events');
    }
};
