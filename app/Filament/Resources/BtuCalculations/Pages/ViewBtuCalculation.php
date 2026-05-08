<?php

namespace App\Filament\Resources\BtuCalculations\Pages;

use App\Filament\Resources\BtuCalculations\BtuCalculationResource;
use App\Models\BtuCalculation;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Action;

class ViewBtuCalculation extends ViewRecord
{
    protected static string $resource = BtuCalculationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([

            // ── Kết quả tính toán ──────────────────────────────────
            Section::make('Kết quả tính BTU')->schema([
                Grid::make(['default' => 2, 'md' => 4])->schema([

                    TextEntry::make('recommended_btu')
                        ->label('BTU đề xuất')
                        ->formatStateUsing(fn ($state) => number_format($state) . ' BTU')
                        ->badge()
                        ->color(fn ($state) => match (true) {
                            $state <= 24000  => 'info',
                            $state <= 36000  => 'success',
                            $state <= 48000  => 'warning',
                            default          => 'danger',
                        })
                        ->size('lg'),

                    TextEntry::make('area_m2')
                        ->label('Diện tích')
                        ->formatStateUsing(fn ($state) => $state . ' m²'),

                    TextEntry::make('space_type')
                        ->label('Loại không gian')
                        ->formatStateUsing(fn ($state) => BtuCalculation::spaceTypeLabels()[$state] ?? $state)
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('created_at')
                        ->label('Thời gian')
                        ->dateTime('d/m/Y H:i'),
                ]),
            ]),

            // ── Chi tiết kỹ thuật ────────────────────────────────
            Section::make('Chi tiết kỹ thuật')->schema([
                Grid::make(['default' => 2, 'md' => 4])->schema([

                    TextEntry::make('calculated_btu')
                        ->label('BTU tính toán (raw)')
                        ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' BTU' : null)
                        ->visible(fn ($record) => !empty($record->calculated_btu) && $record->calculated_btu !== $record->recommended_btu),

                    TextEntry::make('cooling_w_per_m2')
                        ->label('Tải lạnh')
                        ->formatStateUsing(fn ($state) => $state ? $state . ' W/m²' : null)
                        ->badge()->color('info')
                        ->visible(fn ($record) => !empty($record->cooling_w_per_m2)),
                ]),
            ]),

            // ── Thông số đầu vào ───────────────────────────────────
            Section::make('Thông số đầu vào')->schema([
                Grid::make(['default' => 2, 'md' => 3])->schema([

                    TextEntry::make('ceiling_height')
                        ->label('Chiều cao trần')
                        ->formatStateUsing(fn ($state) => $state . ' m')
                        ->visible(fn ($record) => !empty($record->ceiling_height) && $record->ceiling_height != 3.0),

                    TextEntry::make('people_count')
                        ->label('Số người')
                        ->visible(fn ($record) => !empty($record->people_count)),

                    TextEntry::make('direct_sunlight')
                        ->label('Nắng trực tiếp')
                        ->formatStateUsing(fn ($state) => $state ? 'Có' : 'Không')
                        ->badge()
                        ->color(fn ($state) => $state ? 'warning' : 'gray'),

                    TextEntry::make('heat_equipment')
                        ->label('Thiết bị sinh nhiệt')
                        ->formatStateUsing(fn ($state) => $state ? 'Có' : 'Không')
                        ->badge()
                        ->color(fn ($state) => $state ? 'danger' : 'gray'),

                    TextEntry::make('priority')
                        ->label('Ưu tiên')
                        ->formatStateUsing(fn ($state) => BtuCalculation::priorityLabels()[$state] ?? $state)
                        ->visible(fn ($record) => !empty($record->priority)),
                ]),
            ]),

            // ── Thông tin liên hệ ────────────────────────────────
            Section::make('Thông tin liên hệ')
                ->visible(fn ($record) =>
                    !empty($record->full_name) ||
                    !empty($record->phone) ||
                    !empty($record->email)
                )
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([

                        TextEntry::make('full_name')
                            ->label('Họ tên')
                            ->weight('bold')
                            ->visible(fn ($record) => !empty($record->full_name)),

                        TextEntry::make('phone')
                            ->label('Số điện thoại')
                            ->copyable()
                            ->icon('heroicon-o-phone')
                            ->url(fn ($record) => $record?->phone ? 'tel:' . $record->phone : null)
                            ->visible(fn ($record) => !empty($record->phone)),

                        TextEntry::make('email')
                            ->label('Email')
                            ->copyable()
                            ->visible(fn ($record) => !empty($record->email)),

                        TextEntry::make('note')
                            ->label('Ghi chú')
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->note)),
                    ]),
                ]),

            // ── Tracking ───────────────────────────────────
            Section::make('Tracking')->collapsed()->schema([
                Grid::make(['default' => 2, 'md' => 3])->schema([
                    TextEntry::make('id')->label('BTU Calc ID')->prefix('#'),
                    TextEntry::make('source_page')->label('Trang nguồn')->limit(60)
                        ->visible(fn ($record) => !empty($record->source_page)),
                    TextEntry::make('ip_address')->label('IP')
                        ->visible(fn ($record) => !empty($record->ip_address)),
                ]),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('call')
                ->label('Gọi điện')
                ->icon('heroicon-o-phone')
                ->color('success')
                ->url(fn () => 'tel:' . $this->record->phone)
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->record->phone)),

            Action::make('zalo')
                ->label('Zalo')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('info')
                ->url(fn () => 'https://zalo.me/' . preg_replace('/^0/', '84', preg_replace('/\D/', '', $this->record->phone ?? '')))
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->record->phone)),
        ];
    }
}
