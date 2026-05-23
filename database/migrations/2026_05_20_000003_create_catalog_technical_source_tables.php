<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('source_name');
            $table->string('source_type', 30);
            $table->string('version')->nullable();
            $table->string('uploaded_file')->nullable();
            $table->string('parsed_status', 40)->default('pending');
            $table->string('imported_status', 40)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['brand_id', 'category_id']);
            $table->index(['source_type', 'parsed_status', 'imported_status']);
        });

        Schema::create('catalog_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_source_id')->constrained()->cascadeOnDelete();
            $table->string('model')->nullable();
            $table->string('sku')->nullable();
            $table->string('normalized_model')->nullable();
            $table->string('normalized_sku')->nullable();
            $table->json('technical_data_json')->nullable();
            $table->unsignedInteger('source_page')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('import_status', 40)->default('parsed');
            $table->timestamps();

            $table->index(['catalog_source_id', 'normalized_model']);
            $table->index(['catalog_source_id', 'normalized_sku']);
            $table->index('import_status');
        });

        Schema::create('catalog_model_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_model_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('field_label')->nullable();
            $table->text('field_value')->nullable();
            $table->text('normalized_value')->nullable();
            $table->string('unit', 30)->nullable();
            $table->text('source_text')->nullable();
            $table->unsignedInteger('source_page')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['catalog_model_id', 'field_key']);
            $table->index('unit');
        });

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'catalog_source_id')) {
                $table->foreignId('catalog_source_id')->nullable()->after('product_category_id')->constrained('catalog_sources')->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'catalog_model_id')) {
                $table->foreignId('catalog_model_id')->nullable()->after('catalog_source_id')->constrained('catalog_models')->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'catalog_match_status')) {
                $table->string('catalog_match_status', 40)->nullable()->after('catalog_model_id');
            }
            if (! Schema::hasColumn('products', 'technical_specs_source')) {
                $table->string('technical_specs_source', 40)->nullable()->after('catalog_match_status');
            }
            if (! Schema::hasColumn('products', 'technical_specs_override_reason')) {
                $table->text('technical_specs_override_reason')->nullable()->after('technical_specs_source');
            }
            if (! Schema::hasColumn('products', 'technical_specs_overridden_at')) {
                $table->timestamp('technical_specs_overridden_at')->nullable()->after('technical_specs_override_reason');
            }

            $table->index(['catalog_source_id', 'catalog_model_id']);
            $table->index('catalog_match_status');
        });

        Schema::create('product_catalog_audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('completed');
            $table->json('summary_json')->nullable();
            $table->json('filters_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_catalog_audit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_run_id')->nullable()->constrained('product_catalog_audit_runs')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('validation_status', 60);
            $table->string('field_key')->nullable();
            $table->text('product_value')->nullable();
            $table->text('catalog_value')->nullable();
            $table->string('product_unit', 30)->nullable();
            $table->string('catalog_unit', 30)->nullable();
            $table->unsignedInteger('source_page')->nullable();
            $table->string('risk_level', 20)->default('low');
            $table->json('details_json')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'validation_status']);
            $table->index(['catalog_model_id', 'field_key']);
        });

        Schema::create('product_specs_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_run_id')->nullable()->constrained('product_catalog_audit_runs')->nullOnDelete();
            $table->json('snapshot_json');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specs_snapshots');
        Schema::dropIfExists('product_catalog_audit_items');
        Schema::dropIfExists('product_catalog_audit_runs');

        Schema::table('products', function (Blueprint $table): void {
            foreach (['catalog_source_id', 'catalog_model_id'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'catalog_match_status',
                'technical_specs_source',
                'technical_specs_override_reason',
                'technical_specs_overridden_at',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('catalog_model_fields');
        Schema::dropIfExists('catalog_models');
        Schema::dropIfExists('catalog_sources');
    }
};
