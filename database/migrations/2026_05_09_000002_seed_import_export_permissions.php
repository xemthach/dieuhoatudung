<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'product.import',
            'product.export',
            'lead.import',
            'lead.export',
            'quote_request.import',
            'quote_request.export',
            'btu_calculation.import',
            'btu_calculation.export',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm, 'guard_name' => 'web']
            );
        }

        // Assign all import/export permissions to super_admin role
        $superAdmin = \Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        $permissions = [
            'product.import', 'product.export',
            'lead.import', 'lead.export',
            'quote_request.import', 'quote_request.export',
            'btu_calculation.import', 'btu_calculation.export',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};
