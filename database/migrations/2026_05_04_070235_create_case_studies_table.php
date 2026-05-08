<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_studies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('client_name')->nullable();
            $table->string('location')->nullable();
            $table->string('area')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            
            $table->text('problem')->nullable();
            $table->longText('solution')->nullable();
            $table->longText('result')->nullable();
            
            $table->string('cover_image')->nullable();
            $table->json('gallery_json')->nullable();
            
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->default('index,follow');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_studies');
    }
};
