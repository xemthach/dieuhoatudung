<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_benefit_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('subtitle', 200)->nullable();
            $table->string('icon_type', 20)->default('heroicon'); // heroicon, lucide, image, svg
            $table->string('icon_name', 80)->nullable();
            $table->string('icon_image', 500)->nullable();
            $table->text('icon_svg')->nullable();
            $table->string('icon_color', 30)->default('text-primary-600');
            $table->string('bg_color', 30)->default('bg-primary-100');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_benefit_items');
    }
};
