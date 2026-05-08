<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Spatie\Permission\Models\Permission;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => !in_array($this->record->name, ['super_admin', 'admin'])),
        ];
    }

    /**
     * Hydrate: load role's current permission IDs into each perm_* field.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $registry = config('permissions', []);
        $allPerms = Permission::where('guard_name', 'web')
            ->pluck('name', 'id')
            ->toArray();

        // Get this role's permission IDs
        $rolePermIds = $this->record->permissions->pluck('id')->toArray();

        foreach ($registry as $module => $config) {
            $fieldName = "perm_{$module}";
            $modulePermIds = [];

            foreach ($config['permissions'] as $action => $label) {
                $permName = "{$module}.{$action}";
                $id = array_search($permName, $allPerms);
                if ($id !== false && in_array($id, $rolePermIds)) {
                    $modulePermIds[] = $id;
                }
            }

            $data[$fieldName] = $modulePermIds;
        }

        return $data;
    }

    /**
     * Save: merge all perm_* fields and sync to role permissions.
     */
    protected function afterSave(): void
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
