<?php

namespace App\Filament\Resources\BtuCalculations;

use App\Filament\Resources\BtuCalculations\Pages\ListBtuCalculations;
use App\Models\BtuCalculation;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use App\Filament\Traits\HasResourcePermissions;

class BtuCalculationResource extends Resource
{

    use HasResourcePermissions;
    protected static array $permissionMap = [
        'viewAny' => 'btu_calculator.view',
        'create'  => 'btu_calculator.edit',
        'edit'    => 'btu_calculator.edit',
        'delete'  => 'btu_calculator.edit',
    ];

    protected static ?string $model = BtuCalculation::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-calculator'; }
    public static function getNavigationLabel(): string { return 'BTU Calculator'; }
    public static function getNavigationGroup(): ?string { return 'Leads & Contacts'; }
    public static function getModelLabel(): string { return 'Lịch sử tính BTU'; }
    public static function getPluralModelLabel(): string { return 'Lịch sử tính BTU'; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('recommended_btu')
                    ->label('BTU đề xuất')
                    ->formatStateUsing(fn($state) => number_format($state) . ' BTU')
                    ->badge()
                    ->color(fn($state) => match(true) {
                        $state <= 24000  => 'info',
                        $state <= 36000  => 'success',
                        $state <= 48000  => 'warning',
                        default          => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('area_m2')
                    ->label('Diện tích')
                    ->formatStateUsing(fn($state) => $state . ' m²')
                    ->sortable(),

                TextColumn::make('space_type')
                    ->label('Loại không gian')
                    ->formatStateUsing(fn($state) => BtuCalculation::spaceTypeLabels()[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                IconColumn::make('direct_sunlight')
                    ->label('Nắng')
                    ->boolean()
                    ->trueIcon('heroicon-o-sun')
                    ->falseIcon('heroicon-o-moon')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                TextColumn::make('phone')
                    ->label('SĐT liên hệ')
                    ->placeholder('—')
                    ->copyable()
                    ->icon(fn($state) => $state ? 'heroicon-o-phone' : null),

                TextColumn::make('full_name')
                    ->label('Tên')
                    ->placeholder('—'),

                TextColumn::make('priority')
                    ->label('Ưu tiên')
                    ->formatStateUsing(fn($state) => BtuCalculation::priorityLabels()[$state] ?? ($state ?? '—'))
                    ->placeholder('—'),

                TextColumn::make('source_page')
                    ->label('Nguồn')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('recommended_btu')
                    ->label('BTU đề xuất')
                    ->options([
                        24000  => '24.000 BTU',
                        28000  => '28.000 BTU',
                        36000  => '36.000 BTU',
                        48000  => '48.000 BTU',
                        50000  => '50.000 BTU',
                        60000  => '60.000 BTU',
                        100000 => '100.000 BTU',
                    ]),

                SelectFilter::make('space_type')
                    ->label('Loại không gian')
                    ->options(BtuCalculation::spaceTypeLabels()),

                Filter::make('has_contact')
                    ->label('Có thông tin liên hệ')
                    ->query(fn(Builder $q) => $q->whereNotNull('phone')),

                Filter::make('today')
                    ->label('Hôm nay')
                    ->query(fn(Builder $q) => $q->whereDate('created_at', today())),

                Filter::make('this_week')
                    ->label('Tuần này')
                    ->query(fn(Builder $q) => $q->whereBetween('created_at', [
                        Carbon::now()->startOfWeek(),
                        Carbon::now()->endOfWeek(),
                    ])),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBtuCalculations::route('/'),
            'view'  => Pages\ViewBtuCalculation::route('/{record}'),
        ];
    }
}
