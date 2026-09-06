<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_sources', function (Blueprint $table): void {
            $table->char('source_sha256', 64)->nullable()->after('uploaded_file');
            $table->index(['brand_id', 'source_sha256'], 'catalog_source_brand_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_sources', function (Blueprint $table): void {
            $table->dropIndex('catalog_source_brand_hash_idx');
            $table->dropColumn('source_sha256');
        });
    }
};
