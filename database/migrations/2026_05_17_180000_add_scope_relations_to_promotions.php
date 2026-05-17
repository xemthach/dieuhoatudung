<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('promotions', 'scope')) {
                $table->string('scope')->default('global')->after('description')->index();
            }
        });

        Schema::create('product_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'promotion_id']);
        });

        Schema::create('category_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_category_id', 'promotion_id']);
        });

        Schema::create('brand_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['brand_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_promotion');
        Schema::dropIfExists('category_promotion');
        Schema::dropIfExists('product_promotion');

        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumn('promotions', 'scope')) {
                $table->dropColumn('scope');
            }
        });
    }
};
