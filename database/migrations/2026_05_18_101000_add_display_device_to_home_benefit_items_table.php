<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_benefit_items', function (Blueprint $table) {
            if (! Schema::hasColumn('home_benefit_items', 'display_device')) {
                $table->string('display_device', 20)->default('both')->after('bg_color');
                $table->index(['is_active', 'display_device', 'sort_order'], 'home_benefits_device_active_sort_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('home_benefit_items', function (Blueprint $table) {
            if (Schema::hasColumn('home_benefit_items', 'display_device')) {
                $table->dropIndex('home_benefits_device_active_sort_idx');
                $table->dropColumn('display_device');
            }
        });
    }
};
