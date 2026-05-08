<?php

namespace App\Filament\Resources\InternalLinks\Pages;

use App\Filament\Resources\InternalLinks\InternalLinkSuggestionResource;
use Filament\Resources\Pages\ListRecords;

class ListInternalLinkSuggestions extends ListRecords
{
    protected static string $resource = InternalLinkSuggestionResource::class;
}
