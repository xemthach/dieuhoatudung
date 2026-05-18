<?php

namespace App\Filament\Resources\SiteCampaigns\Pages;

use App\Filament\Resources\SiteCampaigns\SiteCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiteCampaigns extends ListRecords
{
    protected static string $resource = SiteCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
