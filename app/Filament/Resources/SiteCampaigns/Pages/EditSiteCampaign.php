<?php

namespace App\Filament\Resources\SiteCampaigns\Pages;

use App\Filament\Resources\SiteCampaigns\SiteCampaignResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class EditSiteCampaign extends EditRecord
{
    protected static string $resource = SiteCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview_campaign')
                ->label('Xem trước campaign')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(fn (): string => 'Xem trước: '.$this->record->title)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Đóng')
                ->modalContent(fn (): HtmlString => new HtmlString(Blade::render(
                    '<x-site-campaigns :campaigns="$campaigns" :preview="true" />',
                    ['campaigns' => collect([$this->record])],
                ))),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
