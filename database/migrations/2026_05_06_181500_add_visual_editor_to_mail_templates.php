<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->longText('content_html')->nullable()->after('body_html')
                ->comment('Visual editor content (main body only, without wrapper)');
            $table->boolean('use_visual_editor')->default(true)->after('locale')
                ->comment('true = render content_html inside base layout; false = use raw body_html');
        });

        // Backfill: extract body content from existing body_html for visual editor
        // For existing templates, set use_visual_editor=false (keep raw mode) so nothing breaks
        \DB::table('mail_templates')->update(['use_visual_editor' => false]);
    }

    public function down(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->dropColumn(['content_html', 'use_visual_editor']);
        });
    }
};
