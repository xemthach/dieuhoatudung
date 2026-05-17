<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Enums\DiscountType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                        Section::make('Thông tin khuyến mãi')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('title')
                                    ->label('Tiêu đề')
                                    ->required(),
                                TextInput::make('slug')
                                    ->label('Đường dẫn')
                                    ->required(),
                            ]),
                            Textarea::make('description')
                                ->label('Mô tả chương trình')
                                ->rows(3)
                                ->columnSpanFull(),
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
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Thời gian & Trạng thái')->schema([
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
