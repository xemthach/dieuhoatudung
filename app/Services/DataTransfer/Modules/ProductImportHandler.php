<?php

namespace App\Services\DataTransfer\Modules;

use App\Models\Product;
use App\Models\Brand;
use App\Models\ProductCategory;
use App\Services\DataTransfer\Contracts\ImportHandlerInterface;
use Illuminate\Support\Str;

class ProductImportHandler implements ImportHandlerInterface
{
    public function validateRow(array $row, string $mode, string $matchingKey): array
    {
        $errors = [];

        // Name required for create mode
        if ($mode === 'create' && empty($row['name'] ?? null)) {
            $errors[] = 'Tên sản phẩm (name) là bắt buộc.';
        }

        // SKU required for update by SKU
        if ($mode !== 'create' && $matchingKey === 'sku' && empty($row['sku'] ?? null)) {
            $errors[] = 'SKU là bắt buộc khi import mode Update theo SKU.';
        }

        // Validate prices
        foreach (['regular_price', 'sale_price'] as $priceField) {
            if (!empty($row[$priceField] ?? null) && !is_numeric($row[$priceField])) {
                $errors[] = "{$priceField} phải là số.";
            }
        }

        // Validate numeric fields
        foreach (['btu', 'discount_percent', 'sort_order'] as $numField) {
            if (!empty($row[$numField] ?? null) && !is_numeric($row[$numField])) {
                $errors[] = "{$numField} phải là số nguyên.";
            }
        }

        // Validate brand_id exists (if provided as name, we'll resolve it)
        if (!empty($row['brand_id'] ?? null) && !is_numeric($row['brand_id'])) {
            // Treat as brand name - will resolve during import
        }

        // Validate JSON fields
        foreach (['specs_json', 'gallery_json', 'documents_json'] as $jsonField) {
            if (!empty($row[$jsonField] ?? null) && is_string($row[$jsonField])) {
                json_decode($row[$jsonField]);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "{$jsonField} JSON không hợp lệ.";
                }
            }
        }

        return $errors;
    }

    public function findExisting(array $row, string $matchingKey): mixed
    {
        return match ($matchingKey) {
            'sku'  => !empty($row['sku']) ? Product::where('sku', $row['sku'])->first() : null,
            'slug' => !empty($row['slug']) ? Product::where('slug', $row['slug'])->first() : null,
            'id'   => !empty($row['id']) ? Product::find($row['id']) : null,
            default => null,
        };
    }

    public function importRow(array $row, string $mode, string $matchingKey): string
    {
        $data = $this->prepareData($row);
        $existing = $this->findExisting($row, $matchingKey);

        if ($mode === 'update') {
            if (!$existing) return 'skipped';
            $existing->update($data);
            return 'updated';
        }

        if ($mode === 'upsert') {
            if ($existing) {
                $existing->update($data);
                return 'updated';
            }
        }

        // Create mode or upsert without existing
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
            // Ensure unique slug
            $baseSlug = $data['slug'];
            $counter = 1;
            while (Product::where('slug', $data['slug'])->exists()) {
                $data['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        Product::create($data);
        return 'created';
    }

    protected function prepareData(array $row): array
    {
        $data = [];
        $fillableFields = [
            'name', 'slug', 'sku', 'model_code', 'brand_id', 'product_category_id',
            'series', 'btu', 'inverter', 'cooling_type', 'voltage', 'refrigerant_gas',
            'power_consumption', 'airflow', 'noise_level', 'indoor_dimensions',
            'outdoor_dimensions', 'weight', 'recommended_area',
            'regular_price', 'sale_price', 'discount_percent',
            'promotion_start_at', 'promotion_end_at', 'stock_status',
            'short_description', 'long_description', 'warranty_info', 'installation_note',
            'main_image', 'video_url',
            'is_featured', 'is_bestseller', 'is_new', 'is_active', 'sort_order',
            'seo_title', 'seo_description', 'canonical_url', 'robots',
            'og_title', 'og_description', 'og_image', 'schema_enabled',
            'condition', 'gtin', 'identifier_exists', 'google_product_category',
            'product_type', 'shipping_weight', 'shipping_label',
            'custom_label_0', 'custom_label_1', 'custom_label_2',
            'custom_label_3', 'custom_label_4',
        ];

        foreach ($fillableFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== '') {
                $data[$field] = $row[$field];
            }
        }

        // Resolve brand_id from name if not numeric
        if (!empty($data['brand_id']) && !is_numeric($data['brand_id'])) {
            $brand = Brand::where('name', $data['brand_id'])->first();
            $data['brand_id'] = $brand?->id;
        }

        // Resolve category from name if not numeric
        if (!empty($data['product_category_id']) && !is_numeric($data['product_category_id'])) {
            $cat = ProductCategory::where('name', $data['product_category_id'])->first();
            $data['product_category_id'] = $cat?->id;
        }

        // Parse JSON fields
        foreach (['specs_json', 'gallery_json', 'documents_json'] as $jsonField) {
            if (!empty($row[$jsonField]) && is_string($row[$jsonField])) {
                $decoded = json_decode($row[$jsonField], true);
                if ($decoded !== null) {
                    $data[$jsonField] = $decoded;
                }
            }
        }

        // Parse boolean fields
        foreach (['inverter', 'is_featured', 'is_bestseller', 'is_new', 'is_active', 'schema_enabled', 'identifier_exists'] as $boolField) {
            if (isset($data[$boolField])) {
                $data[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        return $data;
    }
}
