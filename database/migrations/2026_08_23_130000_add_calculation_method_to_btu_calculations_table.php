<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('btu_calculations', 'calculation_method')) {
            Schema::table('btu_calculations', function (Blueprint $table): void {
                $table->string('calculation_method', 20)->default('area')->after('rule_version');
            });
        }

        // Every historical row predates Method B; the existing rule version and
        // application lineage therefore establish Method A as its source.
        DB::table('btu_calculations')
            ->where(fn ($query) => $query->whereNull('calculation_method')->orWhere('calculation_method', ''))
            ->update(['calculation_method' => 'area']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('btu_calculations', 'calculation_method')) {
            Schema::table('btu_calculations', function (Blueprint $table): void {
                $table->dropColumn('calculation_method');
            });
        }
    }
};
