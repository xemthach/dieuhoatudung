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

        // Add 'scanned' to r2_sync_items.status ENUM
        DB::statement("ALTER TABLE r2_sync_items MODIFY COLUMN `status` ENUM('pending','scanned','uploaded','skipped','replaced','failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE r2_sync_items MODIFY COLUMN `status` ENUM('pending','uploaded','skipped','replaced','failed') NOT NULL DEFAULT 'pending'");
    }
};
