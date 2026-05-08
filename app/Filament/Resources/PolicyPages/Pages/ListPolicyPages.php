<?php

namespace App\Filament\Resources\PolicyPages\Pages;

use App\Filament\Resources\PolicyPages\PolicyPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPolicyPages extends ListRecords
{
    protected static string $resource = PolicyPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
