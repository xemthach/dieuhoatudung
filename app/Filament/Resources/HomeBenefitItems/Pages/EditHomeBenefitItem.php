<?php

namespace App\Filament\Resources\HomeBenefitItems\Pages;

use App\Filament\Resources\HomeBenefitItems\HomeBenefitItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomeBenefitItem extends EditRecord
{
    protected static string $resource = HomeBenefitItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
