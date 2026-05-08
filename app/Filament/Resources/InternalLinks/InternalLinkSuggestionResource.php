<?php

namespace App\Filament\Resources\InternalLinks;

use App\Filament\Resources\InternalLinks\Pages\ListInternalLinkSuggestions;
use App\Models\InternalLinkSuggestion;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Traits\HasResourcePermissions;

class InternalLinkSuggestionResource extends Resource
{

    use HasResourcePermissions;
    protected static array $permissionMap = [
        'viewAny' => 'internal_link.view',
        'create'  => 'internal_link.create',
        'edit'    => 'internal_link.edit',
        'delete'  => 'internal_link.delete',
    ];

    protected static ?string $model = InternalLinkSuggestion::class;

    protected static ?string $navigationLabel = 'Internal Links';

    public static function getNavigationGroup(): ?string { return 'SEO'; }

    protected static ?int $navigationSort = 20;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-link'; }

    protected static ?string $recordTitleAttribute = 'anchor_text';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('anchor_text')->label('Anchor Text'),
            Textarea::make('reason')->label('Lý do gợi ý')->rows(3),
            Select::make('status')
                ->options([
                    'pending'  => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_type')
                    ->label('Nguồn')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->color('info'),

                TextColumn::make('source_id')
                    ->label('ID Nguồn')
                    ->sortable(),

                TextColumn::make('target_type')
                    ->label('Đích')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->color('success'),

                TextColumn::make('anchor_text')
                    ->label('Anchor Text')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('reason')
                    ->label('Lý do')
                    ->limit(60)
                    ->toggleable(),

                TextColumn::make('score')
                    ->label('Điểm')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default      => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    }),
            ])
            ->defaultSort('score', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                SelectFilter::make('source_type')
                    ->label('Loại nguồn')
                    ->options([
                        'App\\Models\\Post'    => 'Post',
                        'App\\Models\\Product' => 'Product',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->action(fn (InternalLinkSuggestion $record) => self::setStatus($record, 'approved'))
                    ->visible(fn (InternalLinkSuggestion $record) => $record->status !== 'approved'),

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->action(fn (InternalLinkSuggestion $record) => self::setStatus($record, 'rejected'))
                    ->visible(fn (InternalLinkSuggestion $record) => $record->status !== 'rejected'),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulk_approve')
                        ->label('Approve đã chọn')
                        ->icon(Heroicon::OutlinedCheck)
                        ->action(function ($records) {
                            $records->each(fn ($r) => $r->update(['status' => 'approved']));
                            Notification::make()->title('Đã approve ' . $records->count() . ' suggestion.')->success()->send();
                        }),

                    \Filament\Actions\BulkAction::make('bulk_reject')
                        ->label('Reject đã chọn')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each(fn ($r) => $r->update(['status' => 'rejected']));
                            Notification::make()->title('Đã reject ' . $records->count() . ' suggestion.')->success()->send();
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function setStatus(InternalLinkSuggestion $record, string $status): void
    {
        $record->update(['status' => $status]);
        Notification::make()
            ->title("Đã chuyển trạng thái thành {$status}.")
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInternalLinkSuggestions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderByDesc('score');
    }
}
