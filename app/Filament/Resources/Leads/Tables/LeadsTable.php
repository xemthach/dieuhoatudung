<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at?->diffForHumans()),

                TextColumn::make('lead_type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Lead::leadTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => Lead::leadTypeColors()[$state] ?? 'gray')
                    ->sortable(),

                TextColumn::make('intent_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 90  => 'danger',
                        $state >= 60  => 'warning',
                        default       => 'info',
                    })
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn ($record) => $record->email),

                TextColumn::make('phone')
                    ->label('SĐT')
                    ->copyable()
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->url(fn ($record) => $record->phone ? 'tel:' . $record->phone : null),

                TextColumn::make('product_name')
                    ->label('Sản phẩm')
                    ->limit(30)
                    ->placeholder('—')
                    ->description(fn ($record) => implode(' · ', array_filter([
                        $record->brand_name,
                        $record->capacity_btu ? number_format($record->capacity_btu) . ' BTU' : null,
                    ]))),

                TextColumn::make('usage_type')
                    ->label('Công trình')
                    ->formatStateUsing(fn ($state) => \App\Models\QuoteRequest::projectTypeLabels()[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('area')
                    ->label('Diện tích')
                    ->formatStateUsing(fn ($state) => $state ? $state . ' m²' : '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof LeadStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof LeadStatus ? $state->color() : 'gray'),

                TextColumn::make('source_page')
                    ->label('Nguồn')
                    ->limit(20)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('lead_type')
                    ->label('Loại lead')
                    ->options(Lead::leadTypeLabels()),

                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(LeadStatus::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                        ->toArray()),

                Filter::make('high_intent')
                    ->label('Intent cao (≥70)')
                    ->query(fn (Builder $q) => $q->where('intent_score', '>=', 70)),

                Filter::make('has_product')
                    ->label('Có sản phẩm')
                    ->query(fn (Builder $q) => $q->whereNotNull('interested_product_id')),

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
                    ->query(fn (Builder $q) => $q->where('status', 'new'))
                    ->default(false),

                TrashedFilter::make(),
            ])
            ->recordActions([
                // ── Đổi trạng thái ──
                Action::make('change_status')
                    ->label('Đổi trạng thái')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Select::make('status')
                            ->label('Trạng thái mới')
                            ->options(collect(LeadStatus::cases())
                                ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                                ->toArray())
                            ->required(),
                        Textarea::make('admin_note')
                            ->label('Ghi chú')
                            ->rows(3),
                    ])
                    ->action(function (Lead $record, array $data) {
                        $record->update([
                            'status'     => $data['status'],
                            'admin_note' => $data['admin_note'] ?? $record->admin_note,
                        ]);
                    })
                    ->color('warning'),

                // ── Gọi điện ──
                Action::make('call')
                    ->label('Gọi')
                    ->icon('heroicon-o-phone')
                    ->url(fn (Lead $record) => $record->phone ? 'tel:' . $record->phone : null)
                    ->openUrlInNewTab()
                    ->color('success')
                    ->visible(fn (Lead $record) => !empty($record->phone)),

                // ── Mở Zalo ──
                Action::make('zalo')
                    ->label('Zalo')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->url(fn (Lead $record) => $record->phone ? 'https://zalo.me/' . preg_replace('/^0/', '84', preg_replace('/\D/', '', $record->phone)) : null)
                    ->openUrlInNewTab()
                    ->color('info')
                    ->visible(fn (Lead $record) => !empty($record->phone)),

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
