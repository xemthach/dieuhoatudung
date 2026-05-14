<?php

namespace App\Filament\Resources\HomeBenefitItems\Schemas;

use App\Models\HomeBenefitItem;
use App\Services\Media\MediaDiskService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class HomeBenefitItemForm
{
    public static function configure(Schema $schema): Schema
    {
        $iconOptions = collect(HomeBenefitItem::iconSvgMap())->mapWithKeys(fn ($v, $k) => [$k => $k])->toArray();

        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([

                        // ─── Content ───
                        Section::make('Nội dung')->schema([
                            TextInput::make('title')
                                ->label('Tiêu đề')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('Dữ liệu sản phẩm rõ ràng'),

                            TextInput::make('subtitle')
                                ->label('Phụ đề')
                                ->maxLength(200)
                                ->placeholder('Nhập khẩu trực tiếp'),
                        ]),

                        // ─── Icon ───
                        Section::make('Icon')->schema([
                            Select::make('icon_type')
                                ->label('Loại icon')
                                ->options([
                                    'heroicon' => 'Heroicon / Lucide',
                                    'image'    => 'Hình ảnh upload',
                                    'svg'      => 'SVG tùy chỉnh',
                                ])
                                ->default('heroicon')
                                ->required()
                                ->live(),

                            Select::make('icon_name')
                                ->label('Chọn icon')
                                ->options($iconOptions)
                                ->searchable()
                                ->visible(fn ($get) => $get('icon_type') === 'heroicon')
                                ->helperText('Chọn từ danh sách icon có sẵn.'),

                            FileUpload::make('icon_image')
                                ->label('Upload icon')
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory('benefits/icons')
                                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/webp'])
                                ->maxSize(512)
                                ->visible(fn ($get) => $get('icon_type') === 'image'),

                            Textarea::make('icon_svg')
                                ->label('SVG Code')
                                ->rows(4)
                                ->maxLength(5000)
                                ->helperText('Dán SVG code. Script/event handler sẽ bị loại bỏ tự động.')
                                ->visible(fn ($get) => $get('icon_type') === 'svg'),
                        ]),

                    ])->columnSpan(2),

                    Group::make()->schema([

                        // ─── Style ───
                        Section::make('Style')->schema([
                            Select::make('icon_color')
                                ->label('Màu icon')
                                ->options([
                                    'text-primary-600' => 'Primary (xanh)',
                                    'text-accent-600'  => 'Accent (cam)',
                                    'text-success-600' => 'Success (xanh lá)',
                                    'text-warning-600' => 'Warning (vàng)',
                                    'text-danger-600'  => 'Danger (đỏ)',
                                    'text-surface-600' => 'Neutral (xám)',
                                ])
                                ->default('text-primary-600'),

                            Select::make('bg_color')
                                ->label('Màu nền icon')
                                ->options([
                                    'bg-primary-100'    => 'Primary nhạt',
                                    'bg-accent-100'     => 'Accent nhạt',
                                    'bg-success-500/10' => 'Success nhạt',
                                    'bg-warning-500/10' => 'Warning nhạt',
                                    'bg-danger-500/10'  => 'Danger nhạt',
                                    'bg-surface-100'    => 'Neutral nhạt',
                                ])
                                ->default('bg-primary-100'),
                        ]),

                        // ─── Status ───
                        Section::make('Trạng thái')->schema([
                            Toggle::make('is_active')
                                ->label('Hiển thị')
                                ->default(true),

                            TextInput::make('sort_order')
                                ->label('Thứ tự')
                                ->numeric()
                                ->default(0)
                                ->helperText('Số nhỏ hiển thị trước.'),
                        ]),

                    ])->columnSpan(1),
                ]),
            ]);
    }
}
