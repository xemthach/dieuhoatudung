<?php

namespace App\Filament\Resources\DealerServices\Pages;

use App\Filament\Resources\DealerServices\DealerServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEntries extends ListRecords
{
    protected static string $resource = DealerServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
