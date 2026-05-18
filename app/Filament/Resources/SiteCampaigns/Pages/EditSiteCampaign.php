<?php

namespace App\Filament\Resources\SiteCampaigns\Pages;

use App\Filament\Resources\SiteCampaigns\SiteCampaignResource;
use Filament\Resources\Pages\EditRecord;

class EditSiteCampaign extends EditRecord
{
    protected static string $resource = SiteCampaignResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
