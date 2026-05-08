<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'scanned' to r2_sync_items.status ENUM
        DB::statement("ALTER TABLE r2_sync_items MODIFY COLUMN `status` ENUM('pending','scanned','uploaded','skipped','replaced','failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE r2_sync_items MODIFY COLUMN `status` ENUM('pending','uploaded','skipped','replaced','failed') NOT NULL DEFAULT 'pending'");
    }
};
