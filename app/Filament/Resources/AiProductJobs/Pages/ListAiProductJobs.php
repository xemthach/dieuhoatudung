<?php

namespace App\Filament\Resources\AiProductJobs\Pages;

use App\Filament\Resources\AiProductJobs\AiProductJobResource;
use App\Filament\Widgets\AIRuntimePolicyWidget;
use Filament\Resources\Pages\ListRecords;

class ListAiProductJobs extends ListRecords
{
    protected static string $resource = AiProductJobResource::class;

    protected function getHeaderWidgets(): array
    {
        return [AIRuntimePolicyWidget::class];
    }
}
