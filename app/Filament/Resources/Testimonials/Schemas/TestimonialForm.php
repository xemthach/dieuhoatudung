<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Section::make('Nội dung đánh giá')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('customer_name')
                                ->label('Tên khách hàng')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('customer_title')
                                ->label('Chức danh')
                                ->maxLength(255),
                            TextInput::make('company_name')
                                ->label('Tên công ty / Tổ chức')
                                ->maxLength(255),
                            TextInput::make('location')
                                ->label('Địa điểm')
                                ->maxLength(255),
                            Select::make('rating')
                                ->label('Đánh giá (Sao)')
                                ->options([
                                    5 => '5 Sao - Rất hài lòng',
                                    4 => '4 Sao - Hài lòng',
                                    3 => '3 Sao - Bình thường',
                                    2 => '2 Sao - Không hài lòng',
                                    1 => '1 Sao - Rất tệ',
                                ])
                                ->default(5),
                        ]),
                        Textarea::make('content')
                            ->label('Nội dung')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        ])
                    ])->columnSpan(2),

                    Group::make()->schema([
                        Section::make('Truyền thông')->schema([
                            FileUpload::make('avatar')
                                ->label('Avatar (Ảnh đại diện)')
                                ->image()
                                ->imageEditor()
                                ->directory('testimonials/avatars'),
                            FileUpload::make('image')
                                ->label('Hình ảnh công trình/Sản phẩm (Tùy chọn)')
                                ->image()
                                ->imageEditor()
                                ->directory('testimonials/images'),
                        ]),
                        Section::make('Liên kết (Tùy chọn)')->schema([
                            Select::make('product_id')
                                ->label('Sản phẩm liên quan')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('case_study_id')
                                ->label('Dự án (Case Study)')
                                ->relationship('caseStudy', 'title')
                                ->searchable()
                                ->preload(),
                        ]),
                        Section::make('Trạng thái')->schema([
                            Toggle::make('is_active')
                                ->label('Hiển thị')
                                ->default(true)
                                ->required(),
                            Toggle::make('is_featured')
                                ->label('Nổi bật (Hiện trang chủ)')
                                ->default(false)
                                ->required(),
                            TextInput::make('sort_order')
                                ->label('Thứ tự sắp xếp')
                                ->required()
                                ->numeric()
                                ->default(0),
                        ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }
}



