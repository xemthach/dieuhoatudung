<?php

namespace App\Filament\Resources\AiContentJobs\Tables;

use App\Enums\AIContentJobStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AiContentJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('topic')
                    ->label('Chủ đề')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->topic),
                TextColumn::make('primary_keyword')
                    ->label('Từ khóa')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('postCategory.name')
                    ->label('Danh mục')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        AIContentJobStatus::Pending    => 'gray',
                        AIContentJobStatus::Processing => 'info',
                        AIContentJobStatus::Completed  => 'success',
                        AIContentJobStatus::Failed     => 'danger',
                        AIContentJobStatus::Reviewed   => 'primary',
                        default                        => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof AIContentJobStatus ? $state->label() : $state),
                TextColumn::make('creator.name')
                    ->label('Tạo bởi')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(AIContentJobStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
