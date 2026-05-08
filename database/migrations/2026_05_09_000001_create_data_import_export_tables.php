<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);           // product, lead, quote_request, btu_calculation
            $table->string('file_name');
            $table->string('file_path');             // private storage path
            $table->string('file_type', 10);         // xlsx, csv, xml, json
            $table->string('mode', 20)->default('create'); // create, update, upsert
            $table->string('matching_key', 50)->nullable(); // sku, slug, id, phone, etc.
            $table->string('status', 20)->default('pending'); // pending, validating, previewing, importing, completed, failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->json('error_report_json')->nullable();
            $table->json('preview_data_json')->nullable();
            $table->json('column_mapping_json')->nullable();
            $table->json('field_groups_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('module');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('data_export_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('file_type', 10);         // xlsx, csv, xml, json
            $table->json('field_groups_json')->nullable();
            $table->json('filters_json')->nullable();
            $table->json('selected_ids_json')->nullable();
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('module');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_export_jobs');
        Schema::dropIfExists('data_import_jobs');
    }
};
