<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\RoleResource;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()->label('Thêm role'),
        ];
    }
}
