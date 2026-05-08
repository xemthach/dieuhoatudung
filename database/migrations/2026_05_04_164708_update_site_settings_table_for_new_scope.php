<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('type');
            $table->boolean('is_public')->default(false)->after('is_encrypted');
            $table->text('description')->nullable()->after('is_public');
            $table->integer('sort_order')->default(0)->after('description');

            $table->dropUnique(['key']);
            $table->unique(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropUnique(['group', 'key']);
            $table->unique('key');
            
            $table->dropColumn(['is_encrypted', 'is_public', 'description', 'sort_order']);
        });
    }
};
