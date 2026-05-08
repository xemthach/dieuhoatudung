<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AppInstall extends Command
{
    protected $signature = 'app:install
                            {--with-demo : Also seed demo data (brands, products, posts)}
                            {--force : Force operations in production}';

    protected $description = 'First-time setup: migrate, seed base data, create admin user, link storage.';

    public function handle(): int
    {
        $this->info('');
        $this->info('====================================');
        $this->info('  Dieu Hoa Tu Dung — App Install');
        $this->info('====================================');
        $this->info('');

        $isProduction = app()->environment('production');
        $force = $this->option('force');

        // ── 1. Check .env ────────────────────────────────────────────
        if (!file_exists(base_path('.env'))) {
            $this->error('.env file not found! Copy .env.example to .env first.');
            return self::FAILURE;
        }
        $this->info('[1/9] .env file found.');

        // ── 2. Check APP_KEY ─────────────────────────────────────────
        if (empty(config('app.key'))) {
            $this->warn('APP_KEY is empty. Generating...');
            Artisan::call('key:generate', ['--force' => true]);
            $this->info('[2/9] APP_KEY generated.');
        } else {
            $this->info('[2/9] APP_KEY already set.');
        }

        // ── 3. Run migrations ────────────────────────────────────────
        $this->info('[3/9] Running migrations...');
        $migrateOptions = $isProduction || $force ? ['--force' => true] : [];
        Artisan::call('migrate', $migrateOptions, $this->output);

        // ── 4. Seed roles & permissions ──────────────────────────────
        $this->info('[4/9] Seeding roles & permissions...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\RolePermissionSeeder',
            '--force' => true,
        ], $this->output);

        // ── 5. Seed admin user ───────────────────────────────────────
        $this->info('[5/9] Creating admin user...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\AdminUserSeeder',
            '--force' => true,
        ], $this->output);

        // ── 6. Seed site settings ────────────────────────────────────
        $this->info('[6/9] Seeding site settings...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\SiteSettingSeeder',
            '--force' => true,
        ], $this->output);

        // ── 7. Seed mail templates ───────────────────────────────────
        $this->info('[7/9] Seeding mail templates...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\MailTemplateSeeder',
            '--force' => true,
        ], $this->output);

        // ── 8. Storage link ──────────────────────────────────────────
        $this->info('[8/9] Creating storage link...');
        try {
            Artisan::call('storage:link', [], $this->output);
        } catch (\Exception $e) {
            $this->warn('Storage link already exists or failed: ' . $e->getMessage());
        }

        // ── 9. Clear caches ──────────────────────────────────────────
        $this->info('[9/9] Clearing caches...');
        Artisan::call('optimize:clear', [], $this->output);

        // ── Optional: Demo data ──────────────────────────────────────
        if ($this->option('with-demo')) {
            $this->newLine();
            $this->info('Seeding demo data...');
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\DemoDataSeeder',
                '--force' => true,
            ], $this->output);
        }

        // ── Summary ──────────────────────────────────────────────────
        $this->newLine();
        $this->info('====================================');
        $this->info('  Installation complete!');
        $this->info('====================================');
        $this->newLine();
        $this->info('Admin login:');
        $this->info('  URL:      ' . config('app.url') . '/admin');
        $this->info('  Email:    ' . env('ADMIN_EMAIL', 'admin@dieuhoa.vn'));
        $this->info('  Password: (from ADMIN_PASSWORD in .env)');
        $this->newLine();

        if (!$this->option('with-demo')) {
            $this->info('To add demo data: php artisan app:install --with-demo');
        }

        return self::SUCCESS;
    }
}
