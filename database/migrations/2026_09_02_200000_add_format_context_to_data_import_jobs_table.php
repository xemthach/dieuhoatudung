<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_import_jobs', function (Blueprint $table): void {
            $table->json('format_context_json')->nullable()->after('field_groups_json');
        });
    }

    public function down(): void
    {
        Schema::table('data_import_jobs', function (Blueprint $table): void {
            $table->dropColumn('format_context_json');
        });
    }
};
