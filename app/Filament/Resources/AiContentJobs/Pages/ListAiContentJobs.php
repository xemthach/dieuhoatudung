<?php

namespace App\Filament\Resources\AiContentJobs\Pages;

use App\Filament\Resources\AiContentJobs\AiContentJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiContentJobs extends ListRecords
{
    protected static string $resource = AiContentJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
