<?php

namespace App\Filament\Traits;

/**
 * Adds Spatie Permission-based access control to Filament Resources.
 *
 * Usage: Add `use HasResourcePermissions;` to your Resource class
 * and define `protected static array $permissionMap` with keys:
 *   'viewAny', 'create', 'edit', 'delete'
 *
 * Example:
 *   protected static array $permissionMap = [
 *       'viewAny' => 'product.view',
 *       'create'  => 'product.create',
 *       'edit'    => 'product.edit',
 *       'delete'  => 'product.delete',
 *   ];
 */
trait HasResourcePermissions
{
    public static function canViewAny(): bool
    {
        $perm = static::$permissionMap['viewAny'] ?? null;
        if (!$perm) return true;
        return auth()->user()?->can($perm) ?? false;
    }

    public static function canCreate(): bool
    {
        $perm = static::$permissionMap['create'] ?? null;
        if (!$perm) return static::canViewAny();
        return auth()->user()?->can($perm) ?? false;
    }

    public static function canEdit($record): bool
    {
        $perm = static::$permissionMap['edit'] ?? null;
        if (!$perm) return static::canViewAny();
        return auth()->user()?->can($perm) ?? false;
    }

    public static function canDelete($record): bool
    {
        $perm = static::$permissionMap['delete'] ?? null;
        if (!$perm) return false;
        return auth()->user()?->can($perm) ?? false;
    }
}
