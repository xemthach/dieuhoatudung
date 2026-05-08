<?php

namespace App\Filament\Resources\AiContentJobs\Schemas;

use App\Enums\AIContentJobStatus;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AiContentJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Thông tin yêu cầu')
                    ->description('Điền chủ đề và từ khóa để Gemini AI tạo bài viết.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('topic')
                                ->label('Chủ đề bài viết')
                                ->placeholder('vd: Điều hòa tủ đứng Daikin 36000 BTU có tốt không?')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            TextInput::make('primary_keyword')
                                ->label('Từ khóa chính')
                                ->placeholder('vd: điều hòa tủ đứng Daikin')
                                ->helperText('Từ khóa SEO chính cần tối ưu.'),
                            Select::make('intent')
                                ->label('Search Intent')
                                ->options([
                                    'informational'  => 'Informational (thông tin, kiến thức)',
                                    'commercial'     => 'Commercial (so sánh, đánh giá)',
                                    'transactional'  => 'Transactional (mua hàng, giá cả)',
                                ])
                                ->default('informational')
                                ->required(),
                            Select::make('post_category_id')
                                ->label('Danh mục blog')
                                ->relationship('postCategory', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('status')
                                ->label('Trạng thái')
                                ->options(AIContentJobStatus::class)
                                ->default('pending')
                                ->required()
                                ->disabled(),
                        ]),
                    ]),

                Section::make('Kết quả AI (chỉ đọc)')
                    ->description('Nội dung được Gemini AI tạo ra. Xem và duyệt trước khi publish.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('output_outline')
                            ->label('Outline bài viết')
                            ->rows(12)
                            ->columnSpanFull()
                            ->disabled(),
                        RichEditor::make('output_draft')
                            ->label('Draft bài viết (HTML)')
                            ->columnSpanFull()
                            ->disabled(),
                        Textarea::make('error_message')
                            ->label('Lỗi (nếu có)')
                            ->columnSpanFull()
                            ->disabled()
                            ->visible(fn ($record) => !empty($record?->error_message)),
                    ]),
            ]);
    }
}


