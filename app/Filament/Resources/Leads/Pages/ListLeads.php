<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Traits\HasDataTransferActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    use HasDataTransferActions;

    protected static string $resource = LeadResource::class;
    protected string $transferModule = 'lead';

    protected function getHeaderActions(): array
    {
        return [
            $this->getExportHeaderAction(),
            $this->getImportHeaderAction(),
            CreateAction::make(),
        ];
    }
}
