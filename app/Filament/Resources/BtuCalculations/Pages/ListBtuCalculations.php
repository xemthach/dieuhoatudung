<?php

namespace App\Filament\Resources\BtuCalculations\Pages;

use App\Filament\Resources\BtuCalculations\BtuCalculationResource;
use App\Filament\Traits\HasDataTransferActions;
use Filament\Resources\Pages\ListRecords;

class ListBtuCalculations extends ListRecords
{
    use HasDataTransferActions;

    protected static string $resource = BtuCalculationResource::class;
    protected string $transferModule = 'btu_calculation';

    protected function getHeaderActions(): array
    {
        return [
            $this->getExportHeaderAction(),
            $this->getImportHeaderAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
