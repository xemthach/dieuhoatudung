<?php

namespace App\Filament\Resources\QuoteRequests;

use App\Enums\QuoteRequestStatus;
use App\Filament\Resources\QuoteRequests\Pages\ListQuoteRequests;
use App\Filament\Resources\QuoteRequests\Pages\ViewQuoteRequest;
use App\Models\QuoteRequest;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Carbon\Carbon;
use App\Filament\Traits\HasResourcePermissions;

class QuoteRequestResource extends Resource
{
    use HasResourcePermissions;

    protected static array $permissionMap = [
        'viewAny' => 'quote_request.view',
        'create'  => 'quote_request.edit',
        'edit'    => 'quote_request.edit',
        'delete'  => 'quote_request.delete',
    ];

    protected static ?string $model = QuoteRequest::class;

    public static function getNavigationIcon(): ?string  { return 'heroicon-o-document-text'; }
    public static function getNavigationLabel(): string  { return 'Báo giá'; }
    public static function getNavigationGroup(): ?string { return 'Leads & Contacts'; }
    public static function getModelLabel(): string       { return 'Yêu cầu báo giá'; }
    public static function getPluralModelLabel(): string { return 'Yêu cầu báo giá'; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                // ── Thời gian ──────────────────────────────────────────────
                TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m H:i')
                    ->sortable()
                    ->description(fn ($record) => $record?->created_at?->diffForHumans()),

                // ── Khách hàng ─────────────────────────────────────────────
                TextColumn::make('full_name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn ($record) => $record?->phone),

                // ── Lead type + Intent score ────────────────────────────
                TextColumn::make('lead_type')
                    ->label('Loại lead')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'product'      => 'Product',
                        'consultation' => 'Tư vấn',
                        default        => 'General',
                    })
                    ->color(fn ($state) => match ($state) {
                        'product'      => 'success',
                        'consultation' => 'warning',
                        default        => 'gray',
                    })
                    ->description(fn ($record) => $record?->intent_score ? 'Score: ' . $record->intent_score . '/100' : null),

                // ── Sản phẩm (chỉ hiển thị nếu có) ─────────────────────
                TextColumn::make('product_name')
                    ->label('Sản phẩm')
                    ->limit(28)
                    ->placeholder('—')
                    ->description(fn ($record) => $record?->product_sku ?: ($record?->product_capacity_btu ? number_format($record->product_capacity_btu) . ' BTU' : null)),

                // ── Công trình ─────────────────────────────────────────────
                TextColumn::make('project_type')
                    ->label('Công trình')
                    ->formatStateUsing(fn ($state) => QuoteRequest::projectTypeLabels()[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                // ── Diện tích ──────────────────────────────────────────────
                TextColumn::make('area_m2')
                    ->label('Diện tích')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 0) . ' m²' : null)
                    ->placeholder('—'),

                // ── BTU tính toán ──────────────────────────────────────────
                TextColumn::make('calculated_btu')
                    ->label('BTU đề xuất')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' BTU' : null)
                    ->placeholder('—')
                    ->color('primary'),

                // ── Ngân sách ──────────────────────────────────────────────
                TextColumn::make('budget_range')
                    ->label('Ngân sách')
                    ->formatStateUsing(fn ($state) => QuoteRequest::budgetRangeLabels()[$state] ?? ($state ?? '—'))
                    ->placeholder('—'),

                // ── Trạng thái ─────────────────────────────────────────────
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof QuoteRequestStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof QuoteRequestStatus ? $state->color() : 'gray'),

                // ── Nguồn (ẩn mặc định) ────────────────────────────────────────
                TextColumn::make('source_page')
                    ->label('Nguồn')
                    ->limit(22)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                SelectFilter::make('lead_type')
                    ->label('Loại lead')
                    ->options(['product' => 'Product Lead', 'general' => 'General', 'consultation' => 'Tư vấn']),

                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(QuoteRequestStatus::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                        ->toArray()),

                SelectFilter::make('project_type')
                    ->label('Loại công trình')
                    ->options(QuoteRequest::projectTypeLabels()),

                SelectFilter::make('budget_range')
                    ->label('Ngân sách')
                    ->options(QuoteRequest::budgetRangeLabels()),

                Filter::make('high_intent')
                    ->label('Intent cao (>=70)')
                    ->query(fn (Builder $q) => $q->where('intent_score', '>=', 70)),

                Filter::make('has_product')
                    ->label('Có sản phẩm')
                    ->query(fn (Builder $q) => $q->whereNotNull('product_name')),

                Filter::make('today')
                    ->label('Hôm nay')
                    ->query(fn (Builder $q) => $q->whereDate('created_at', today())),

                Filter::make('this_week')
                    ->label('Tuần này')
                    ->query(fn (Builder $q) => $q->whereBetween('created_at', [
                        Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek(),
                    ])),

                Filter::make('new_only')
                    ->label('Chỉ mới')
                    ->query(fn (Builder $q) => $q->where('status', 'new')),

                TrashedFilter::make(),
            ])

            ->recordActions([
                Action::make('call')
                    ->label('Gọi')
                    ->icon('heroicon-o-phone')
                    ->url(fn (QuoteRequest $record) => $record->phone ? 'tel:' . $record->phone : null)
                    ->openUrlInNewTab()
                    ->color('success')
                    ->visible(fn (QuoteRequest $record) => !empty($record->phone)),

                Action::make('zalo')
                    ->label('Zalo')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->url(fn (QuoteRequest $record) => $record->phone
                        ? 'https://zalo.me/' . preg_replace('/^0/', '84', preg_replace('/\D/', '', $record->phone))
                        : null)
                    ->openUrlInNewTab()
                    ->color('info')
                    ->visible(fn (QuoteRequest $record) => !empty($record->phone)),

                Action::make('change_status')
                    ->label('Đổi trạng thái')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Select::make('status')
                            ->label('Trạng thái mới')
                            ->options(collect(QuoteRequestStatus::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                ->toArray())
                            ->required(),
                        Textarea::make('admin_note')
                            ->label('Ghi chú admin')
                            ->rows(2),
                    ])
                    ->action(fn (QuoteRequest $record, array $data) => $record->update([
                        'status'     => $data['status'],
                        'admin_note' => $data['admin_note'] ?? $record->admin_note,
                    ])),

                ViewAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuoteRequests::route('/'),
            'view'  => ViewQuoteRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
