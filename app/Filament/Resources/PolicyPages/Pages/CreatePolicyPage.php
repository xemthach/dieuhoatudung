<?php

namespace App\Filament\Resources\PolicyPages\Pages;

use App\Filament\Resources\PolicyPages\PolicyPageResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePolicyPage extends CreateRecord
{
    protected static string $resource = PolicyPageResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::SevenExtraLarge;
    }
}
