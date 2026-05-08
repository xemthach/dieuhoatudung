<?php

namespace App\Filament\Resources\ProductReviews\Schemas;

use App\Services\Media\MediaDiskService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class ProductReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        $mediaDisk = app(MediaDiskService::class);
        $mediaDisk->configureR2Disk();
        $disk = $mediaDisk->getUploadDisk();

        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Section::make('Thông tin đánh giá')->schema([
                            Select::make('product_id')
                                ->label('Sản phẩm')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('customer_name')
                                    ->label('Tên khách hàng')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('customer_phone')
                                    ->label('Số điện thoại')
                                    ->maxLength(20),
                                TextInput::make('customer_email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                Select::make('rating')
                                    ->label('Đánh giá (Sao)')
                                    ->options([
                                        5 => '5 Sao',
                                        4 => '4 Sao',
                                        3 => '3 Sao',
                                        2 => '2 Sao',
                                        1 => '1 Sao',
                                    ])
                                    ->required()
                                    ->default(5),
                            ]),
                            TextInput::make('title')
                                ->label('Tiêu đề')
                                ->maxLength(255),
                            Textarea::make('content')
                                ->label('Nội dung đánh giá')
                                ->required()
                                ->rows(5)
                                ->columnSpanFull(),
                        ]),
                        Section::make('Phản hồi của Admin')->schema([
                            Textarea::make('admin_reply')
                                ->label('Trả lời')
                                ->rows(4),
                        ]),
                    ])->columnSpan(2),

                    Group::make()->schema([
                        Section::make('Trạng thái')->schema([
                            Select::make('status')
                                ->label('Trạng thái')
                                ->options([
                                    'pending'  => 'Chờ duyệt',
                                    'approved' => 'Đã duyệt',
                                    'rejected' => 'Từ chối',
                                ])
                                ->required()
                                ->default('pending'),
                            Toggle::make('is_verified_purchase')
                                ->label('Xác nhận mua hàng')
                                ->default(false),
                        ]),
                        Section::make('Hình ảnh')->schema([
                            FileUpload::make('images_json')
                                ->label('Hình ảnh đánh giá')
                                ->disk($disk)
                                ->directory('reviews')
                                ->image()
                                ->multiple()
                                ->reorderable()
                                ->maxFiles(5)
                                ->maxSize(fn () => app(\App\Services\Settings\UploadSettingService::class)->reviewImageMaxSizeKb())
                                ->acceptedFileTypes(fn () => app(\App\Services\Settings\UploadSettingService::class)->allowedImageTypes())
                                ->helperText(fn () => 'Tối đa ' . app(\App\Services\Settings\UploadSettingService::class)->formatMb(app(\App\Services\Settings\UploadSettingService::class)->reviewImageMaxSizeKb()) . ' mỗi ảnh.'),
                        ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }
}
