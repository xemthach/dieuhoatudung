<?php

namespace App\Filament\Pages;

use App\Models\Brand;
use App\Models\DataImportJob;
use App\Models\ProductCategory;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ModuleRegistry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportPreviewPage extends Page
{
    protected string $view = 'filament.pages.import-preview';
    protected static bool $shouldRegisterNavigation = false;

    public ?int $jobId = null;
    public ?DataImportJob $job = null;

    /**
     * Lookup maps for resolving foreign-key IDs to human-readable names.
     * Pre-loaded once in mount() to avoid N+1 queries in the Blade template.
     */
    public array $brandMap = [];
    public array $categoryMap = [];

    /**
     * Human-readable column labels for the preview table.
     * DB field names → clean display labels.
     */
    public array $columnLabels = [];

    /**
     * Columns to display in the preview table (curated subset).
     */
    public array $displayColumns = [];

    public function mount(): void
    {
        $this->jobId = request()->query('job');

        if (!$this->jobId) {
            $this->redirect(DataTransferPage::getUrl());
            return;
        }

        $this->job = DataImportJob::find($this->jobId);

        if (!$this->job || $this->job->status !== 'previewing') {
            Notification::make()
                ->warning()
                ->title('Import job không hợp lệ hoặc đã được xử lý.')
                ->send();
            $this->redirect(DataTransferPage::getUrl());
            return;
        }

        // Pre-load lookup maps for the module
        $this->loadLookupMaps();

        // Configure display columns
        $this->configureDisplayColumns();
    }

    /**
     * Pre-load brand/category maps for resolving IDs → names.
     */
    protected function loadLookupMaps(): void
    {
        if ($this->job->module === 'product') {
            $this->brandMap = Brand::pluck('name', 'id')->all();
            $this->categoryMap = ProductCategory::pluck('name', 'id')->all();
        }
    }

    /**
     * Select which columns to show and in what order, with human-readable labels.
     */
    protected function configureDisplayColumns(): void
    {
        $allColumns = $this->job->column_mapping_json ?? [];

        // Column label map: db_field → Display Label
        $labelMap = [
            // Basic info
            'id'                      => 'ID',
            'name'                    => 'Tên sản phẩm',
            'slug'                    => 'Slug',
            'sku'                     => 'SKU',
            'model_code'              => 'Model',
            'brand_id'                => 'Thương hiệu',
            'product_category_id'     => 'Danh mục',
            'series'                  => 'Series',
            'short_description'       => 'Mô tả ngắn',
            'long_description'        => 'Mô tả chi tiết',
            'is_active'               => 'Hoạt động',
            'is_featured'             => 'Nổi bật',
            'is_bestseller'           => 'Bán chạy',
            'is_new'                  => 'Mới',
            'sort_order'              => 'Thứ tự',

            // Pricing
            'regular_price'           => 'Giá gốc',
            'sale_price'              => 'Giá sale',
            'discount_percent'        => 'Giảm giá (%)',
            'promotion_start_at'      => 'KM bắt đầu',
            'promotion_end_at'        => 'KM kết thúc',
            'stock_status'            => 'Tồn kho',

            // Specs
            'btu'                     => 'BTU',
            'inverter'                => 'Inverter',
            'cooling_type'            => 'Loại làm lạnh',
            'voltage'                 => 'Điện áp',
            'refrigerant_gas'         => 'Gas lạnh',
            'power_consumption'       => 'Công suất',
            'airflow'                 => 'Lưu lượng gió',
            'noise_level'             => 'Độ ồn',
            'indoor_dimensions'       => 'KT dàn lạnh',
            'outdoor_dimensions'      => 'KT dàn nóng',
            'weight'                  => 'Trọng lượng',
            'recommended_area'        => 'Diện tích',
            'warranty_info'           => 'Bảo hành',
            'installation_note'       => 'Lắp đặt',
            'specs_json'              => 'Thông số (JSON)',

            // SEO
            'seo_title'               => 'SEO Title',
            'seo_description'         => 'SEO Description',
            'canonical_url'           => 'Canonical URL',
            'robots'                  => 'Robots',
            'og_title'                => 'OG Title',
            'og_description'          => 'OG Description',
            'og_image'                => 'OG Image',
            'schema_enabled'          => 'Schema',

            // Media
            'main_image'              => 'Ảnh chính',
            'gallery_json'            => 'Gallery (JSON)',
            'video_url'               => 'Video URL',
            'documents_json'          => 'Tài liệu (JSON)',

            // Merchant
            'condition'               => 'Tình trạng',
            'gtin'                    => 'GTIN',
            'identifier_exists'       => 'Has ID',
            'google_product_category' => 'Google Category',
            'product_type'            => 'Product Type',
            'shipping_weight'         => 'Cân nặng ship',
            'shipping_label'          => 'Label ship',
            'custom_label_0'          => 'Label 0',
            'custom_label_1'          => 'Label 1',
            'custom_label_2'          => 'Label 2',
            'custom_label_3'          => 'Label 3',
            'custom_label_4'          => 'Label 4',

            // Lead module
            'full_name'               => 'Họ tên',
            'phone'                   => 'Số điện thoại',
            'email'                   => 'Email',
            'source'                  => 'Nguồn',
            'status'                  => 'Trạng thái',
            'note'                    => 'Ghi chú',
            'assigned_to'             => 'Phân công',

            // Quote request
            'product_id'              => 'Sản phẩm',
            'quantity'                => 'Số lượng',
            'message'                 => 'Tin nhắn',

            // BTU
            'area_m2'                 => 'Diện tích (m²)',
            'space_type'              => 'Loại không gian',
            'recommended_btu'         => 'BTU đề xuất',
        ];

        $this->columnLabels = $labelMap;

        // Curated column order for preview (most important first)
        $priorityColumns = match($this->job->module) {
            'product' => [
                'name', 'model_code', 'sku', 'brand_id', 'product_category_id',
                'series', 'btu', 'cooling_type', 'regular_price', 'sale_price',
                'is_active', 'stock_status',
            ],
            'lead' => [
                'full_name', 'phone', 'email', 'source', 'status', 'note',
            ],
            'quote_request' => [
                'full_name', 'phone', 'product_id', 'quantity', 'message',
            ],
            'btu_calculation' => [
                'area_m2', 'space_type', 'recommended_btu',
            ],
            default => array_slice($allColumns, 0, 10),
        };

        // Only include columns that actually exist in the data
        $this->displayColumns = array_values(array_filter(
            $priorityColumns,
            fn ($col) => in_array($col, $allColumns)
        ));
    }

    /**
     * Resolve a raw field value to a human-readable display value.
     * Used in the Blade template to transform IDs → names, booleans → badges, etc.
     */
    public function resolveDisplayValue(string $column, $rawValue): array
    {
        if ($rawValue === null || $rawValue === '') {
            return ['value' => '—', 'type' => 'empty'];
        }

        // Foreign key resolution
        if ($column === 'brand_id') {
            $name = $this->brandMap[(int) $rawValue] ?? null;
            return $name
                ? ['value' => $name, 'type' => 'text']
                : ['value' => "ID: {$rawValue}", 'type' => 'id_fallback'];
        }

        if ($column === 'product_category_id') {
            $name = $this->categoryMap[(int) $rawValue] ?? null;
            return $name
                ? ['value' => $name, 'type' => 'text']
                : ['value' => "ID: {$rawValue}", 'type' => 'id_fallback'];
        }

        // Boolean fields
        if (in_array($column, ['is_active', 'is_featured', 'is_bestseller', 'is_new', 'inverter', 'schema_enabled', 'identifier_exists'])) {
            return $rawValue && $rawValue !== '0'
                ? ['value' => 'Có', 'type' => 'bool_true']
                : ['value' => 'Không', 'type' => 'bool_false'];
        }

        // Price fields
        if (in_array($column, ['regular_price', 'sale_price'])) {
            $numeric = (float) $rawValue;
            return $numeric > 0
                ? ['value' => number_format($numeric, 0, ',', '.') . 'đ', 'type' => 'price']
                : ['value' => '—', 'type' => 'empty'];
        }

        // Stock status
        if ($column === 'stock_status') {
            $label = match($rawValue) {
                'in_stock'     => 'Còn hàng',
                'out_of_stock' => 'Hết hàng',
                'pre_order'    => 'Đặt trước',
                default        => $rawValue,
            };
            $type = match($rawValue) {
                'in_stock'     => 'stock_ok',
                'out_of_stock' => 'stock_out',
                default        => 'text',
            };
            return ['value' => $label, 'type' => $type];
        }

        // Truncate long text
        $strValue = (string) $rawValue;
        if (mb_strlen($strValue) > 50) {
            return ['value' => mb_substr($strValue, 0, 50) . '…', 'type' => 'truncated', 'full' => $strValue];
        }

        return ['value' => $strValue, 'type' => 'text'];
    }

    public function getTitle(): string
    {
        $moduleName = ModuleRegistry::modules()[$this->job?->module ?? ''] ?? '';
        return "Preview Import: {$moduleName}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            DataTransferPage::getUrl() => 'Import / Export',
            '#' => 'Preview Import',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm_import')
                ->label('Xác nhận Import')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('warning')
                ->modalHeading('Xác nhận import dữ liệu')
                ->modalDescription(fn () => $this->getConfirmDescription())
                ->modalSubmitActionLabel('Xác nhận và Import')
                ->action(function () {
                    $service = app(DataImportService::class);
                    $result = $service->confirmImport($this->job);

                    if ($result->status === 'completed') {
                        Notification::make()
                            ->success()
                            ->title('Import hoàn tất!')
                            ->body("Tạo mới: {$result->created_rows} | Cập nhật: {$result->updated_rows} | Lỗi: {$result->failed_rows}")
                            ->duration(10000)
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Import thất bại')
                            ->body("Lỗi: {$result->failed_rows} dòng")
                            ->send();
                    }

                    $this->redirect(ImportResultPage::getUrl(['job' => $result->id]));
                })
                ->visible(fn () => $this->job?->status === 'previewing'),

            Action::make('cancel')
                ->label('Hủy bỏ')
                ->color('gray')
                ->icon('heroicon-o-x-mark')
                ->requiresConfirmation()
                ->modalHeading('Hủy bỏ import?')
                ->modalDescription('Dữ liệu preview sẽ bị xóa. Bạn sẽ cần upload lại file nếu muốn import.')
                ->modalSubmitActionLabel('Xác nhận hủy')
                ->modalCancelActionLabel('Quay lại')
                ->action(function () {
                    $this->job?->update(['status' => 'failed', 'finished_at' => now()]);
                    Notification::make()
                        ->info()
                        ->title('Import đã hủy.')
                        ->send();
                    $this->redirect(DataTransferPage::getUrl());
                }),
        ];
    }

    protected function getConfirmDescription(): string
    {
        if (!$this->job) return '';

        $moduleName = ModuleRegistry::modules()[$this->job->module] ?? $this->job->module;

        $parts = [];
        $parts[] = "Bạn sắp import {$this->job->total_rows} dòng dữ liệu vào module {$moduleName}.";
        $parts[] = "";
        $parts[] = "• Hợp lệ: {$this->job->success_rows} dòng";
        $parts[] = "• Tạo mới: {$this->job->created_rows} dòng";
        $parts[] = "• Cập nhật: {$this->job->updated_rows} dòng";

        if ($this->job->failed_rows > 0) {
            $parts[] = "• Lỗi (sẽ bỏ qua): {$this->job->failed_rows} dòng";
        }

        $parts[] = "";
        $parts[] = "⚠ Hành động này không thể hoàn tác.";

        return implode("\n", $parts);
    }
}
