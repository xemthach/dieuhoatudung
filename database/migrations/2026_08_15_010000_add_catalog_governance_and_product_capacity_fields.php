<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_sources', function (Blueprint $table): void {
            $table->string('section_type', 40)->default('unknown')->after('source_type');
            $table->string('authority', 40)->nullable()->after('section_type');
            $table->string('source_status', 40)->default('unverified')->after('authority');
            $table->index(['section_type', 'authority', 'source_status'], 'catalog_source_governance_idx');
        });

        Schema::table('catalog_models', function (Blueprint $table): void {
            $table->string('section_type', 40)->default('unknown')->after('normalized_sku');
            $table->string('verification_status', 40)->default('unverified')->after('import_status');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->index(['section_type', 'verification_status'], 'catalog_model_verification_idx');
        });

        Schema::table('catalog_model_fields', function (Blueprint $table): void {
            $table->string('source_section', 40)->default('unknown')->after('source_page');
            $table->string('source_table_title')->nullable()->after('source_section');
            $table->string('source_row_label')->nullable()->after('source_table_title');
            $table->string('source_column_model')->nullable()->after('source_row_label');
            $table->string('extraction_method', 40)->nullable()->after('source_column_model');
            $table->string('verification_status', 40)->default('unverified')->after('extraction_method');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->index(['source_section', 'verification_status', 'field_key'], 'catalog_field_provenance_idx');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('marketing_capacity_btu')->nullable()->after('btu');
            $table->unsignedInteger('technical_capacity_btu')->nullable()->after('marketing_capacity_btu');
            $table->string('technical_capacity_status', 40)->default('unverified')->after('technical_capacity_btu');
            $table->index(['technical_capacity_status', 'technical_capacity_btu'], 'product_capacity_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('product_capacity_status_idx');
            $table->dropColumn(['marketing_capacity_btu', 'technical_capacity_btu', 'technical_capacity_status']);
        });

        Schema::table('catalog_model_fields', function (Blueprint $table): void {
            $table->dropIndex('catalog_field_provenance_idx');
            $table->dropColumn([
                'source_section', 'source_table_title', 'source_row_label', 'source_column_model',
                'extraction_method', 'verification_status', 'verified_at',
            ]);
        });

        Schema::table('catalog_models', function (Blueprint $table): void {
            $table->dropIndex('catalog_model_verification_idx');
            $table->dropColumn(['section_type', 'verification_status', 'verified_at']);
        });

        Schema::table('catalog_sources', function (Blueprint $table): void {
            $table->dropIndex('catalog_source_governance_idx');
            $table->dropColumn(['section_type', 'authority', 'source_status']);
        });
    }
};
