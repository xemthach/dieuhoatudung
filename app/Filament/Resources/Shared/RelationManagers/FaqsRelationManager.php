<?php

namespace App\Filament\Resources\Shared\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Shared FaqsRelationManager — attach existing FAQs to any entity.
 * Register this in the getRelations() of each Resource.
 */
class FaqsRelationManager extends RelationManager
{
    protected static string $relationship = 'faqs';

    protected static ?string $title = 'Câu hỏi thường gặp (FAQ)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('question')
                ->label('Câu hỏi')
                ->required()
                ->columnSpanFull(),

            Textarea::make('answer')
                ->label('Trả lời')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            TextInput::make('group')
                ->label('Nhóm FAQ'),

            TextInput::make('sort_order')
                ->label('Thứ tự (trong FAQ chung)')
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label('Kích hoạt')
                ->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->reorderable('sort_order') // pivot sort_order
            ->columns([
                TextColumn::make('question')
                    ->label('Câu hỏi')
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('group')
                    ->label('Nhóm')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('pivot.sort_order')
                    ->label('Thứ tự')
                    ->sortable(),
            ])
            ->defaultSort('pivot_sort_order', 'asc')
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['question', 'group'])
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('sort_order')
                            ->label('Thứ tự trong trang này')
                            ->numeric()
                            ->default(0),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Sửa pivot')
                    ->form([
                        TextInput::make('sort_order')
                            ->label('Thứ tự trong trang này')
                            ->numeric()
                            ->default(0),
                    ]),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
