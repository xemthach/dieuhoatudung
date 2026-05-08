<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Resources\Pages\EditRecord;

class EditRedirect extends EditRecord
{
    protected static string $resource = RedirectResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Normalize source_url to path only
        $data['source_url'] = '/' . ltrim(parse_url($data['source_url'], PHP_URL_PATH) ?? $data['source_url'], '/');
        return $data;
    }
}
