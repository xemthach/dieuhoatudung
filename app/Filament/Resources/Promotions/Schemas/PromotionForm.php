<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Enums\DiscountType;
use App\Filament\Resources\Shared\Forms\AdminContentAutomation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        AdminContentAutomation::slugTracker('title'),
                        Section::make('Thông tin khuyến mãi')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                AdminContentAutomation::titleField('title', 'Tiêu đề'),
                                AdminContentAutomation::slugField('promotions'),
                            ]),
                            AdminContentAutomation::aiAction(
                                module: 'promotion',
                                inputFields: [
                                    'title' => 'title',
                                    'placement' => 'placement',
                                    'scope' => 'scope',
                                ],
                                fieldMap: [
                                    'promotion_description' => 'description',
                                    'short_description' => 'description',
                                    'detailed_content' => 'content',
                                    'meta_description' => 'seo_description',
                                ],
                                fieldOptions: [
                                    'promotion_description' => 'Promotion description',
                                    'cta_content' => 'CTA content',
                                    'banner_copy' => 'Banner copy',
                                    'seo_title' => 'SEO title',
                                    'meta_description' => 'Meta description',
                                    'og_title' => 'OG title',
                                    'og_description' => 'OG description',
                                ],
                            ),
                            Textarea::make('description')
                                ->label('Mô tả chương trình')
                                ->rows(3)
                                ->columnSpanFull(),
                            Textarea::make('content')
                                ->label('Nội dung chi tiết')
                                ->rows(5)
                                ->columnSpanFull(),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('cta_content')->label('CTA content'),
                                TextInput::make('banner_copy')->label('Banner copy'),
                            ]),
                        ]),

                        Section::make('Cấu hình giảm giá')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Select::make('discount_type')
                                    ->label('Loại giảm giá')
                                    ->options(DiscountType::class)
                                    ->default('percent')
                                    ->required(),
                                TextInput::make('discount_value')
                                    ->label('Giá trị giảm')
                                    ->numeric(),
                            ]),
                        ]),

                        Section::make('Phạm vi áp dụng')->schema([
                            Select::make('scope')
                                ->label('Áp dụng cho')
                                ->options([
                                    'global' => 'Toàn site',
                                    'product' => 'Sản phẩm cụ thể',
                                    'category' => 'Danh mục sản phẩm',
                                    'brand' => 'Thương hiệu',
                                ])
                                ->default('global')
                                ->live()
                                ->required(),
                            Select::make('placement')
                                ->label('Placement')
                                ->options([
                                    'landing' => 'Landing page',
                                    'banner' => 'Banner',
                                    'popup' => 'Popup',
                                    'announcement_bar' => 'Announcement bar',
                                ])
                                ->default('landing'),
                            Select::make('products')
                                ->label('Sản phẩm')
                                ->relationship('products', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn ($get) => $get('scope') === 'product'),
                            Select::make('categories')
                                ->label('Danh mục')
                                ->relationship('categories', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn ($get) => $get('scope') === 'category'),
                            Select::make('brands')
                                ->label('Thương hiệu')
                                ->relationship('brands', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn ($get) => $get('scope') === 'brand'),
                        ]),

                        Section::make('SEO & Open Graph')->schema([
                            TextInput::make('seo_title')->label('Tiêu đề SEO'),
                            TextInput::make('seo_description')->label('Meta description'),
                            TextInput::make('og_title')->label('OG title'),
                            TextInput::make('og_description')->label('OG description'),
                        ])->collapsed(),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Thời gian & trạng thái')->schema([
                            DateTimePicker::make('start_at')
                                ->label('Bắt đầu'),
                            DateTimePicker::make('end_at')
                                ->label('Kết thúc'),
                            Toggle::make('is_active')
                                ->label('Kích hoạt')
                                ->default(true)
                                ->required(),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}
