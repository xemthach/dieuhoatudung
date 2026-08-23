<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_bulk_apply_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_bulk_apply_items', 'chunk_no')) $table->unsignedInteger('chunk_no')->nullable()->after('status');
        });
        Schema::table('ai_bulk_apply_snapshots', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_bulk_apply_snapshots', 'version_id')) $table->unsignedBigInteger('version_id')->nullable()->after('after_hash');
        });
    }

    public function down(): void
    {
        Schema::table('ai_bulk_apply_snapshots', function (Blueprint $table): void { if (Schema::hasColumn('ai_bulk_apply_snapshots', 'version_id')) $table->dropColumn('version_id'); });
        Schema::table('ai_bulk_apply_items', function (Blueprint $table): void { if (Schema::hasColumn('ai_bulk_apply_items', 'chunk_no')) $table->dropColumn('chunk_no'); });
    }
};
