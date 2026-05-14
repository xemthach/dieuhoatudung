<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('faqables', 'sort_order')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Must drop FK constraint, then composite PK, then re-add all cleanly
        DB::statement('ALTER TABLE `faqables`
            DROP FOREIGN KEY `faqables_faq_id_foreign`
        ');

        DB::statement('ALTER TABLE `faqables` DROP PRIMARY KEY');

        DB::statement('ALTER TABLE `faqables`
            ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
            ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `faqable_id`,
            ADD COLUMN `created_at` TIMESTAMP NULL AFTER `sort_order`,
            ADD COLUMN `updated_at` TIMESTAMP NULL AFTER `created_at`,
            ADD UNIQUE KEY `faqables_unique_pivot` (`faq_id`, `faqable_id`, `faqable_type`),
            ADD CONSTRAINT `faqables_faq_id_foreign`
                FOREIGN KEY (`faq_id`) REFERENCES `faqs` (`id`) ON DELETE CASCADE
        ');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('faqables', function (Blueprint $table) {
                $table->dropUnique('faqables_unique_pivot');
                $table->dropColumn(['id', 'sort_order', 'created_at', 'updated_at']);
            });

            return;
        }

        DB::statement('ALTER TABLE `faqables`
            DROP FOREIGN KEY `faqables_faq_id_foreign`,
            DROP KEY `faqables_unique_pivot`,
            DROP COLUMN `id`,
            DROP COLUMN `sort_order`,
            DROP COLUMN `created_at`,
            DROP COLUMN `updated_at`,
            ADD PRIMARY KEY (`faq_id`, `faqable_id`, `faqable_type`),
            ADD CONSTRAINT `faqables_faq_id_foreign`
                FOREIGN KEY (`faq_id`) REFERENCES `faqs` (`id`) ON DELETE CASCADE
        ');
    }
};
