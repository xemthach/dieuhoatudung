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

        // ── CREATE mode: detect existing records to prevent duplicate errors ──
        if ($mode === 'create') {
            $existing = $this->findExisting($row, $matchingKey);
            if ($existing) {
                $errors[] = "Sản phẩm đã tồn tại (#{$existing->id}: {$existing->name}). Dùng mode UPSERT nếu muốn cập nhật.";
            }

            // Also check slug uniqueness if slug is provided (include soft-deleted)
            if (!empty($row['slug'] ?? null)) {
                $slugExists = Product::withTrashed()->where('slug', $row['slug'])->exists();
                if ($slugExists) {
                    $errors[] = "Slug \"{$row['slug']}\" đã tồn tại. Slug sẽ được tự động tạo mới nếu bỏ trống cột slug.";
                }
            }
        }

        // ── Validate foreign keys exist in DB ──
        if (!empty($row['brand_id'] ?? null) && is_numeric($row['brand_id'])) {
            if (!Brand::find((int) $row['brand_id'])) {
                $errors[] = "Brand ID {$row['brand_id']} không tồn tại trong hệ thống.";
            }
        }

        if (!empty($row['product_category_id'] ?? null) && is_numeric($row['product_category_id'])) {
            if (!ProductCategory::find((int) $row['product_category_id'])) {
                $errors[] = "Category ID {$row['product_category_id']} không tồn tại trong hệ thống.";
            }
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
            $brand = Brand::where('name', $row['brand_id'])->first();
            if (!$brand) {
                $errors[] = "Brand \"{$row['brand_id']}\" không tìm thấy.";
            }
        }

        // Validate category name resolution
        if (!empty($row['product_category_id'] ?? null) && !is_numeric($row['product_category_id'])) {
            $cat = ProductCategory::where('name', $row['product_category_id'])->first();
            if (!$cat) {
                $errors[] = "Category \"{$row['product_category_id']}\" không tìm thấy.";
            }
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
        // withTrashed: MySQL unique index includes soft-deleted rows
        return match ($matchingKey) {
            'sku'  => !empty($row['sku']) ? Product::withTrashed()->where('sku', $row['sku'])->first() : null,
            'slug' => !empty($row['slug']) ? Product::withTrashed()->where('slug', $row['slug'])->first() : null,
            'id'   => !empty($row['id']) ? Product::withTrashed()->find($row['id']) : null,
            default => null,
        };
    }

    public function importRow(array $row, string $mode, string $matchingKey): string
    {
        $data = $this->prepareData($row);
        $existing = $this->findExisting($row, $matchingKey);

        // ── UPDATE mode ──
        if ($mode === 'update') {
            if (!$existing) return 'skipped';
            $existing->update($data);
            return 'updated';
        }

        // ── UPSERT mode ──
        if ($mode === 'upsert') {
            if ($existing) {
                $existing->update($data);
                return 'updated';
            }
            // Fall through to create
        }

        // ── CREATE mode ──
        // Skip if record already exists (defensive — prevents duplicate errors)
        if ($mode === 'create' && $existing) {
            return 'skipped';
        }

        // Always ensure slug uniqueness — even when slug is provided from file
        $data['slug'] = $this->ensureUniqueSlug(
            $data['slug'] ?? null,
            $data['name'] ?? ''
        );

        Product::create($data);
        return 'created';
    }

    /**
     * Generate or validate a unique slug.
     * Uses withTrashed() because MySQL unique index includes soft-deleted rows.
     */
    protected function ensureUniqueSlug(?string $slug, string $name): string
    {
        if (empty($slug) && !empty($name)) {
            $slug = Str::slug($name);
        }

        if (empty($slug)) {
            $slug = Str::slug('product-' . Str::random(8));
        }

        // Truncate to 200 chars to prevent utf8mb4 index overflow
        if (mb_strlen($slug) > 200) {
            $slug = mb_substr($slug, 0, 200);
        }

        $baseSlug = $slug;
        $counter = 1;

        // CRITICAL: withTrashed() — MySQL unique index includes soft-deleted rows
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = mb_substr($baseSlug, 0, 200) . '-' . $counter++;
        }

        return $slug;
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

        // Defensive: verify numeric FK IDs exist (prevent FK constraint violation)
        if (!empty($data['brand_id']) && is_numeric($data['brand_id'])) {
            if (!Brand::find((int) $data['brand_id'])) {
                $data['brand_id'] = null;
            }
        }
        if (!empty($data['product_category_id']) && is_numeric($data['product_category_id'])) {
            if (!ProductCategory::find((int) $data['product_category_id'])) {
                $data['product_category_id'] = null;
            }
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
