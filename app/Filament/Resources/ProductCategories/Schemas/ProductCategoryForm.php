<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Enums\ProductCategoryType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use App\Services\Media\MediaDiskService;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Section::make('Thông tin chung')->schema([
                            Select::make('parent_id')
                                ->label('Danh mục cha')
                                ->relationship('parent', 'name'),
                            Select::make('type')
                                ->label('Loại danh mục')
                                ->options(ProductCategoryType::class)
                                ->default('main')
                                ->required(),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('name')
                                    ->label('Tên danh mục')
                                    ->required(),
                                TextInput::make('slug')
                                    ->label('Đường dẫn (Slug)')
                                    ->required(),
                            ]),
                            Textarea::make('intro')
                                ->label('Mô tả ngắn')
                                ->rows(3)
                                ->columnSpanFull(),
                            Textarea::make('content')
                                ->label('Nội dung chi tiết')
                                ->rows(5)
                                ->columnSpanFull(),
                        ]),
                        
                        Section::make('Cấu hình SEO')->schema([
                            TextInput::make('seo_title')->label('Tiêu đề SEO'),
                            TextInput::make('seo_description')->label('Mô tả SEO'),
                            TextInput::make('canonical_url')->label('Canonical URL')->url(),
                            TextInput::make('robots')
                                ->label('Robots')
                                ->required()
                                ->default('index,follow'),
                        ])->collapsed(),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Truyền thông')->schema([
                            FileUpload::make('image')
                                ->label('Ảnh đại diện')
                                ->image()
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory('categories'),
                        ]),
                        
                        Section::make('Trạng thái')->schema([
                            Toggle::make('is_active')
                                ->label('Kích hoạt')
                                ->default(true)
                                ->required(),
                            Toggle::make('is_indexable')
                                ->label('Cho phép Index')
                                ->default(true)
                                ->required(),
                            TextInput::make('sort_order')
                                ->label('Thứ tự sắp xếp')
                                ->required()
                                ->numeric()
                                ->default(0),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}



