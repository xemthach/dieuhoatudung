<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [Phase 1 — C-1] Thêm ip_address vào leads table.
     * BtuCalculatorController và QuoteController đều ghi ip_address nhưng
     * migration gốc 2026_05_04_070237 thiếu cột này, gây SQLSTATE 42S22.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Thêm sau cột status để nhất quán với quote_requests
            $table->string('ip_address', 45)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
