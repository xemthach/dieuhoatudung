<?php

namespace App\Filament\Resources\PolicyPages\Pages;

use App\Filament\Resources\PolicyPages\PolicyPageResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\Width;

class EditPolicyPage extends EditRecord
{
    protected static string $resource = PolicyPageResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::SevenExtraLarge;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
