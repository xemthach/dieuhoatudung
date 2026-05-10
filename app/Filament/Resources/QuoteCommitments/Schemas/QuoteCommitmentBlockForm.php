<?php

namespace App\Filament\Resources\QuoteCommitments\Schemas;

use App\Models\QuoteCommitmentItem;
use App\Services\Media\MediaDiskService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class QuoteCommitmentBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        $iconOptions = collect(QuoteCommitmentItem::iconSvgMap())
            ->mapWithKeys(fn ($v, $k) => [$k => $k])
            ->toArray();

        return $schema
            ->columns(1)
            ->components([

                // ─── Block Info ───
                Section::make('Thông tin block')->schema([
                    TextInput::make('title')
                        ->label('Tiêu đề block')
                        ->required()
                        ->maxLength(200)
                        ->placeholder('Cam kết kỹ thuật & triển khai'),

                    Textarea::make('description')
                        ->label('Mô tả ngắn')
                        ->rows(2)
                        ->maxLength(500),

                    Toggle::make('is_active')
                        ->label('Hiển thị block')
                        ->default(true),
                ]),

                // ─── Items Repeater ───
                Section::make('Danh sách cam kết')->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('title')
                                    ->label('Nội dung')
                                    ->required()
                                    ->maxLength(300)
                                    ->columnSpan(2),

                                TextInput::make('sort_order')
                                    ->label('Thứ tự')
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpan(1),
                            ]),

                            Grid::make(3)->schema([
                                Select::make('icon_type')
                                    ->label('Loại icon')
                                    ->options([
                                        'heroicon' => 'Heroicon',
                                        'image'    => 'Hình ảnh',
                                        'svg'      => 'SVG',
                                    ])
                                    ->default('heroicon')
                                    ->live(),

                                Select::make('icon_name')
                                    ->label('Icon')
                                    ->options($iconOptions)
                                    ->searchable()
                                    ->default('check-circle')
                                    ->visible(fn ($get) => $get('icon_type') === 'heroicon'),

                                Select::make('icon_color')
                                    ->label('Màu icon')
                                    ->options([
                                        'text-green-500'   => 'Xanh lá',
                                        'text-primary-600' => 'Xanh dương',
                                        'text-accent-600'  => 'Cam',
                                        'text-warning-500' => 'Vàng',
                                        'text-surface-500' => 'Xám',
                                    ])
                                    ->default('text-green-500'),
                            ]),

                            FileUpload::make('icon_image')
                                ->label('Upload icon')
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory('quote-commitments/icons')
                                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/webp'])
                                ->maxSize(512)
                                ->visible(fn ($get) => $get('icon_type') === 'image'),

                            Textarea::make('icon_svg')
                                ->label('SVG Code')
                                ->rows(2)
                                ->maxLength(3000)
                                ->visible(fn ($get) => $get('icon_type') === 'svg'),

                            Toggle::make('is_active')
                                ->label('Hiển thị')
                                ->default(true),
                        ])
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->cloneable()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Cam kết mới'),
                ]),
            ]);
    }
}
