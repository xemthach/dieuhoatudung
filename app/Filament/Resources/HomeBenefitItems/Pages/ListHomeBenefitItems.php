<?php

namespace App\Filament\Resources\HomeBenefitItems\Pages;

use App\Filament\Resources\HomeBenefitItems\HomeBenefitItemResource;
use Filament\Resources\Pages\ListRecords;

class ListHomeBenefitItems extends ListRecords
{
    protected static string $resource = HomeBenefitItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
