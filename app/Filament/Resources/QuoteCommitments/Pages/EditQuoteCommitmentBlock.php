<?php

namespace App\Filament\Resources\QuoteCommitments\Pages;

use App\Filament\Resources\QuoteCommitments\QuoteCommitmentBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuoteCommitmentBlock extends EditRecord
{
    protected static string $resource = QuoteCommitmentBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
