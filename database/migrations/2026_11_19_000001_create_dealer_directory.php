<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_units', function (Blueprint $table) {
            $table->id();
            $table->string('scheme', 20);
            $table->string('dataset_version', 30);
            $table->string('code', 20);
            $table->string('kind', 20);
            $table->foreignId('parent_id')->nullable()->constrained('administrative_units')->restrictOnDelete();
            $table->string('name');
            $table->string('full_name');
            $table->string('slug');
            $table->text('introduction')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_indexable')->default(false);
            $table->timestamps();
            $table->unique(['scheme', 'dataset_version', 'code']);
            $table->unique(['scheme', 'dataset_version', 'kind', 'slug'], 'admin_units_version_slug_unique');
            $table->index(['scheme', 'kind', 'parent_id']);
        });
        Schema::create('dealer_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            foreach (['phone', 'hotline', 'email', 'website', 'logo', 'cover_image', 'google_maps_url', 'openstreetmap_url'] as $field) {
                $table->string($field, 1000)->nullable();
            }
            $table->string('address_line');
            $table->string('legacy_address_line')->nullable();
            foreach (['province_id', 'ward_id', 'legacy_province_id', 'legacy_district_id', 'legacy_ward_id'] as $field) {
                $table->foreignId($field)->nullable()->constrained('administrative_units')->restrictOnDelete();
            }
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            foreach (['is_active', 'is_featured', 'is_verified'] as $field) {
                $table->boolean($field)->default(false);
            }
            $table->boolean('review_enabled')->default(true);
            $table->boolean('reviews_visible')->default(true);
            $table->boolean('is_indexable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'province_id']);
            $table->index(['is_active', 'legacy_province_id', 'legacy_district_id']);
        });
        foreach (['brand' => 'brands', 'product_category' => 'product_categories', 'dealer_service' => 'dealer_services'] as $name => $related) {
            Schema::create('dealer_'.$name, function (Blueprint $table) use ($name, $related) {
                $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
                $table->foreignId($name.'_id')->constrained($related)->cascadeOnDelete();
                $table->primary(['dealer_id', $name.'_id']);
            });
        }
        Schema::create('dealer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('status', 20)->default('pending');
            $table->boolean('is_visible')->default(true);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['dealer_id', 'status', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_reviews');
        Schema::dropIfExists('dealer_brand');
        Schema::dropIfExists('dealer_product_category');
        Schema::dropIfExists('dealer_dealer_service');
        Schema::dropIfExists('dealers');
        Schema::dropIfExists('dealer_services');
        // SQLite enforces the self-reference while dropping a populated master.
        // The module is being removed; detach its own parent links before DROP.
        if (Schema::hasTable('administrative_units')) {
            DB::table('administrative_units')->whereNotNull('parent_id')->update(['parent_id' => null]);
        }
        Schema::dropIfExists('administrative_units');
    }
};
