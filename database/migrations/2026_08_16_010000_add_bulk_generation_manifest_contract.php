<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_product_jobs')) return;

        Schema::table('ai_product_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_product_jobs', 'batch_uuid')) $table->uuid('batch_uuid')->nullable()->unique();
            if (! Schema::hasColumn('ai_product_jobs', 'scope_type')) $table->string('scope_type', 32)->nullable();
            if (! Schema::hasColumn('ai_product_jobs', 'target_manifest_json')) $table->json('target_manifest_json')->nullable();
            if (! Schema::hasColumn('ai_product_jobs', 'target_manifest_hash')) $table->char('target_manifest_hash', 64)->nullable()->index();
            if (! Schema::hasColumn('ai_product_jobs', 'manifest_frozen_at')) $table->timestamp('manifest_frozen_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_product_jobs')) return;
        Schema::table('ai_product_jobs', function (Blueprint $table): void {
            foreach (['batch_uuid', 'scope_type', 'target_manifest_json', 'target_manifest_hash', 'manifest_frozen_at'] as $column) {
                if (Schema::hasColumn('ai_product_jobs', $column)) $table->dropColumn($column);
            }
        });
    }
};
