<?php

namespace App\Filament\Resources\AiProductJobs;

use App\Filament\Resources\AiProductJobs\Pages\EditAiProductJob;
use App\Filament\Resources\AiProductJobs\Pages\ListAiProductJobs;
use App\Filament\Resources\AiProductJobs\RelationManagers\ItemsRelationManager;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\AiProductJob;
use App\Models\AiBulkRuntimeBatch;
use App\Services\AI\BulkRuntimeObservabilityService;
use App\Services\AI\BulkRuntimeAuthorizationService;
use App\Services\AI\AIRuntimePolicyService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;

class AiProductJobResource extends Resource
{
    use HasResourcePermissions;

    protected static array $permissionMap = [
        'viewAny' => 'bulk_ai_view',
        'create' => 'product.ai_generate',
        'edit' => 'product.ai_generate',
        'delete' => 'product.ai_generate',
    ];

    protected static ?string $model = AiProductJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'Công việc nội dung AI';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'AI Content';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $actor = auth()->user();
        if (! $actor || ! $actor->can('bulk_ai_view')) return $query->whereRaw('1 = 0');
        $ids = app(BulkRuntimeAuthorizationService::class)->visibleJobIds($actor);
        $query = $ids === null ? $query : $query->whereIn('id', $ids ?: [-1]);
        return $query->with('runtimeBatch');
    }

    public static function canEdit($record): bool
    {
        $actor = auth()->user();
        return $actor?->can('bulk_ai_view') && app(BulkRuntimeAuthorizationService::class)->canViewJob($actor, $record);
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
            ->header(function () {
                $counts = (clone static::getEloquentQuery())
                    ->selectRaw("SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued")
                    ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing")
                    ->selectRaw("SUM(CASE WHEN status = 'needs_review' THEN 1 ELSE 0 END) AS review")
                    ->selectRaw("SUM(CASE WHEN status IN ('completed', 'completed_with_errors') THEN 1 ELSE 0 END) AS completed")
                    ->selectRaw("SUM(CASE WHEN status IN ('failed', 'blocked', 'cancelled') THEN 1 ELSE 0 END) AS failed")
                    ->toBase()
                    ->first();

                return view('filament.ai-product-jobs.table-header', [
                    'summary' => (array) $counts,
                    'policy' => app(AIRuntimePolicyService::class)->snapshot(),
                ]);
            })
            ->headerActions([
                Action::make('refresh_runtime')
                    ->label('Làm mới')
                    ->icon(Heroicon::ArrowPath)
                    ->action(fn ($livewire) => $livewire->dispatch('$refresh')),
            ])
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('type')->label('Thao tác')->badge()->searchable()->limit(36)->tooltip(fn ($state) => $state),
                TextColumn::make('scope')->label('Phạm vi')->badge(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'queued' => 'Đang chờ',
                        'processing' => 'Đang xử lý',
                        'needs_review' => 'Cần duyệt',
                        'completed' => 'Hoàn thành',
                        'completed_with_errors' => 'Hoàn thành có lỗi',
                        'failed' => 'Thất bại',
                        'blocked' => 'Bị chặn',
                        'cancelled' => 'Đã hủy',
                        default => str($state)->replace('_', ' ')->title()->toString(),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'queued' => 'gray',
                        'processing', 'needs_review', 'completed_with_errors' => 'warning',
                        'failed', 'blocked', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('failed_reason')->label('Lý do lỗi')->badge()->color('danger')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('queue_name')->label('Queue')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attempts')->label('Lần thử')->numeric()->sortable(),
                TextColumn::make('progress')
                    ->label('Tiến độ')
                    ->state(fn (AiProductJob $record): string => number_format((int) $record->processed).' / '.number_format((int) $record->total))
                    ->description(fn (AiProductJob $record): string => "Thành công {$record->success} · Lỗi {$record->failed} · Cần duyệt {$record->needs_review}"),
                TextColumn::make('runtime_status')->label('Runtime')->state(fn (AiProductJob $record) => $record->runtimeBatch?->status ?: '-')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('runtime_running')->label('Đang chạy')->state(fn (AiProductJob $record) => $record->runtimeBatch ? app(BulkRuntimeObservabilityService::class)->snapshot($record->runtimeBatch)['running'] : 0)->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('runtime_tokens')->label('Tokens')->state(fn (AiProductJob $record) => $record->runtimeBatch ? app(BulkRuntimeObservabilityService::class)->snapshot($record->runtimeBatch)['tokens_consumed'] : 0)->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('runtime_worker')->label('Worker')->state(fn (AiProductJob $record) => $record->runtimeBatch ? app(BulkRuntimeObservabilityService::class)->snapshot($record->runtimeBatch)['worker_health'] : '-')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Cập nhật')->since()->dateTimeTooltip()->sortable(),
                TextColumn::make('created_at')->label('Tạo lúc')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('finished_at')->label('Hoàn tất')->dateTime('d/m/Y H:i')->sortable()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
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
                EditAction::make()->label('Xem báo cáo'),
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
