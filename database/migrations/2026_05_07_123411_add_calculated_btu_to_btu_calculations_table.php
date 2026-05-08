<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('btu_calculations', function (Blueprint $table) {
            if (! Schema::hasColumn('btu_calculations', 'calculated_btu')) {
                $table->unsignedInteger('calculated_btu')->nullable()->after('recommended_btu');
            }
            if (! Schema::hasColumn('btu_calculations', 'cooling_w_per_m2')) {
                $table->unsignedSmallInteger('cooling_w_per_m2')->nullable()->after('calculated_btu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('btu_calculations', function (Blueprint $table) {
            $table->dropColumn(['calculated_btu', 'cooling_w_per_m2']);
        });
    }
};
