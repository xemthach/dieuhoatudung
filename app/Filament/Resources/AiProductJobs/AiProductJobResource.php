<?php

namespace App\Filament\Resources\AiProductJobs;

use App\Filament\Resources\AiProductJobs\Pages\EditAiProductJob;
use App\Filament\Resources\AiProductJobs\Pages\ListAiProductJobs;
use App\Filament\Resources\AiProductJobs\RelationManagers\ItemsRelationManager;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\AiProductJob;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AiProductJobResource extends Resource
{
    use HasResourcePermissions;

    protected static array $permissionMap = [
        'viewAny' => 'product.ai_generate',
        'create' => 'product.ai_generate',
        'edit' => 'product.ai_generate',
        'delete' => 'product.ai_generate',
    ];

    protected static ?string $model = AiProductJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'AI Product Jobs';

    protected static ?int $navigationSort = 25;

    public static function getNavigationGroup(): ?string
    {
        return 'E-commerce';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('AI Product Job Report')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('type')->disabled(),
                        TextInput::make('scope')->disabled(),
                        TextInput::make('status')->disabled(),
                        TextInput::make('total')->disabled(),
                        TextInput::make('processed')->disabled(),
                        TextInput::make('success')->disabled(),
                        TextInput::make('failed')->disabled(),
                        TextInput::make('needs_review')->disabled(),
                    ]),
                    Textarea::make('config_json')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                        ->disabled()
                        ->columnSpanFull(),
                ]),
            Section::make('Technical debug')
                ->collapsed()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('module')->disabled(),
                        TextInput::make('provider')->disabled(),
                        TextInput::make('model')->disabled(),
                        TextInput::make('queue_name')->disabled(),
                        TextInput::make('attempts')->disabled(),
                        TextInput::make('retry_count')->disabled(),
                        TextInput::make('failed_reason')->disabled(),
                        TextInput::make('last_error_code')->disabled(),
                        TextInput::make('duration_ms')->disabled(),
                    ]),
                    Textarea::make('last_error_message')->rows(4)->disabled()->columnSpanFull(),
                    Textarea::make('stack_trace')->rows(8)->disabled()->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('type')->badge()->searchable(),
                TextColumn::make('scope')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('failed_reason')->badge()->color('danger')->placeholder('-')->toggleable(),
                TextColumn::make('queue_name')->label('Queue')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attempts')->numeric()->sortable()->toggleable(),
                TextColumn::make('total')->numeric()->sortable(),
                TextColumn::make('processed')->numeric()->sortable(),
                TextColumn::make('success')->numeric()->sortable()->color('success'),
                TextColumn::make('failed')->numeric()->sortable()->color('danger'),
                TextColumn::make('needs_review')->numeric()->sortable()->color('warning'),
                TextColumn::make('created_at')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('finished_at')->dateTime('d/m/Y H:i')->sortable()->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'queued' => 'Đang chờ',
                        'processing' => 'Đang xử lý',
                        'completed' => 'Hoàn thành',
                        'completed_with_errors' => 'Hoàn thành có lỗi',
                        'failed' => 'Thất bại',
                        'cancelled' => 'Đã hủy',
                        'stuck' => 'Bị kẹt',
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Report'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiProductJobs::route('/'),
            'edit' => EditAiProductJob::route('/{record}/edit'),
        ];
    }
}
