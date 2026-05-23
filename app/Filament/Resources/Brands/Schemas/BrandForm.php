<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Filament\Resources\Shared\Forms\AdminContentAutomation;
use App\Services\Media\MediaDiskService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        AdminContentAutomation::slugTracker('name'),
                        Section::make('Thông tin chung')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                AdminContentAutomation::titleField('name', 'Tên thương hiệu'),
                                AdminContentAutomation::slugField('brands'),
                            ]),
                            AdminContentAutomation::aiAction(
                                module: 'brand',
                                inputFields: [
                                    'title' => 'name',
                                ],
                                fieldMap: [
                                    'short_description' => 'description',
                                    'detailed_content' => 'content',
                                    'brand_introduction' => 'description',
                                    'meta_description' => 'seo_description',
                                ],
                                fieldOptions: [
                                    'brand_introduction' => 'Brand introduction',
                                    'detailed_content' => 'Detailed content',
                                    'seo_title' => 'SEO title',
                                    'meta_description' => 'Meta description',
                                    'og_title' => 'OG title',
                                    'og_description' => 'OG description',
                                ],
                            ),
                            Textarea::make('description')
                                ->label('Giới thiệu ngắn')
                                ->rows(3)
                                ->columnSpanFull(),
                            Textarea::make('content')
                                ->label('Nội dung chi tiết')
                                ->rows(5)
                                ->columnSpanFull(),
                        ]),

                        Section::make('Cấu hình SEO')->schema([
                            TextInput::make('seo_title')->label('Tiêu đề SEO'),
                            TextInput::make('seo_description')->label('Meta description'),
                            TextInput::make('og_title')->label('OG title'),
                            TextInput::make('og_description')->label('OG description'),
                            TextInput::make('canonical_url')->label('Canonical URL')->url(),
                            TextInput::make('robots')
                                ->label('Robots')
                                ->required()
                                ->default('index,follow'),
                        ])->collapsed(),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Logo & nhận diện')->schema([
                            FileUpload::make('logo')
                                ->label('Logo thương hiệu')
                                ->image()
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory('brands'),
                        ]),

                        Section::make('Trạng thái')->schema([
                            Toggle::make('is_active')
                                ->label('Kích hoạt')
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
