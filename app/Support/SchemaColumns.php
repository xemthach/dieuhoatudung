<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class SchemaColumns
{
    public static function existing(string $table, array $attributes): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return collect($attributes)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn($table, $column))
            ->all();
    }
}
