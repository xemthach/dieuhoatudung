<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\AIReviewStatus;
use App\Enums\PostStatus;
use App\Enums\SearchIntent;
use App\Filament\Traits\HasSEOFields;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use App\Services\Media\MediaDiskService;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Section::make('Nội dung chính')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('title')
                                    ->label('Tiêu đề')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->label('Đường dẫn')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),
                            Textarea::make('excerpt')
                                ->label('Mô tả ngắn (Excerpt)')
                                ->rows(3)
                                ->columnSpanFull(),
                            RichEditor::make('content')
                                ->label('Nội dung bài viết')
                                ->fileAttachmentsDisk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->fileAttachmentsDirectory(config('media.folders.blog'))
                                ->columnSpanFull(),
                        ]),
                        
                        Section::make('Hình ảnh / Media')->schema([
                            FileUpload::make('cover_image')
                                ->label('Ảnh đại diện')
                                ->image()
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory(config('media.folders.blog'))
                                ->columnSpanFull(),
                        ])->columns(2),

                        Section::make('AI & SEO Content')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('primary_keyword')
                                    ->label('Từ khoá chính'),
                                Select::make('search_intent')
                                    ->label('Search Intent')
                                    ->options(SearchIntent::class),
                            ]),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Toggle::make('ai_generated')
                                    ->label('Được tạo bởi AI')
                                    ->default(false),
                                Select::make('ai_review_status')
                                    ->label('Trạng thái duyệt AI')
                                    ->options(AIReviewStatus::class)
                                    ->default('none'),
                            ]),
                        ])->collapsed(),

                        Section::make('SEO')->schema([
                            HasSEOFields::getSEOFields(),
                            Section::make('Open Graph Settings')->schema([
                                TextInput::make('og_title')
                                    ->label('OG Title'),
                                TextInput::make('og_description')
                                    ->label('OG Description'),
                                FileUpload::make('og_image')
                                    ->label('OG Image')
                                    ->image()
                                    ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                    ->directory('og'),
                                Toggle::make('schema_enabled')
                                    ->label('Bật Schema.org cho trang này')
                                    ->default(true)
                                    ->required(),
                            ])->collapsed(),
                        ])->columns(2)->collapsible(),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Trạng thái & Phân loại')->schema([
                            Select::make('status')
                                ->label('Trạng thái')
                                ->options(PostStatus::class)
                                ->default('draft')
                                ->required(),
                            DateTimePicker::make('published_at')
                                ->label('Ngày xuất bản'),
                            Select::make('post_category_id')
                                ->label('Danh mục')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('author_id')
                                ->label('Tác giả')
                                ->relationship('author', 'name')
                                ->searchable()
                                ->preload(),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}


