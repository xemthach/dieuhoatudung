<?php

namespace App\Filament\Resources\AiContentJobs\Schemas;

use App\Enums\AIContentJobStatus;
use App\Models\Brand;
use App\Models\Product;
use App\Services\AI\HVACSeoContentEngine;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiContentJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Thông tin yêu cầu')
                    ->description('AI có thể tự tạo topic, keyword và intent khi bạn để trống.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Select::make('input_payload.category')
                                ->label('Category nội dung')
                                ->options(array_combine(HVACSeoContentEngine::CATEGORIES, HVACSeoContentEngine::CATEGORIES))
                                ->default('Kiến thức HVAC')
                                ->required(),
                            Select::make('intent')
                                ->label('Search intent')
                                ->options([
                                    'informational' => 'Informational',
                                    'commercial' => 'Commercial',
                                ])
                                ->placeholder('AI tự suy luận nếu để trống'),
                            TextInput::make('topic')
                                ->label('Chủ đề bài viết')
                                ->placeholder('Để trống để AI tự tạo topic theo category')
                                ->maxLength(255)
                                ->columnSpanFull(),
                            TextInput::make('primary_keyword')
                                ->label('Từ khóa chính')
                                ->placeholder('Để trống để AI tự tạo keyword SEO hợp lý'),
                            Select::make('input_payload.audience')
                                ->label('Đối tượng')
                                ->options(array_combine(HVACSeoContentEngine::AUDIENCES, HVACSeoContentEngine::AUDIENCES))
                                ->placeholder('AI tự suy luận nếu để trống'),
                            Select::make('input_payload.product_id')
                                ->label('Product liên quan')
                                ->options(fn () => Product::query()->orderBy('name')->limit(200)->pluck('name', 'id'))
                                ->searchable()
                                ->preload(),
                            Select::make('input_payload.brand_id')
                                ->label('Brand liên quan')
                                ->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload(),
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

                Section::make('Kết quả AI')
                    ->description('Kết quả JSON được tách ra thành draft, meta, FAQ, tags và internal links.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('output_outline')
                            ->label('Title / slug / excerpt')
                            ->rows(6)
                            ->columnSpanFull()
                            ->disabled(),
                        RichEditor::make('output_draft')
                            ->label('Nội dung chi tiết HTML')
                            ->columnSpanFull()
                            ->disabled(),
                        Textarea::make('error_message')
                            ->label('Lỗi nếu có')
                            ->columnSpanFull()
                            ->disabled()
                            ->visible(fn ($record) => ! empty($record?->error_message)),
                    ]),

                Section::make('Technical debug')
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextInput::make('module')->disabled(),
                            TextInput::make('provider')->disabled(),
                            TextInput::make('model')->disabled(),
                            TextInput::make('queue_name')->disabled(),
                            TextInput::make('attempts')->disabled(),
                            TextInput::make('retry_count')->disabled(),
                            TextInput::make('failed_reason')->disabled(),
                            TextInput::make('last_error_code')->disabled(),
                            TextInput::make('duration_ms')->disabled(),
                            TextInput::make('exception_class')->disabled(),
                            TextInput::make('exception_file')->disabled()->columnSpan(2),
                            TextInput::make('exception_line')->disabled(),
                        ]),
                        Textarea::make('last_error_message')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('stack_trace')
                            ->rows(8)
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('raw_response_summary')
                            ->rows(6)
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('validation_errors')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $state)
                            ->rows(6)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
