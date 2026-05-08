<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Traits\HasDataTransferActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    use HasDataTransferActions;

    protected static string $resource = ProductResource::class;
    protected string $transferModule = 'product';

    protected function getHeaderActions(): array
    {
        return [
            $this->getExportHeaderAction(),
            $this->getImportHeaderAction(),
            CreateAction::make(),
        ];
    }
}
