<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\QuoteRequest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        // ── Thông tin liên hệ ──
                        Section::make('Thông tin liên hệ')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('full_name')
                                    ->label('Họ tên')
                                    ->required(),
                                TextInput::make('phone')
                                    ->label('Số điện thoại')
                                    ->tel()
                                    ->required()
                                    ->suffixAction(
                                        \Filament\Forms\Components\Actions\Action::make('call')
                                            ->icon('heroicon-o-phone')
                                            ->url(fn ($get) => $get('phone') ? 'tel:' . $get('phone') : null)
                                            ->openUrlInNewTab()
                                    ),
                            ]),
                            TextInput::make('email')
                                ->label('Email')
                                ->email(),
                            TextInput::make('region')
                                ->label('Khu vực'),
                            Textarea::make('message')
                                ->label('Lời nhắn')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),

                        // ── Nhu cầu khách hàng ──
                        Section::make('Nhu cầu khách hàng')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Select::make('lead_type')
                                    ->label('Loại lead')
                                    ->options(Lead::leadTypeLabels())
                                    ->required(),
                                TextInput::make('intent_score')
                                    ->label('Điểm ý định (Intent Score)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                            ]),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Select::make('interested_product_id')
                                    ->label('Sản phẩm quan tâm')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->nullable(),
                                Select::make('usage_type')
                                    ->label('Loại công trình')
                                    ->options(QuoteRequest::projectTypeLabels())
                                    ->nullable(),
                            ]),
                            Grid::make(['default' => 1, 'md' => 3])->schema([
                                TextInput::make('area')
                                    ->label('Diện tích (m²)')
                                    ->numeric(),
                                TextInput::make('capacity_btu')
                                    ->label('BTU')
                                    ->numeric(),
                                TextInput::make('budget')
                                    ->label('Ngân sách'),
                            ]),
                        ]),

                        // ── Product metadata (readonly) ──
                        Section::make('Metadata sản phẩm')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    TextInput::make('product_name')->label('Tên SP')->disabled(),
                                    TextInput::make('product_sku')->label('SKU')->disabled(),
                                    TextInput::make('brand_name')->label('Thương hiệu')->disabled(),
                                    TextInput::make('category_name')->label('Danh mục')->disabled(),
                                    TextInput::make('product_url')->label('URL SP')->disabled()->columnSpanFull(),
                                ]),
                            ]),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Trạng thái & Ghi chú')->schema([
                            Select::make('status')
                                ->label('Trạng thái xử lý')
                                ->options(LeadStatus::class)
                                ->default('new')
                                ->required(),
                            TextInput::make('source_page')
                                ->label('Nguồn (Source Page)')
                                ->disabled(),
                            TextInput::make('need_type')
                                ->label('Loại nhu cầu')
                                ->disabled(),
                            Textarea::make('admin_note')
                                ->label('Ghi chú của Admin')
                                ->rows(5),
                        ]),

                        Section::make('Liên kết')->schema([
                            TextInput::make('quote_request_id')
                                ->label('Mã báo giá')
                                ->disabled()
                                ->suffixAction(
                                    \Filament\Forms\Components\Actions\Action::make('view_quote')
                                        ->icon('heroicon-o-arrow-top-right-on-square')
                                        ->url(fn ($get) => $get('quote_request_id')
                                            ? route('filament.admin.resources.quote-requests.view', $get('quote_request_id'))
                                            : null)
                                        ->openUrlInNewTab()
                                        ->visible(fn ($get) => !empty($get('quote_request_id')))
                                ),
                        ])->collapsible()->collapsed(),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}
