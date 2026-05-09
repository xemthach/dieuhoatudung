<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('capacity_kw', 8, 2)->nullable()->after('btu');
            $table->decimal('hp', 5, 1)->nullable()->after('capacity_kw');
        });

        // Normalize cooling_type: "1 chiều" → "1_chieu", "2 chiều" → "2_chieu"
        DB::table('products')->where('cooling_type', '1 chiều')->update(['cooling_type' => '1_chieu']);
        DB::table('products')->where('cooling_type', '2 chiều')->update(['cooling_type' => '2_chieu']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['capacity_kw', 'hp']);
        });

        DB::table('products')->where('cooling_type', '1_chieu')->update(['cooling_type' => '1 chiều']);
        DB::table('products')->where('cooling_type', '2_chieu')->update(['cooling_type' => '2 chiều']);
    }
};
