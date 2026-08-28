<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Models\Promotion;
use App\Services\Marketing\PromotionDisplayResolver;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('discount_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('scope')
                    ->label('Phạm vi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'product' => 'Sản phẩm',
                        'category' => 'Danh mục',
                        'brand' => 'Thương hiệu',
                        default => 'Toàn site',
                    }),
                TextColumn::make('placement')
                    ->label('Placement')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PromotionDisplayResolver::PLACEMENTS[$state] ?? 'Không cấu hình'),
                TextColumn::make('discount_readiness')
                    ->label('Giảm giá')
                    ->badge()
                    ->state(fn (Promotion $record): string => $record->is_active && filled($record->discount_value) ? 'Đã cấu hình' : 'Không hoạt động')
                    ->color(fn (Promotion $record): string => $record->is_active && filled($record->discount_value) ? 'success' : 'gray'),
                TextColumn::make('display_readiness')
                    ->label('Hiển thị')
                    ->badge()
                    ->state(function (Promotion $record): string {
                        if (! app(PromotionDisplayResolver::class)->isRenderable($record)) return 'Không thể render';
                        if (! $record->is_active) return 'Đã tắt';
                        if ($record->start_at?->isFuture()) return 'Đã lên lịch';
                        if ($record->end_at?->isPast()) return 'Đã hết hạn';

                        return 'Có thể hiển thị';
                    })
                    ->color(function (Promotion $record): string {
                        if (! app(PromotionDisplayResolver::class)->isRenderable($record)) return 'danger';
                        if ($record->start_at?->isFuture()) return 'info';
                        if (! $record->is_active || $record->end_at?->isPast()) return 'gray';

                        return 'success';
                    }),
                TextColumn::make('discount_value')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('start_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
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
