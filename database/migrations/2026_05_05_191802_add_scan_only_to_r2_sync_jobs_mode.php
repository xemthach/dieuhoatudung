<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE r2_sync_jobs MODIFY COLUMN `mode` ENUM('scan_only','upload_only','replace_urls_only','upload_and_replace') NOT NULL DEFAULT 'upload_only'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE r2_sync_jobs MODIFY COLUMN `mode` ENUM('upload_only','replace_urls_only','upload_and_replace') NOT NULL DEFAULT 'upload_only'");
    }
};
