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
        Schema::create('r2_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->enum('mode', ['upload_only', 'replace_urls_only', 'upload_and_replace']);
            $table->enum('status', ['pending', 'scanning', 'syncing', 'replacing', 'completed', 'failed', 'cancelled']);
            $table->boolean('dry_run')->default(true);
            $table->integer('total_files')->default(0);
            $table->integer('synced_files')->default(0);
            $table->integer('skipped_files')->default(0);
            $table->integer('failed_files')->default(0);
            $table->integer('replaced_records')->default(0);
            $table->integer('replaced_occurrences')->default(0);
            $table->json('old_base_urls')->nullable();
            $table->string('new_base_url')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('r2_sync_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('r2_sync_job_id')->constrained()->cascadeOnDelete();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_field')->nullable();
            $table->string('local_path', 1000)->nullable();
            $table->string('old_url', 1000)->nullable();
            $table->string('new_url', 1000)->nullable();
            $table->string('r2_key', 1000)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->enum('status', ['pending', 'uploaded', 'skipped', 'replaced', 'failed']);
            $table->enum('action', ['upload', 'replace_url', 'upload_and_replace']);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('r2_sync_items');
        Schema::dropIfExists('r2_sync_jobs');
    }
};
