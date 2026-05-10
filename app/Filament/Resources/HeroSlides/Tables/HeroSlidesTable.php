<?php

namespace App\Filament\Resources\HeroSlides\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables;
use Filament\Tables\Table;

class HeroSlidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                Tables\Columns\ImageColumn::make('background_image')
                    ->label('Preview')
                    ->disk(fn ($record) => $record->background_image ? app(\App\Services\Media\MediaDiskService::class)->getUploadDisk() : 'public')
                    ->width(80)
                    ->height(45)
                    ->defaultImageUrl(fn () => asset('images/placeholders/product-default.jpg')),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('background_type')
                    ->label('Loại nền')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'gradient' => 'Gradient',
                        'color'    => 'Màu đơn',
                        'image'    => 'Hình ảnh',
                        'video'    => 'Video',
                        'embed'    => 'Embed',
                        default    => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'gradient' => 'primary',
                        'image'    => 'success',
                        'video'    => 'warning',
                        'embed'    => 'info',
                        default    => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái'),
            ])
            ->actions([
                EditAction::make(),
                ReplicateAction::make()
                    ->excludeAttributes(['sort_order'])
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['title'] = ($data['title'] ?? '') . ' (Copy)';
                        $data['is_active'] = false;
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

