<?php

namespace App\Filament\Resources\AiProductJobs\Pages;

use App\Filament\Resources\AiProductJobs\AiProductJobResource;
use Filament\Resources\Pages\EditRecord;

class EditAiProductJob extends EditRecord
{
    protected static string $resource = AiProductJobResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
