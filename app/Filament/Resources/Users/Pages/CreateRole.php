<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Spatie\Permission\Models\Permission;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /**
     * After creating the role, sync selected permissions from perm_* fields.
     */
    protected function afterCreate(): void
    {
        $registry = config('permissions', []);
        $allPermIds = [];

        foreach ($registry as $module => $config) {
            $fieldName = "perm_{$module}";
            $selected = $this->data[$fieldName] ?? [];
            if (is_array($selected)) {
                $allPermIds = array_merge($allPermIds, array_map('intval', $selected));
            }
        }

        // Sync permissions by ID
        $permNames = Permission::whereIn('id', $allPermIds)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        $this->record->syncPermissions($permNames);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
