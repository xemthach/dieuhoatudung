<?php

namespace App\Filament\Resources\SiteCampaigns\Tables;

use App\Models\SiteCampaign;
use App\Services\Marketing\SiteCampaignReadinessService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SiteCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount([
                    'events as impressions_count' => fn ($q) => $q->where('event_type', 'impression'),
                    'events as clicks_count' => fn ($q) => $q->whereIn('event_type', ['click_primary', 'click_secondary']),
                ])
                ->withMax('events as last_rendered_at', 'created_at'))
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SiteCampaign::typeOptions()[$state] ?? (string) $state),
                TextColumn::make('placement')
                    ->label('Placement')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SiteCampaign::placementOptions()[$state] ?? (string) $state),
                TextColumn::make('device')
                    ->label('Device')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'desktop' => 'Desktop',
                        'mobile' => 'Mobile',
                        default => 'Both',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'archived' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('runtime_readiness')
                    ->label('Runtime')
                    ->badge()
                    ->state(fn (SiteCampaign $record): string => app(SiteCampaignReadinessService::class)->present($record)['label'])
                    ->color(fn (SiteCampaign $record): string => app(SiteCampaignReadinessService::class)->present($record)['color'])
                    ->tooltip(fn (SiteCampaign $record): string => app(SiteCampaignReadinessService::class)->present($record)['reason']),
                TextColumn::make('start_at')
                    ->label('Start')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_at')
                    ->label('End')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('impressions_count')
                    ->label('Impressions')
                    ->numeric(),
                TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->numeric(),
                TextColumn::make('ctr')
                    ->label('CTR')
                    ->suffix('%'),
                TextColumn::make('last_rendered_at')
                    ->label('Event gần nhất')
                    ->dateTime()
                    ->placeholder('Chưa có dữ liệu')
                    ->toggleable(),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'archived' => 'Archived',
                    ]),
                SelectFilter::make('type')
                    ->options(SiteCampaign::typeOptions()),
                SelectFilter::make('placement')
                    ->options(SiteCampaign::placementOptions()),
                TrashedFilter::make(),
            ])
            ->defaultSort('priority', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
