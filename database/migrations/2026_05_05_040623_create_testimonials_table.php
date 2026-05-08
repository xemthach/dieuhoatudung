<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            
            $table->string('customer_name');
            $table->string('customer_title')->nullable();
            $table->string('company_name')->nullable();
            $table->string('location')->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('rating')->nullable()->comment('1 to 5 stars');
            
            // Optional relations to specific entities
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('case_study_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('avatar')->nullable();
            $table->string('image')->nullable();
            
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'is_featured', 'sort_order']);
            $table->index('product_id');
            $table->index('case_study_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
