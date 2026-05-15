<?php

namespace App\Filament\Resources\AiProductJobs\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.name')->label('Sản phẩm')->searchable()->limit(45),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('seo_score_before')->label('Score trước')->sortable(),
                TextColumn::make('seo_score_after')->label('Score sau')->sortable(),
                TextColumn::make('generated_payload_json.governance_context.data_completeness.score')
                    ->label('Data %')
                    ->suffix('%')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('generated_payload_json.fact_check.status')
                    ->label('Fact check')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'verified' ? 'success' : ($state === 'blocked' ? 'danger' : 'gray'))
                    ->placeholder('-'),
                TextColumn::make('generated_payload_json.governance_context.missing_facts')
                    ->label('Missing data')
                    ->formatStateUsing(fn ($state) => self::formatList($state))
                    ->limit(50)
                    ->tooltip(fn ($record) => self::formatList($record->generated_payload_json['governance_context']['missing_facts'] ?? []))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('generated_payload_json.used_facts')
                    ->label('Used facts')
                    ->formatStateUsing(fn ($state) => self::formatList($state))
                    ->limit(50)
                    ->tooltip(fn ($record) => self::formatList($record->generated_payload_json['used_facts'] ?? []))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('generated_payload_json.fact_check.calculation_source')
                    ->label('Calc source')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('warnings_json')
                    ->label('Warnings')
                    ->formatStateUsing(fn ($state) => self::formatList($state))
                    ->limit(60)
                    ->tooltip(fn ($record) => self::formatList($record->warnings_json ?? [])),
                TextColumn::make('error_message')->label('Lỗi')->limit(60)->color('danger'),
                TextColumn::make('provider')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('model')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('queue_name')->label('Queue')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attempts')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('retry_count')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('failed_reason')->badge()->color('danger')->toggleable(),
                TextColumn::make('exception_class')->limit(30)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('exception_line')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration_ms')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tokens_used')->numeric()->toggleable(),
                TextColumn::make('finished_at')->dateTime('d/m/Y H:i')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'queued' => 'Đang chờ',
                        'processing' => 'Đang xử lý',
                        'completed' => 'Hoàn thành',
                        'needs_review' => 'Cần duyệt',
                        'failed' => 'Thất bại',
                    ]),
            ]);
    }

    private static function formatList(mixed $state): string
    {
        if (! is_array($state)) {
            return is_scalar($state) ? (string) $state : '';
        }

        return collect($state)
            ->map(function ($item): string {
                if (is_scalar($item)) {
                    return (string) $item;
                }

                if (is_array($item)) {
                    foreach (['code', 'warning', 'claim', 'message', 'value', 'name', 'label'] as $key) {
                        if (isset($item[$key]) && is_scalar($item[$key])) {
                            return (string) $item[$key];
                        }
                    }

                    return json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                }

                return '';
            })
            ->filter()
            ->implode(', ');
    }
}
