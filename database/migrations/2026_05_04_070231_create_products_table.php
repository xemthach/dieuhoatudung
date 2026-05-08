<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('model_code')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('series')->nullable();
            $table->integer('btu')->nullable();
            $table->boolean('inverter')->default(false);
            $table->string('cooling_type')->nullable(); // 1 chieu, 2 chieu
            $table->string('voltage')->nullable(); // 1 pha, 3 pha
            $table->string('refrigerant_gas')->nullable(); // R32, R410A
            $table->string('power_consumption')->nullable();
            $table->string('airflow')->nullable();
            $table->string('noise_level')->nullable();
            $table->string('indoor_dimensions')->nullable();
            $table->string('outdoor_dimensions')->nullable();
            $table->string('weight')->nullable();
            $table->string('recommended_area')->nullable();
            
            $table->decimal('regular_price', 15, 2)->nullable();
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->integer('discount_percent')->nullable();
            $table->timestamp('promotion_start_at')->nullable();
            $table->timestamp('promotion_end_at')->nullable();
            
            $table->string('stock_status')->default('in_stock');
            
            $table->text('short_description')->nullable();
            $table->longText('long_description')->nullable();
            $table->json('specs_json')->nullable();
            $table->text('warranty_info')->nullable();
            $table->text('installation_note')->nullable();
            
            $table->string('main_image')->nullable();
            $table->json('gallery_json')->nullable();
            $table->string('video_url')->nullable();
            $table->json('documents_json')->nullable();
            
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_bestseller')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->default('index,follow');
            $table->string('og_title')->nullable();
            $table->string('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->boolean('schema_enabled')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
