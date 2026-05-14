<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('scope', 40);
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('success')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('needs_review')->default(0);
            $table->json('config_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('created_at');
        });

        Schema::create('ai_product_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_product_job_id')->constrained('ai_product_jobs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('queued');
            $table->unsignedTinyInteger('seo_score_before')->nullable();
            $table->unsignedTinyInteger('seo_score_after')->nullable();
            $table->json('warnings_json')->nullable();
            $table->text('error_message')->nullable();
            $table->json('generated_payload_json')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_product_job_id', 'product_id']);
            $table->index(['status', 'product_id']);
        });

        Schema::create('ai_product_content_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->longText('old_excerpt')->nullable();
            $table->longText('old_content')->nullable();
            $table->json('old_seo_json')->nullable();
            $table->json('old_merchant_json')->nullable();
            $table->json('old_tags_json')->nullable();
            $table->json('old_faq_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_product_content_versions');
        Schema::dropIfExists('ai_product_job_items');
        Schema::dropIfExists('ai_product_jobs');
    }
};
