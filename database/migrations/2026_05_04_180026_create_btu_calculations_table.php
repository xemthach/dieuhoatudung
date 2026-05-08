<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('btu_calculations', function (Blueprint $table) {
            $table->id();

            // Input fields
            $table->decimal('area_m2', 8, 2);
            $table->decimal('ceiling_height', 4, 2)->nullable()->default(3.0);
            $table->string('space_type', 50);          // van_phong, nha_hang, etc.
            $table->unsignedSmallInteger('people_count')->nullable();
            $table->boolean('direct_sunlight')->default(false);
            $table->boolean('heat_equipment')->default(false);
            $table->string('priority', 50)->nullable(); // tiet_kiem_dien, gia_tot, etc.

            // Calculated result
            $table->unsignedInteger('recommended_btu');
            $table->json('matched_product_ids')->nullable();

            // Contact info (optional — filled if user wants consultation)
            $table->string('full_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('note')->nullable();

            // Tracking
            $table->string('source_page', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            // Indexes for admin filters
            $table->index('recommended_btu');
            $table->index('space_type');
            $table->index('created_at');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('btu_calculations');
    }
};
