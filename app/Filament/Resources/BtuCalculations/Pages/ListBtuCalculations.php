<?php

namespace App\Filament\Resources\BtuCalculations\Pages;

use App\Filament\Resources\BtuCalculations\BtuCalculationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListBtuCalculations extends ListRecords
{
    protected static string $resource = BtuCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return []; // No manual create
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
