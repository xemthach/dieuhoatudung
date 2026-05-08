<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policy_pages', function (Blueprint $table) {
            $table->json('display_locations')->nullable()->after('type');
            $table->integer('sort_order')->default(0)->after('display_locations');
        });

        // Set default display_locations for existing pages
        DB::table('policy_pages')->update(['display_locations' => json_encode(['footer'])]);
    }

    public function down(): void
    {
        Schema::table('policy_pages', function (Blueprint $table) {
            $table->dropColumn(['display_locations', 'sort_order']);
        });
    }
};
