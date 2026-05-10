<?php

namespace App\Filament\Resources\HomeBenefitItems\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables;
use Filament\Tables\Table;

class HomeBenefitItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subtitle')
                    ->label('Phụ đề')
                    ->limit(40),

                Tables\Columns\TextColumn::make('icon_type')
                    ->label('Icon')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'heroicon' => 'Heroicon',
                        'image'    => 'Image',
                        'svg'      => 'SVG',
                        default    => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'heroicon' => 'primary',
                        'image'    => 'success',
                        'svg'      => 'warning',
                        default    => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
