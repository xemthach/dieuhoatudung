<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Super Admin ───────────────────────────────────────────────
        $superAdmin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@dieuhoa.vn')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe!2024')),
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('super_admin');

        // ── Sample Staff ──────────────────────────────────────────────
        $staff = User::firstOrCreate(
            ['email' => env('STAFF_EMAIL', 'staff@dieuhoa.vn')],
            [
                'name' => 'Nhân viên',
                'password' => Hash::make(env('STAFF_PASSWORD', 'ChangeMe!2024')),
                'is_active' => true,
            ]
        );
        $staff->assignRole('staff');

        $this->command->info('Admin users seeded successfully.');
        $this->command->info('Set ADMIN_EMAIL/ADMIN_PASSWORD and STAFF_EMAIL/STAFF_PASSWORD in .env');
    }
}
