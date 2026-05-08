<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $isProduction = app()->environment('production');

        // ── Ensure super_admin role exists ────────────────────────────
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web']
        );
        $staffRole = Role::firstOrCreate(
            ['name' => 'staff', 'guard_name' => 'web']
        );

        // ── Super Admin ──────────────────────────────────────────────
        $adminName     = env('ADMIN_NAME', 'Super Admin');
        $adminEmail    = env('ADMIN_EMAIL', 'admin@dieuhoa.vn');
        $adminPassword = env('ADMIN_PASSWORD');

        if (empty($adminPassword)) {
            if ($isProduction) {
                $this->command->error('!! ADMIN_PASSWORD is not set in .env !!');
                $this->command->error('   Set ADMIN_PASSWORD in .env before deploying to production.');
                Log::critical('AdminUserSeeder: ADMIN_PASSWORD not set in production environment.');
                // Use a random password — admin must reset via .env
                $adminPassword = \Illuminate\Support\Str::random(32);
                $this->command->warn("   A random password was generated. You MUST set ADMIN_PASSWORD in .env and re-seed.");
            } else {
                $adminPassword = 'ChangeMe!2024';
                $this->command->warn("Using fallback admin password 'ChangeMe!2024' — set ADMIN_PASSWORD in .env for production.");
            }
        }

        $superAdmin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name'              => $adminName,
                'password'          => Hash::make($adminPassword),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles([$superAdminRole]);

        $this->command->info("Admin user created: {$adminEmail}");

        // ── Sample Staff (optional, for dev/staging) ─────────────────
        $staffEmail    = env('STAFF_EMAIL');
        $staffPassword = env('STAFF_PASSWORD');

        if ($staffEmail && $staffPassword) {
            $staff = User::updateOrCreate(
                ['email' => $staffEmail],
                [
                    'name'              => env('STAFF_NAME', 'Nhan vien'),
                    'password'          => Hash::make($staffPassword),
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ]
            );
            $staff->syncRoles([$staffRole]);
            $this->command->info("Staff user created: {$staffEmail}");
        } elseif (!$isProduction) {
            // In local/dev, create a default staff user for testing
            $staff = User::updateOrCreate(
                ['email' => 'staff@dieuhoa.vn'],
                [
                    'name'              => 'Nhan vien',
                    'password'          => Hash::make('ChangeMe!2024'),
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ]
            );
            $staff->syncRoles([$staffRole]);
            $this->command->warn("Staff user created with fallback credentials: staff@dieuhoa.vn / ChangeMe!2024");
        }

        $this->command->info('Admin users seeded successfully.');
    }
}
