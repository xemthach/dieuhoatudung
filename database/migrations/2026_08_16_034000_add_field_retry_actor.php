<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ai_bulk_field_operations') && ! Schema::hasColumn('ai_bulk_field_operations', 'actor_id')) {
            Schema::table('ai_bulk_field_operations', fn (Blueprint $table) => $table->unsignedBigInteger('actor_id')->nullable()->after('latency_ms'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_bulk_field_operations') && Schema::hasColumn('ai_bulk_field_operations', 'actor_id')) {
            Schema::table('ai_bulk_field_operations', fn (Blueprint $table) => $table->dropColumn('actor_id'));
        }
    }
};
