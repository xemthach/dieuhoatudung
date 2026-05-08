<?php

namespace App\Filament\Resources\QuoteRequests\Pages;

use App\Enums\QuoteRequestStatus;
use App\Filament\Resources\QuoteRequests\QuoteRequestResource;
use App\Models\QuoteRequest;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class ViewQuoteRequest extends ViewRecord
{
    protected static string $resource = QuoteRequestResource::class;

    /**
     * Helper: only show entry if the record field is non-empty.
     * Returns the entry with ->visible(fn) already set.
     */
    private static function ifNotEmpty(TextEntry $entry, string $field): TextEntry
    {
        return $entry->visible(fn ($record) => !empty($record->{$field}));
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([

            // ══════════════════════════════════════════
            // Card 1: Tong quan lead
            // ══════════════════════════════════════════
            Section::make('Tong quan')->schema([
                Grid::make(['default' => 2, 'md' => 4])->schema([

                    TextEntry::make('lead_type')
                        ->label('Loai lead')
                        ->badge()
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'product'      => 'Product Lead',
                            'consultation' => 'Tu van',
                            default        => 'General',
                        })
                        ->color(fn ($state) => match ($state) {
                            'product'      => 'success',
                            'consultation' => 'warning',
                            default        => 'gray',
                        }),

                    TextEntry::make('intent_score')
                        ->label('Intent Score')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ($state ?? 0) . ' / 100')
                        ->color(fn ($state) => ($state ?? 0) >= 80 ? 'success' : (($state ?? 0) >= 50 ? 'warning' : 'gray')),

                    TextEntry::make('status')
                        ->label('Trạng thái')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof QuoteRequestStatus ? $state->label() : $state)
                        ->color(fn ($state) => $state instanceof QuoteRequestStatus ? $state->color() : 'gray'),

                    TextEntry::make('created_at')
                        ->label('Nhận lúc')
                        ->dateTime('d/m/Y H:i'),
                ]),
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextEntry::make('source_page')->label('Trang nguồn')->limit(60)
                        ->visible(fn ($record) => !empty($record->source_page)),
                    TextEntry::make('utm_source')->label('UTM Source')
                        ->visible(fn ($record) => !empty($record->utm_source)),
                    TextEntry::make('utm_campaign')->label('UTM Campaign')
                        ->visible(fn ($record) => !empty($record->utm_campaign)),
                ]),
            ]),

            // ══════════════════════════════════════════
            // Card 2: Khách hàng
            // ══════════════════════════════════════════
            Section::make('Khách hàng')->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([

                    TextEntry::make('full_name')->label('Họ tên')->weight('bold'),

                    TextEntry::make('phone')->label('Số điện thoại')
                        ->copyable()
                        ->icon('heroicon-o-phone')
                        ->url(fn ($record) => $record?->phone ? 'tel:' . $record->phone : null)
                        ->openUrlInNewTab(false)
                        ->visible(fn ($record) => !empty($record?->phone)),

                    TextEntry::make('email')->label('Email')
                        ->copyable()
                        ->visible(fn ($record) => !empty($record->email)),

                    TextEntry::make('province_city')->label('Tỉnh / Thành phố')
                        ->visible(fn ($record) => !empty($record->province_city)),

                    TextEntry::make('address')->label('Địa chỉ')
                        ->visible(fn ($record) => !empty($record->address)),

                    TextEntry::make('preferred_contact_method')->label('Liên hệ qua')
                        ->formatStateUsing(fn ($state) => QuoteRequest::contactMethodLabels()[$state] ?? $state)
                        ->visible(fn ($record) => !empty($record->preferred_contact_method)),

                    TextEntry::make('preferred_contact_time')->label('Giờ liên hệ')
                        ->formatStateUsing(fn ($state) => QuoteRequest::contactTimeLabels()[$state] ?? $state)
                        ->visible(fn ($record) => !empty($record->preferred_contact_time)),
                ]),
            ]),

            // ══════════════════════════════════════════
            // Card 3: Sản phẩm (chỉ hiển thị khi có)
            // ══════════════════════════════════════════
            Section::make('Sản phẩm quan tâm')
                ->visible(fn ($record) => !empty($record->product_name))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([

                        TextEntry::make('product_name')
                            ->label('Tên sản phẩm')
                            ->weight('bold')
                            ->url(fn ($record) => $record->product_url ?: null, true),

                        TextEntry::make('product_sku')->label('SKU')
                            ->copyable()
                            ->visible(fn ($record) => !empty($record->product_sku)),

                        TextEntry::make('product_model')->label('Model')
                            ->visible(fn ($record) => !empty($record->product_model)),

                        TextEntry::make('product_brand')->label('Thương hiệu')
                            ->visible(fn ($record) => !empty($record->product_brand)),

                        TextEntry::make('product_category')->label('Danh mục')
                            ->visible(fn ($record) => !empty($record->product_category)),

                        TextEntry::make('product_capacity_btu')->label('Công suất sản phẩm')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' BTU' : null)
                            ->badge()->color('primary')
                            ->visible(fn ($record) => !empty($record->product_capacity_btu)),
                    ]),
                ]),

            // ══════════════════════════════════════════
            // Card 4: Nhu cầu HVAC
            // ══════════════════════════════════════════
            Section::make('Nhu cầu HVAC')
                ->visible(fn ($record) =>
                    !empty($record->project_type) ||
                    !empty($record->area_m2) ||
                    !empty($record->calculated_btu) ||
                    !empty($record->budget_range)
                )
                ->schema([
                    Grid::make(['default' => 2, 'md' => 3])->schema([

                        TextEntry::make('project_type')->label('Loại công trình')
                            ->formatStateUsing(fn ($state) => QuoteRequest::projectTypeLabels()[$state] ?? $state)
                            ->badge()->color('info')
                            ->visible(fn ($record) => !empty($record->project_type)),

                        TextEntry::make('number_of_rooms')->label('Số phòng')
                            ->visible(fn ($record) => !empty($record->number_of_rooms) && $record->number_of_rooms > 1),

                        TextEntry::make('usage_description')->label('Mô tả không gian')
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->usage_description)),

                        TextEntry::make('area_m2')->label('Diện tích')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 0) . ' m²' : null)
                            ->visible(fn ($record) => !empty($record->area_m2)),

                        TextEntry::make('ceiling_height')->label('Chiều cao trần')
                            ->formatStateUsing(fn ($state) => $state ? $state . ' m' : null)
                            ->visible(fn ($record) => !empty($record->ceiling_height)),

                        TextEntry::make('estimated_volume_m3')->label('Thể tích')
                            ->formatStateUsing(fn ($state) => $state ? $state . ' m³' : null)
                            ->visible(fn ($record) => !empty($record->estimated_volume_m3)),

                        TextEntry::make('number_of_people')->label('Số người')
                            ->visible(fn ($record) => !empty($record->number_of_people)),

                        TextEntry::make('sun_exposure')->label('Tiếp xúc nắng')
                            ->formatStateUsing(fn ($state) => QuoteRequest::sunExposureLabels()[$state] ?? $state)
                            ->visible(fn ($record) => !empty($record->sun_exposure)),

                        TextEntry::make('current_aircon_status')->label('Điều hòa hiện tại')
                            ->formatStateUsing(fn ($state) => QuoteRequest::airconStatusLabels()[$state] ?? $state)
                            ->visible(fn ($record) => !empty($record->current_aircon_status)),

                        // BTU
                        TextEntry::make('calculated_btu')->label('BTU đề xuất (tính toán)')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' BTU' : null)
                            ->badge()->color('primary')
                            ->visible(fn ($record) => !empty($record->calculated_btu)),

                        TextEntry::make('preferred_btu')->label('BTU khách yêu cầu')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' BTU' : null)
                            ->visible(fn ($record) => !empty($record->preferred_btu)),

                        TextEntry::make('suggested_capacity_range')->label('Khoảng công suất')
                            ->visible(fn ($record) => !empty($record->suggested_capacity_range)),

                        TextEntry::make('preferred_brands')->label('Thương hiệu ưa thích')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                            ->visible(fn ($record) => !empty($record->preferred_brands)),

                        TextEntry::make('installation_type')->label('Loại lắp đặt')
                            ->formatStateUsing(fn ($state) => QuoteRequest::installationTypeLabels()[$state] ?? $state)
                            ->visible(fn ($record) => !empty($record->installation_type)),

                        TextEntry::make('power_supply')->label('Nguồn điện')
                            ->visible(fn ($record) => !empty($record->power_supply)),
                    ]),
                ]),

            // ══════════════════════════════════════════
            // Card 5: Ngân sách & thời gian
            // ══════════════════════════════════════════
            Section::make('Ngân sách & Thời gian')
                ->visible(fn ($record) =>
                    !empty($record->budget_range) ||
                    !empty($record->installation_time) ||
                    !empty($record->need_installation_service)
                )
                ->schema([
                    Grid::make(['default' => 2, 'md' => 3])->schema([

                        TextEntry::make('budget_range')->label('Ngân sách')
                            ->formatStateUsing(fn ($state) => QuoteRequest::budgetRangeLabels()[$state] ?? $state)
                            ->badge()->color('warning')
                            ->visible(fn ($record) => !empty($record->budget_range)),

                        TextEntry::make('installation_time')->label('Thời gian lắp')
                            ->formatStateUsing(fn ($state) => QuoteRequest::installationTimeLabels()[$state] ?? $state)
                            ->badge()
                            ->color(fn ($record) => in_array($record->installation_time, ['ngay', '3_ngay']) ? 'danger' : 'gray')
                            ->visible(fn ($record) => !empty($record->installation_time)),

                        TextEntry::make('need_installation_service')->label('Dịch vụ')
                            ->formatStateUsing(fn ($state) => QuoteRequest::needInstallLabels()[$state] ?? $state)
                            ->visible(fn ($record) => !empty($record->need_installation_service)),

                        TextEntry::make('need_invoice')->label('Cần hóa đơn VAT')
                            ->formatStateUsing(fn ($state) => $state ? 'Có' : 'Không')
                            ->visible(fn ($record) => $record->need_invoice),

                        TextEntry::make('need_site_survey')->label('Cần khảo sát')
                            ->formatStateUsing(fn ($state) => $state ? 'Có' : 'Không')
                            ->visible(fn ($record) => $record->need_site_survey),
                    ]),
                ]),

            // ══════════════════════════════════════════
            // Card 6: Ghi chú (chỉ hiển thị khi có)
            // ══════════════════════════════════════════
            Section::make('Ghi chú')
                ->visible(fn ($record) => !empty($record->message) || !empty($record->admin_note))
                ->schema([
                    TextEntry::make('message')->label('Ghi chú khách')
                        ->columnSpanFull()
                        ->visible(fn ($record) => !empty($record->message)),

                    TextEntry::make('admin_note')->label('Ghi chú Admin')
                        ->columnSpanFull()
                        ->visible(fn ($record) => !empty($record->admin_note)),
                ]),

            // ══════════════════════════════════════════
            // Card 7: Tracking (collapsed, optional)
            // ══════════════════════════════════════════
            Section::make('Tracking')->collapsed()->schema([
                Grid::make(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('id')->label('Quote ID')->prefix('#'),
                    TextEntry::make('ip_address')->label('IP')
                        ->visible(fn ($record) => !empty($record->ip_address)),
                    TextEntry::make('utm_source')->label('UTM Source')
                        ->visible(fn ($record) => !empty($record->utm_source)),
                    TextEntry::make('utm_medium')->label('UTM Medium')
                        ->visible(fn ($record) => !empty($record->utm_medium)),
                    TextEntry::make('utm_campaign')->label('UTM Campaign')
                        ->visible(fn ($record) => !empty($record->utm_campaign)),
                    TextEntry::make('utm_term')->label('UTM Term')
                        ->visible(fn ($record) => !empty($record->utm_term)),
                    TextEntry::make('landing_page')->label('Landing Page')
                        ->limit(50)
                        ->visible(fn ($record) => !empty($record->landing_page)),
                    TextEntry::make('referrer')->label('Referrer')
                        ->limit(50)
                        ->visible(fn ($record) => !empty($record->referrer)),
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

            Action::make('change_status')
                ->label('Đổi trạng thái')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    Select::make('status')
                        ->label('Trạng thái mới')
                        ->options(collect(QuoteRequestStatus::cases())
                            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                            ->toArray())
                        ->default(fn () => $this->record->status instanceof QuoteRequestStatus
                            ? $this->record->status->value
                            : $this->record->status)
                        ->required(),
                    Textarea::make('admin_note')
                        ->label('Ghi chú admin')
                        ->default(fn () => $this->record->admin_note)
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status'     => $data['status'],
                        'admin_note' => $data['admin_note'] ?? $this->record->admin_note,
                    ]);
                    Notification::make()->title('Đã cập nhật trạng thái')->success()->send();
                    $this->refreshFormData(['status', 'admin_note']);
                }),
        ];
    }
}
