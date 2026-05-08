<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\StockStatus;
use App\Filament\Traits\HasSEOFields;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use App\Services\Product\ProductAIContentService;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Tabs::make('Product Tabs')
                            ->tabs([
                                Tabs\Tab::make('Thông tin cơ bản')
                                    ->schema([
                                        Grid::make(['default' => 1, 'md' => 2])
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Tên sản phẩm')
                                                    ->required()
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state))),
                                                TextInput::make('slug')
                                                    ->label('Đường dẫn (Slug)')
                                                    ->required()
                                                    ->unique(ignoreRecord: true),
                                                TextInput::make('sku')
                                                    ->label('Mã SKU')
                                                    ->unique(ignoreRecord: true),
                                                TextInput::make('model_code')
                                                    ->label('Mã Model'),
                                            ]),

                                        Section::make('Giá và Tồn kho')
                                            ->schema([
                                                Grid::make(['default' => 1, 'md' => 3])
                                                    ->schema([
                                                        TextInput::make('regular_price')
                                                            ->label('Giá gốc')
                                                            ->numeric()
                                                            ->suffix('VNĐ'),
                                                        TextInput::make('sale_price')
                                                            ->label('Giá khuyến mãi')
                                                            ->numeric()
                                                            ->suffix('VNĐ'),
                                                        TextInput::make('discount_percent')
                                                            ->label('Phần trăm giảm (%)')
                                                            ->numeric(),
                                                    ]),
                                                Grid::make(['default' => 1, 'md' => 3])
                                                    ->schema([
                                                        Select::make('stock_status')
                                                            ->label('Trạng thái tồn kho')
                                                            ->options(StockStatus::class)
                                                            ->default('in_stock')
                                                            ->required(),
                                                        DateTimePicker::make('promotion_start_at')
                                                            ->label('Bắt đầu KM'),
                                                        DateTimePicker::make('promotion_end_at')
                                                            ->label('Kết thúc KM'),
                                                    ]),
                                            ]),
                                    ]),

                                Tabs\Tab::make('Thông số kỹ thuật')
                                    ->schema([
                                        Grid::make(['default' => 1, 'md' => 3])
                                            ->schema([
                                                TextInput::make('btu')
                                                    ->label('Công suất BTU')
                                                    ->numeric(),
                                                Toggle::make('inverter')
                                                    ->label('Công nghệ Inverter')
                                                    ->default(false)
                                                    ->required(),
                                                Select::make('cooling_type')
                                                    ->label('Kiểu làm lạnh')
                                                    ->options([
                                                        '1_chieu' => '1 chiều',
                                                        '2_chieu' => '2 chiều',
                                                    ]),
                                                TextInput::make('voltage')
                                                    ->label('Điện áp'),
                                                TextInput::make('refrigerant_gas')
                                                    ->label('Loại Gas'),
                                                TextInput::make('power_consumption')
                                                    ->label('Điện năng tiêu thụ'),
                                                TextInput::make('airflow')
                                                    ->label('Lưu lượng gió'),
                                                TextInput::make('noise_level')
                                                    ->label('Độ ồn'),
                                                TextInput::make('recommended_area')
                                                    ->label('Diện tích đề nghị'),
                                            ]),
                                        Grid::make(['default' => 1, 'md' => 3])
                                            ->schema([
                                                TextInput::make('indoor_dimensions')
                                                    ->label('Kích thước dàn lạnh'),
                                                TextInput::make('outdoor_dimensions')
                                                    ->label('Kích thước dàn nóng'),
                                                TextInput::make('weight')
                                                    ->label('Trọng lượng'),
                                            ]),
                                        
                                        Repeater::make('specs_json')
                                            ->label('Thông số kỹ thuật mở rộng (JSON)')
                                            ->schema([
                                                TextInput::make('key')->label('Tên thông số')->required(),
                                                TextInput::make('value')->label('Giá trị')->required(),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0),
                                    ]),

                                Tabs\Tab::make('Nội dung')
                                    ->schema([
                                        Actions::make([
                                            Action::make('generate_content')
                                                ->label('Generate nội dung bằng AI')
                                                ->icon('heroicon-o-sparkles')
                                                ->color('primary')
                                                ->form([
                                                    Toggle::make('generate_short_description')->label('Mô tả ngắn')->default(true),
                                                    Toggle::make('generate_long_description')->label('Mô tả chi tiết')->default(true),
                                                    Toggle::make('generate_warranty_info')->label('Thông tin bảo hành')->default(false),
                                                    Toggle::make('generate_installation_note')->label('Lưu ý lắp đặt')->default(false),
                                                    Toggle::make('generate_faq')->label('FAQ Suggestions')->default(true),
                                                    Toggle::make('generate_tags')->label('Tag Suggestions')->default(true),
                                                    Toggle::make('overwrite')->label('Ghi đè nội dung đã có')->default(false)->onColor('danger'),
                                                ])
                                                ->action(function (array $data, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                                    try {
                                                        $service = app(ProductAIContentService::class);
                                                        
                                                        // Collect current product state
                                                        $productData = [
                                                            'name' => $get('name'),
                                                            'model_code' => $get('model_code'),
                                                            'brand' => ['name' => \App\Models\Brand::find($get('brand_id'))?->name],
                                                            'btu' => $get('btu'),
                                                            'inverter' => $get('inverter'),
                                                            'cooling_type' => $get('cooling_type'),
                                                            'refrigerant_gas' => $get('refrigerant_gas'),
                                                            'voltage' => $get('voltage'),
                                                        ];
                                                        
                                                        $result = $service->generateContent($productData, $data, auth()->id());
                                                        
                                                        if (!empty($result['short_description']) && ($data['overwrite'] || empty($get('short_description')))) {
                                                            $set('short_description', $result['short_description']);
                                                        }
                                                        if (!empty($result['long_description']) && ($data['overwrite'] || empty($get('long_description')))) {
                                                            $set('long_description', $result['long_description']);
                                                        }
                                                        if (!empty($result['warranty_info']) && ($data['overwrite'] || empty($get('warranty_info')))) {
                                                            $set('warranty_info', $result['warranty_info']);
                                                        }
                                                        if (!empty($result['installation_note']) && ($data['overwrite'] || empty($get('installation_note')))) {
                                                            $set('installation_note', $result['installation_note']);
                                                        }
                                                        
                                                        Notification::make()->title('Đã tạo nội dung AI thành công!')->success()->send();
                                                    } catch (\Exception $e) {
                                                        Notification::make()->title('Lỗi: ' . $e->getMessage())->danger()->send();
                                                    }
                                                })
                                        ]),
                                        Textarea::make('short_description')
                                            ->label('Mô tả ngắn')
                                            ->rows(3),
                                        RichEditor::make('long_description')
                                            ->label('Mô tả chi tiết')
                                            ->fileAttachmentsDisk(config('media.disk'))
                                            ->fileAttachmentsDirectory(config('media.folders.products')),
                                        RichEditor::make('warranty_info')
                                            ->label('Thông tin bảo hành'),
                                        RichEditor::make('installation_note')
                                            ->label('Lưu ý lắp đặt'),
                                    ]),

                                Tabs\Tab::make('Media & Hình ảnh')
                                    ->schema([
                                        FileUpload::make('main_image')
                                            ->label('Hình ảnh chính')
                                            ->image()
                                            
                                            ->directory(config('media.folders.products'))
                                            ->imageEditor()
                                            ->maxSize(fn () => app(\App\Services\Settings\UploadSettingService::class)->productImageMaxSizeKb())
                                            ->acceptedFileTypes(fn () => app(\App\Services\Settings\UploadSettingService::class)->allowedImageTypes()),
                                        FileUpload::make('gallery_json')
                                            ->label('Thư viện hình ảnh')
                                            ->image()
                                            ->multiple()
                                            
                                            ->directory(config('media.folders.products_gallery'))
                                            ->imageEditor()
                                            ->reorderable()
                                            ->maxSize(fn () => app(\App\Services\Settings\UploadSettingService::class)->productImageMaxSizeKb())
                                            ->maxFiles(fn () => app(\App\Services\Settings\UploadSettingService::class)->maxImagesPerUpload())
                                            ->acceptedFileTypes(fn () => app(\App\Services\Settings\UploadSettingService::class)->allowedImageTypes()),
                                        TextInput::make('video_url')
                                            ->label('Video YouTube URL')
                                            ->url(),
                                        FileUpload::make('documents_json')
                                            ->label('Tài liệu kỹ thuật')
                                            ->multiple()
                                            ->acceptedFileTypes(['application/pdf'])
                                            
                                            ->directory('media/documents'),
                                    ]),

                                Tabs\Tab::make('SEO')
                                    ->schema([
                                        Actions::make([
                                            Action::make('generate_seo')
                                                ->label('Generate SEO bằng AI')
                                                ->icon('heroicon-o-sparkles')
                                                ->color('success')
                                                ->form([
                                                    Toggle::make('overwrite')->label('Ghi đè SEO hiện tại')->default(false)->onColor('danger'),
                                                ])
                                                ->action(function (array $data, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                                    try {
                                                        $service = app(ProductAIContentService::class);
                                                        
                                                        $productData = [
                                                            'name' => $get('name'),
                                                            'model_code' => $get('model_code'),
                                                            'brand' => ['name' => \App\Models\Brand::find($get('brand_id'))?->name],
                                                            'short_description' => $get('short_description'),
                                                        ];
                                                        
                                                        $result = $service->generateSeo($productData, auth()->id());
                                                        
                                                        if (!empty($result['seo_title']) && ($data['overwrite'] || empty($get('seo_title')))) {
                                                            $set('seo_title', $result['seo_title']);
                                                        }
                                                        if (!empty($result['seo_description']) && ($data['overwrite'] || empty($get('seo_description')))) {
                                                            $set('seo_description', $result['seo_description']);
                                                        }
                                                        if (!empty($result['og_title']) && ($data['overwrite'] || empty($get('og_title')))) {
                                                            $set('og_title', $result['og_title']);
                                                        }
                                                        if (!empty($result['og_description']) && ($data['overwrite'] || empty($get('og_description')))) {
                                                            $set('og_description', $result['og_description']);
                                                        }
                                                        
                                                        Notification::make()->title('Đã tạo SEO AI thành công!')->success()->send();
                                                    } catch (\Exception $e) {
                                                        Notification::make()->title('Lỗi: ' . $e->getMessage())->danger()->send();
                                                    }
                                                })
                                        ]),
                                        HasSEOFields::getSEOFields(),
                                        Section::make('Open Graph Settings')
                                            ->schema([
                                                TextInput::make('og_title')
                                                    ->label('OG Title'),
                                                TextInput::make('og_description')
                                                    ->label('OG Description'),
                                                FileUpload::make('og_image')
                                                    ->label('OG Image')
                                                    ->image()
                                                    
                                                    ->directory('og'),
                                                Toggle::make('schema_enabled')
                                                    ->label('Bật Schema.org cho trang này')
                                                    ->default(true)
                                                    ->required(),
                                            ])->collapsed(),
                                    ]),

                                Tabs\Tab::make('Google Merchant')
                                    ->icon('heroicon-o-shopping-cart')
                                    ->schema([
                                        Section::make('Merchant Center Fields')
                                            ->description('Trường bắt buộc / khuyến nghị để sản phẩm đủ điều kiện chạy Google Shopping.')
                                            ->schema([
                                                Grid::make(['default' => 1, 'md' => 3])
                                                    ->schema([
                                                        Select::make('condition')
                                                            ->label('Tình trạng')
                                                            ->options([
                                                                'new' => 'Mới (new)',
                                                                'refurbished' => 'Tân trang (refurbished)',
                                                                'used' => 'Đã qua sử dụng (used)',
                                                            ])
                                                            ->default('new')
                                                            ->helperText('Google yêu cầu bắt buộc'),
                                                        TextInput::make('gtin')
                                                            ->label('GTIN / EAN / UPC')
                                                            ->helperText('Mã vạch sản phẩm (nếu có)'),
                                                        Toggle::make('identifier_exists')
                                                            ->label('Có mã định danh (GTIN/MPN)')
                                                            ->helperText('Bật nếu SP có GTIN hoặc MPN chính hãng')
                                                            ->default(false),
                                                    ]),
                                                Grid::make(['default' => 1, 'md' => 2])
                                                    ->schema([
                                                        TextInput::make('google_product_category')
                                                            ->label('Google Product Category')
                                                            ->placeholder('604')
                                                            ->helperText('ID danh mục Google (mặc định 604 = HVAC)'),
                                                        TextInput::make('product_type')
                                                            ->label('Product Type')
                                                            ->placeholder('Điều Hòa Tủ Đứng > Daikin')
                                                            ->helperText('Đường dẫn danh mục tùy chỉnh'),
                                                    ]),
                                                Grid::make(['default' => 1, 'md' => 2])
                                                    ->schema([
                                                        TextInput::make('shipping_weight')
                                                            ->label('Cân nặng vận chuyển')
                                                            ->placeholder('50 kg'),
                                                        TextInput::make('shipping_label')
                                                            ->label('Shipping Label')
                                                            ->helperText('Nhóm phí vận chuyển'),
                                                    ]),
                                            ]),
                                        Section::make('Custom Labels')
                                            ->description('Dùng để phân nhóm sản phẩm trong Google Ads campaigns.')
                                            ->schema([
                                                Grid::make(['default' => 1, 'md' => 3])
                                                    ->schema([
                                                        TextInput::make('custom_label_0')->label('Custom Label 0')->placeholder('vd: bestseller'),
                                                        TextInput::make('custom_label_1')->label('Custom Label 1')->placeholder('vd: high-margin'),
                                                        TextInput::make('custom_label_2')->label('Custom Label 2')->placeholder('vd: summer-sale'),
                                                    ]),
                                                Grid::make(['default' => 1, 'md' => 2])
                                                    ->schema([
                                                        TextInput::make('custom_label_3')->label('Custom Label 3'),
                                                        TextInput::make('custom_label_4')->label('Custom Label 4'),
                                                    ]),
                                            ])->collapsible()->collapsed(),
                                    ]),
                            ])
                            ->columnSpanFull()
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Phân loại')->schema([
                            Select::make('brand_id')
                                ->label('Thương hiệu')
                                ->relationship('brand', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('product_category_id')
                                ->label('Danh mục sản phẩm')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload(),
                        ]),
                        Section::make('Trạng thái')->schema([
                            Toggle::make('is_active')
                                ->label('Hiển thị')
                                ->default(true)
                                ->required(),
                            Toggle::make('is_featured')
                                ->label('Nổi bật')
                                ->default(false)
                                ->required(),
                            Toggle::make('is_bestseller')
                                ->label('Bán chạy')
                                ->default(false)
                                ->required(),
                            Toggle::make('is_new')
                                ->label('Mới')
                                ->default(false)
                                ->required(),
                            TextInput::make('sort_order')
                                ->label('Thứ tự sắp xếp')
                                ->numeric()
                                ->default(0),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}



