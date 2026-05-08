<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Delegate to the artisan command which reads config/permissions.php
        $this->command->call('permissions:sync', [
            '--apply' => true,
            '--reset-default-roles' => true,
        ]);

        $this->command->info('Roles & Permissions seeded via permissions:sync command.');
    }
}
