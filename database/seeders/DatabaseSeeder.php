<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production base seeders always run first (roles, admin, settings, mail templates).
     * Demo data only runs in local/testing or via --class=DemoDataSeeder.
     */
    public function run(): void
    {
        // ── 1. Production-safe base seeders (always run) ──────────────
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SiteSettingSeeder::class,
            MailTemplateSeeder::class,
        ]);

        // ── 2. Demo data (only in local/testing environment) ──────────
        if (app()->environment('local', 'testing')) {
            $this->command->warn('Local/testing environment detected — seeding demo data...');
            $this->call(DemoDataSeeder::class);
        } else {
            $this->command->info('Production environment — skipping demo data. Run manually: php artisan db:seed --class=DemoDataSeeder');
        }
    }
}
