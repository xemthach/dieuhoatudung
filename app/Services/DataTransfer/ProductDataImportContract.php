<?php

namespace App\Services\DataTransfer;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Services\DataTransfer\Contracts\ImportHandlerInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Commercial Product edits never enter the catalog technical writer. */
final class ProductDataImportContract implements ImportHandlerInterface
{
    public const VERSION = 1;

    public static function fields(): array
    {
        return [
            'sku', 'slug', 'name', 'model_code', 'brand_slug', 'category_slug', 'series',
            'regular_price', 'sale_price', 'discount_percent', 'promotion_start_at',
            'promotion_end_at', 'stock_status', 'short_description', 'long_description',
            'warranty_info', 'installation_note', 'main_image', 'gallery_json', 'video_url',
            'documents_json', 'is_featured', 'is_bestseller', 'is_new', 'is_active', 'sort_order',
            'seo_title', 'seo_description', 'canonical_url', 'robots', 'og_title',
            'og_description', 'og_image', 'schema_enabled', 'condition', 'gtin',
            'identifier_exists', 'google_product_category', 'product_type',
            'merchant_title', 'merchant_description', 'shipping_weight', 'shipping_label',
            'custom_label_0', 'custom_label_1', 'custom_label_2', 'custom_label_3',
            'custom_label_4', 'price_includes_vat',
        ];
    }

    public function findExisting(array $row, string $matchingKey): ?Product
    {
        if (! in_array($matchingKey, ['sku', 'slug'], true)) {
            throw new \InvalidArgumentException('Product Data Edit requires SKU or slug.');
        }

        return filled($row[$matchingKey] ?? null)
            ? Product::withTrashed()->where($matchingKey, $row[$matchingKey])->first()
            : null;
    }

    public function validateRow(array $row, string $mode, string $matchingKey): array
    {
        $target = $this->findExisting($row, $matchingKey);
        $rules = [
            'name' => [...($target ? ['sometimes'] : []), 'required', 'string', 'max:255'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'product_category_id' => [$target ? 'nullable' : 'required', 'integer', 'exists:product_categories,id'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($target?->id)],
            'slug' => ['sometimes', 'required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('products', 'slug')->ignore($target?->id)],
            'regular_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'discount_percent' => ['nullable', 'integer', 'between:0,100'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'stock_status' => ['sometimes', 'required', Rule::enum(StockStatus::class)],
            'promotion_start_at' => ['nullable', 'date'],
            'promotion_end_at' => ['nullable', 'date'],
            'robots' => ['sometimes', 'required', 'string', 'max:255'],
            'condition' => ['sometimes', 'required', Rule::in(['new', 'used', 'refurbished'])],
        ];
        foreach (['is_featured', 'is_bestseller', 'is_new', 'is_active', 'schema_enabled', 'identifier_exists', 'price_includes_vat'] as $field) {
            $rules[$field] = ['sometimes', 'required', function ($attribute, $value, $fail): void {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
                    $fail($attribute.' requires 1/0 or true/false.');
                }
            }];
        }
        foreach (['gallery_json', 'documents_json'] as $field) {
            $rules[$field] = ['nullable', 'json'];
        }
        foreach (self::fields() as $field) {
            if (! isset($rules[$field]) && ! in_array($field, ['brand_slug', 'category_slug'], true)) {
                $rules[$field] = ['nullable', 'string', ...(! in_array($field, ['long_description', 'short_description', 'warranty_info', 'installation_note', 'merchant_description'], true) ? ['max:255'] : [])];
            }
        }
        $rules[$matchingKey][] = 'required';
        $errors = Validator::make($row, $rules)->errors()->all();
        if ($target?->trashed()) {
            $errors[] = 'Product is soft deleted; restore it separately before editing.';
        }
        if ($mode === 'create' && $target) {
            $errors[] = 'Product already exists; use update or upsert.';
        }
        if ($mode === 'update' && ! $target) {
            $errors[] = 'Product matching key does not exist.';
        }
        if (! in_array($mode, ['create', 'update', 'upsert'], true)) {
            $errors[] = 'Invalid Product Data Edit mode.';
        }

        return $errors;
    }

    public function importRow(array $row, string $mode, string $matchingKey): string
    {
        $errors = $this->validateRow($row, $mode, $matchingKey);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
        $target = $this->findExisting($row, $matchingKey);
        $fields = array_merge(array_diff(self::fields(), ['brand_slug', 'category_slug']), ['brand_id', 'product_category_id']);
        $data = array_intersect_key($row, array_flip($fields));
        foreach ($data as $key => $value) {
            $data[$key] = $value === '' ? null : $value;
        }
        foreach (['gallery_json', 'documents_json'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = json_decode($data[$key], true, 512, JSON_THROW_ON_ERROR);
            }
        }
        foreach (['is_featured', 'is_bestseller', 'is_new', 'is_active', 'schema_enabled', 'identifier_exists', 'price_includes_vat'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = filter_var($data[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }
        if ($target) {
            if (array_key_exists('product_category_id', $data) && (int) $target->product_category_id !== (int) $data['product_category_id']) {
                $data += array_fill_keys(['taxonomy_status', 'taxonomy_source', 'taxonomy_ledger_digest', 'taxonomy_evidence_json', 'taxonomy_verified_at'], null);
            }
            $target->fill($data);
            if (! $target->save()) {
                throw new \RuntimeException('Không thể cập nhật Product.');
            }

            return 'updated';
        }
        $product = new Product($data);
        if (! $product->save()) {
            throw new \RuntimeException('Không thể tạo Product.');
        }

        return 'created';
    }
}
