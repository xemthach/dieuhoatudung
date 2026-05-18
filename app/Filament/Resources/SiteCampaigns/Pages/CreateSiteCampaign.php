<?php

namespace App\Filament\Resources\SiteCampaigns\Pages;

use App\Filament\Resources\SiteCampaigns\SiteCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSiteCampaign extends CreateRecord
{
    protected static string $resource = SiteCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
