<?php

namespace App\Filament\Resources\QuoteRequests\Pages;

use App\Filament\Resources\QuoteRequests\QuoteRequestResource;
use App\Filament\Traits\HasDataTransferActions;
use Filament\Resources\Pages\ListRecords;

class ListQuoteRequests extends ListRecords
{
    use HasDataTransferActions;

    protected static string $resource = QuoteRequestResource::class;
    protected string $transferModule = 'quote_request';

    protected function getHeaderActions(): array
    {
        return [
            $this->getExportHeaderAction(),
            $this->getImportHeaderAction(),
        ];
    }
}
