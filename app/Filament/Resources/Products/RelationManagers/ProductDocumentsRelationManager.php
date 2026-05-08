<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
class ProductDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Tài liệu kỹ thuật';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\FileUpload::make('file_path')
                    ->label('File tài liệu')
                    ->required()
                    
                    ->directory('products/documents')
                    ->acceptedFileTypes(fn () => app(\App\Services\Settings\UploadSettingService::class)->allowedFileTypes())
                    ->maxSize(fn () => app(\App\Services\Settings\UploadSettingService::class)->documentMaxSizeKb())
                    ->preserveFilenames()
                    ->downloadable()
                    ->storeFileNamesIn('file_name')
                    ->afterStateUpdated(function ($set, ?TemporaryUploadedFile $state) {
                        if ($state) {
                            $set('file_size', $state->getSize());
                            $set('mime_type', $state->getMimeType());
                        }
                    })
                    ->columnSpanFull(),
                    
                Forms\Components\TextInput::make('title')
                    ->label('Tiêu đề tài liệu')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\Select::make('document_type')
                    ->label('Loại tài liệu')
                    ->options([
                        'catalogue' => 'Catalogue / Brochure',
                        'manual' => 'Hướng dẫn sử dụng',
                        'specs' => 'Bảng thông số kỹ thuật',
                        'installation' => 'Sơ đồ / Hướng dẫn lắp đặt',
                        'warranty' => 'Phiếu bảo hành',
                        'other' => 'Khác',
                    ])
                    ->required()
                    ->default('catalogue'),
                    
                Forms\Components\TextInput::make('sort_order')
                    ->label('Thứ tự hiển thị')
                    ->numeric()
                    ->default(0)
                    ->required(),
                    
                Forms\Components\Toggle::make('is_public')
                    ->label('Hiển thị công khai (trên web)')
                    ->default(true),
                    
                // Hidden fields for file metadata
                Forms\Components\Hidden::make('file_size'),
                Forms\Components\Hidden::make('mime_type'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Loại')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'catalogue' => 'Catalogue',
                        'manual' => 'HDSD',
                        'specs' => 'Thông số KT',
                        'installation' => 'HD Lắp đặt',
                        'warranty' => 'Bảo hành',
                        default => 'Khác',
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'catalogue' => 'info',
                        'manual' => 'success',
                        'specs' => 'warning',
                        'installation' => 'danger',
                        'warranty' => 'primary',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->file_name),
                    
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Kích thước')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : '-'),
                    
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Loại tài liệu')
                    ->options([
                        'catalogue' => 'Catalogue',
                        'manual' => 'Hướng dẫn sử dụng',
                        'specs' => 'Thông số kỹ thuật',
                        'installation' => 'Sơ đồ lắp đặt',
                        'warranty' => 'Phiếu bảo hành',
                        'other' => 'Khác',
                    ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Tải về')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => Storage::disk(config('media.disk', 'public'))->url($record->file_path))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
