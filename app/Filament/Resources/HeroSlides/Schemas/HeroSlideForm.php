<?php

namespace App\Filament\Resources\HeroSlides\Schemas;

use App\Services\Media\MediaDiskService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class HeroSlideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('HeroSlideTabs')->tabs([

                    // ───── TAB A: Content ─────
                    Tabs\Tab::make('Nội dung')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            TextInput::make('title')
                                ->label('Tiêu đề chính')
                                ->maxLength(255)
                                ->placeholder('Điều Hòa Tủ Đứng'),

                            TextInput::make('highlight_text')
                                ->label('Text highlight (màu nhấn)')
                                ->maxLength(255)
                                ->placeholder('Tư vấn theo công trình')
                                ->helperText('Phần text được tô màu nhấn trong tiêu đề.'),

                            TextInput::make('subtitle')
                                ->label('Phụ đề')
                                ->maxLength(500)
                                ->placeholder('Giải pháp làm mát chuyên nghiệp...'),

                            Textarea::make('description')
                                ->label('Mô tả')
                                ->rows(3)
                                ->maxLength(1000),

                            Grid::make(3)->schema([
                                Select::make('text_align')
                                    ->label('Căn chỉnh text')
                                    ->options([
                                        'left'   => 'Trái',
                                        'center' => 'Giữa',
                                        'right'  => 'Phải',
                                    ])
                                    ->default('center'),

                                Select::make('content_position')
                                    ->label('Vị trí nội dung')
                                    ->options([
                                        'left'   => 'Trái',
                                        'center' => 'Giữa',
                                        'right'  => 'Phải',
                                    ])
                                    ->default('center'),

                                ColorPicker::make('text_color')
                                    ->label('Màu chữ')
                                    ->default('#ffffff'),
                            ]),
                        ]),

                    // ───── TAB B: Background ─────
                    Tabs\Tab::make('Background')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Select::make('background_type')
                                ->label('Loại nền')
                                ->options([
                                    'gradient' => 'Gradient',
                                    'color'    => 'Màu đơn',
                                    'image'    => 'Hình ảnh',
                                    'video'    => 'Video',
                                    'embed'    => 'Embed URL',
                                ])
                                ->default('gradient')
                                ->live()
                                ->required(),

                            // Gradient fields
                            Grid::make(2)->schema([
                                ColorPicker::make('gradient_from')
                                    ->label('Gradient From')
                                    ->default('#1e3a5f'),
                                ColorPicker::make('gradient_to')
                                    ->label('Gradient To')
                                    ->default('#0f172a'),
                            ])->visible(fn ($get) => $get('background_type') === 'gradient'),

                            // Color field
                            ColorPicker::make('background_color')
                                ->label('Màu nền')
                                ->visible(fn ($get) => $get('background_type') === 'color'),

                            // Image upload
                            FileUpload::make('background_image')
                                ->label('Hình nền')
                                ->image()
                                ->imageEditor()
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory('hero-slides')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
                                ->maxSize(5120)
                                ->visible(fn ($get) => $get('background_type') === 'image'),

                            // Video upload
                            FileUpload::make('background_video')
                                ->label('Video nền')
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory('hero-slides/videos')
                                ->acceptedFileTypes(['video/mp4', 'video/webm'])
                                ->maxSize(51200)
                                ->visible(fn ($get) => $get('background_type') === 'video'),

                            // Embed URL
                            TextInput::make('embed_url')
                                ->label('Embed URL')
                                ->url()
                                ->maxLength(500)
                                ->placeholder('https://www.youtube.com/embed/...')
                                ->helperText('Chỉ hỗ trợ YouTube, Vimeo.')
                                ->visible(fn ($get) => $get('background_type') === 'embed'),

                            Section::make('Overlay')->schema([
                                Toggle::make('overlay_enabled')
                                    ->label('Bật overlay')
                                    ->default(true),
                                Grid::make(2)->schema([
                                    ColorPicker::make('overlay_color')
                                        ->label('Màu overlay')
                                        ->default('#000000'),
                                    TextInput::make('overlay_opacity')
                                        ->label('Độ mờ overlay (%)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->default(20)
                                        ->suffix('%'),
                                ]),
                            ])->collapsible(),
                        ]),

                    // ───── TAB C: CTA ─────
                    Tabs\Tab::make('CTA')
                        ->icon('heroicon-o-cursor-arrow-rays')
                        ->schema([
                            Section::make('CTA chính')->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('cta_primary_text')
                                        ->label('Text')
                                        ->maxLength(100)
                                        ->placeholder('Nhận báo giá'),
                                    TextInput::make('cta_primary_url')
                                        ->label('URL')
                                        ->maxLength(500)
                                        ->placeholder('/bao-gia'),
                                ]),
                                Select::make('cta_primary_style')
                                    ->label('Style')
                                    ->options([
                                        'accent'  => 'Accent (cam)',
                                        'primary' => 'Primary (xanh)',
                                        'outline' => 'Outline (viền)',
                                    ])
                                    ->default('accent'),
                            ]),

                            Section::make('CTA phụ')->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('cta_secondary_text')
                                        ->label('Text')
                                        ->maxLength(100)
                                        ->placeholder('Xem sản phẩm'),
                                    TextInput::make('cta_secondary_url')
                                        ->label('URL')
                                        ->maxLength(500)
                                        ->placeholder('/san-pham'),
                                ]),
                                Select::make('cta_secondary_style')
                                    ->label('Style')
                                    ->options([
                                        'accent'  => 'Accent (cam)',
                                        'primary' => 'Primary (xanh)',
                                        'outline' => 'Outline (viền)',
                                    ])
                                    ->default('outline'),
                            ]),

                            Toggle::make('open_in_new_tab')
                                ->label('Mở link trong tab mới')
                                ->default(false),
                        ]),

                    // ───── TAB D: Animation ─────
                    Tabs\Tab::make('Hiệu ứng')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Select::make('animation_type')
                                ->label('Hiệu ứng chữ')
                                ->options([
                                    'fade'       => 'Fade In',
                                    'slide-up'   => 'Slide Up',
                                    'slide-left' => 'Slide Left',
                                    'zoom-in'    => 'Zoom In',
                                    'none'       => 'Không hiệu ứng',
                                ])
                                ->default('fade'),

                            TextInput::make('duration_ms')
                                ->label('Thời gian hiển thị slide (ms)')
                                ->numeric()
                                ->minValue(2000)
                                ->maxValue(30000)
                                ->default(6000)
                                ->suffix('ms')
                                ->helperText('Mặc định 6000ms = 6 giây'),
                        ]),

                    // ───── TAB E: Status ─────
                    Tabs\Tab::make('Trạng thái')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Toggle::make('is_active')
                                ->label('Hiển thị')
                                ->default(true),

                            TextInput::make('sort_order')
                                ->label('Thứ tự sắp xếp')
                                ->numeric()
                                ->default(0)
                                ->helperText('Số nhỏ hơn hiển thị trước.'),
                        ]),

                ])->columnSpanFull(),
            ]);
    }
}
