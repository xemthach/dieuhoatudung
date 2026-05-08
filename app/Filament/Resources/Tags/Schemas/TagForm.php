<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Enums\TagStatus;
use App\Enums\TagType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Section::make('Thông tin chung')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('name')
                                    ->label('Tên thẻ (Tag)')
                                    ->required(),
                                TextInput::make('slug')
                                    ->label('Đường dẫn (Slug)')
                                    ->required(),
                            ]),
                            Select::make('type')
                                ->label('Loại Tag')
                                ->options(TagType::class)
                                ->default('topic')
                                ->required(),
                            Textarea::make('intro')
                                ->label('Giới thiệu ngắn')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                        
                        Section::make('Cấu hình SEO')->schema([
                            TextInput::make('seo_title')->label('Tiêu đề SEO'),
                            TextInput::make('seo_description')->label('Mô tả SEO'),
                            TextInput::make('canonical_url')->label('Canonical URL')->url(),
                            TextInput::make('robots')
                                ->label('Robots')
                                ->required()
                                ->default('noindex,follow'),
                        ])->collapsed(),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Cấu hình hệ thống')->schema([
                            Select::make('status')
                                ->label('Trạng thái')
                                ->options(TagStatus::class)
                                ->default('candidate')
                                ->required(),
                            Toggle::make('is_indexable')
                                ->label('Cho phép Index')
                                ->default(false)
                                ->required(),
                            TextInput::make('min_content_required')
                                ->label('Số lượng bài tối thiểu')
                                ->required()
                                ->numeric()
                                ->default(5),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}



