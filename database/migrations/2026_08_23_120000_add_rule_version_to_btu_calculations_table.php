<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('btu_calculations', 'rule_version')) {
            Schema::table('btu_calculations', function (Blueprint $table): void {
                $table->string('rule_version', 64)->nullable()->after('cooling_w_per_m2');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('btu_calculations', 'rule_version')) {
            Schema::table('btu_calculations', function (Blueprint $table): void {
                $table->dropColumn('rule_version');
            });
        }
    }
};
