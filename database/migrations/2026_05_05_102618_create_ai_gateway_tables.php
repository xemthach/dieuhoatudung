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
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['gemini', 'openai', 'claude', 'groq', 'ollama', 'custom']);
            $table->string('name')->nullable();
            $table->text('api_key')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('model');
            $table->enum('priority', ['primary', 'fallback'])->default('primary');
            $table->integer('weight')->default(1);
            $table->enum('status', ['active', 'inactive', 'rate_limited', 'failed'])->default('active');
            $table->integer('error_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('request_count')->default(0);
            $table->bigInteger('tokens_used')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('rate_limited_until')->nullable();
            $table->integer('daily_limit')->nullable();
            $table->integer('daily_used')->default(0);
            $table->integer('minute_limit')->nullable();
            $table->integer('minute_used')->default(0);
            $table->boolean('supports_streaming')->default(false);
            $table->boolean('supports_json_mode')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_generation_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('context_id')->unique();
            $table->string('task_type')->nullable();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('input_hash')->nullable();
            $table->enum('status', ['active', 'completed', 'failed'])->default('active');
            $table->timestamps();
            
            $table->foreign('provider_id')->references('id')->on('ai_providers')->nullOnDelete();
        });

        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_provider_id')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->string('task_type')->nullable();
            $table->string('context_id')->nullable();
            $table->enum('status', ['success', 'failed', 'rate_limited', 'fallback']);
            $table->integer('latency_ms')->nullable();
            $table->integer('tokens_input')->nullable();
            $table->integer('tokens_output')->nullable();
            $table->integer('tokens_total')->nullable();
            $table->text('error_message')->nullable();
            $table->string('prompt_hash')->nullable();
            $table->timestamps();
            
            $table->foreign('ai_provider_id')->references('id')->on('ai_providers')->nullOnDelete();
            // index for fast lookup
            $table->index('context_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
        Schema::dropIfExists('ai_generation_sessions');
        Schema::dropIfExists('ai_providers');
    }
};
