<?php

namespace App\Filament\Resources\ProductCategories\Tables;

use App\Models\ProductCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('parent.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('technical_schema_status')
                    ->label('Schema')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'missing')
                    ->color(fn (?string $state): string => match ($state ?: 'missing') {
                        'active' => 'success',
                        'locked' => 'warning',
                        'draft' => 'info',
                        'missing' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (ProductCategory $record): string => $record->technicalSchemaSummary()),
                TextColumn::make('technical_schema_version')
                    ->label('Schema v')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('technical_schema_json')
                    ->label('Schema summary')
                    ->formatStateUsing(fn ($state, ProductCategory $record): string => $record->technicalSchemaSummary())
                    ->toggleable(),
                ImageColumn::make('image'),
                TextColumn::make('seo_title')
                    ->searchable(),
                TextColumn::make('seo_description')
                    ->searchable(),
                TextColumn::make('canonical_url')
                    ->searchable(),
                TextColumn::make('robots')
                    ->searchable(),
                IconColumn::make('is_indexable')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
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
                SelectFilter::make('technical_schema_status')
                    ->label('Schema status')
                    ->options([
                        'active' => 'active',
                        'locked' => 'locked',
                        'draft' => 'draft',
                        'missing' => 'missing',
                    ]),
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
