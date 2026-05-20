<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->nullable()->constrained('ai_product_jobs')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('module', 80)->default('ai_product');
            $table->json('raw_output_json')->nullable();
            $table->json('normalized_output_json')->nullable();
            $table->json('field_status_json')->nullable();
            $table->json('validation_errors_json')->nullable();
            $table->json('warnings_json')->nullable();
            $table->json('used_verified_facts_json')->nullable();
            $table->json('token_usage_json')->nullable();
            $table->string('status', 40)->default('draft');
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['job_id', 'product_id']);
        });

        Schema::table('ai_product_job_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_product_job_items', 'field_status_json')) {
                $table->json('field_status_json')->nullable()->after('generated_payload_json');
            }
            if (! Schema::hasColumn('ai_product_job_items', 'token_usage_json')) {
                $table->json('token_usage_json')->nullable()->after('tokens_used');
            }
            if (! Schema::hasColumn('ai_product_job_items', 'draft_id')) {
                $table->foreignId('draft_id')->nullable()->after('token_usage_json')->constrained('ai_product_drafts')->nullOnDelete();
            }
            if (! Schema::hasColumn('ai_product_job_items', 'error_count')) {
                $table->unsignedInteger('error_count')->default(0)->after('field_status_json');
            }
            if (! Schema::hasColumn('ai_product_job_items', 'warning_count')) {
                $table->unsignedInteger('warning_count')->default(0)->after('error_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_product_job_items', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_product_job_items', 'draft_id')) {
                $table->dropConstrainedForeignId('draft_id');
            }

            foreach (['warning_count', 'error_count', 'token_usage_json', 'field_status_json'] as $column) {
                if (Schema::hasColumn('ai_product_job_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('ai_product_drafts');
    }
};
