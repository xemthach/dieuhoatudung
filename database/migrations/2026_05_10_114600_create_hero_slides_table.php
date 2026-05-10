<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();

            // Content
            $table->string('title', 255)->nullable();
            $table->string('highlight_text', 255)->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('text_color', 20)->default('#ffffff');
            $table->string('text_align', 10)->default('center'); // left, center, right
            $table->string('content_position', 10)->default('center'); // left, center, right

            // Background
            $table->string('background_type', 20)->default('gradient'); // color, gradient, image, video, embed
            $table->string('background_color', 20)->nullable();
            $table->string('gradient_from', 20)->nullable();
            $table->string('gradient_to', 20)->nullable();
            $table->string('background_image', 500)->nullable();
            $table->string('background_video', 500)->nullable();
            $table->string('embed_url', 500)->nullable();

            // Overlay
            $table->boolean('overlay_enabled')->default(true);
            $table->string('overlay_color', 20)->default('#000000');
            $table->unsignedTinyInteger('overlay_opacity')->default(20); // 0-100

            // CTA
            $table->string('cta_primary_text', 100)->nullable();
            $table->string('cta_primary_url', 500)->nullable();
            $table->string('cta_primary_style', 30)->default('accent'); // accent, primary, outline
            $table->string('cta_secondary_text', 100)->nullable();
            $table->string('cta_secondary_url', 500)->nullable();
            $table->string('cta_secondary_style', 30)->default('outline');
            $table->boolean('open_in_new_tab')->default(false);

            // Animation & Transition
            $table->string('animation_type', 30)->default('fade'); // fade, slide-up, slide-left, zoom-in, none
            $table->unsignedInteger('duration_ms')->default(6000);

            // Status
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
