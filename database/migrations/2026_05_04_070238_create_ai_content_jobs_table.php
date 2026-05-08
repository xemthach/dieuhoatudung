<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_content_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->string('primary_keyword')->nullable();
            $table->string('intent')->nullable();
            $table->foreignId('post_category_id')->nullable()->constrained()->nullOnDelete();
            
            $table->json('input_payload')->nullable();
            $table->longText('output_outline')->nullable();
            $table->longText('output_draft')->nullable();
            $table->json('output_faq')->nullable();
            $table->json('output_tags')->nullable();
            $table->json('output_meta')->nullable();
            $table->json('output_internal_links')->nullable();
            
            $table->string('status')->default('pending'); // Enum AIContentJobStatus
            $table->text('error_message')->nullable();
            
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_content_jobs');
    }
};
