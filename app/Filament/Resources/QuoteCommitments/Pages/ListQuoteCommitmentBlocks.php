<?php

namespace App\Filament\Resources\QuoteCommitments\Pages;

use App\Filament\Resources\QuoteCommitments\QuoteCommitmentBlockResource;
use Filament\Resources\Pages\ListRecords;

class ListQuoteCommitmentBlocks extends ListRecords
{
    protected static string $resource = QuoteCommitmentBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
