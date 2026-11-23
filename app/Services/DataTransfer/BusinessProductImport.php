<?php

namespace App\Services\DataTransfer;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

final class BusinessProductImport
{
    public function map(Collection $rows, array $mapping, string $matchingKey): Collection
    {
        if (! in_array($matchingKey, ['sku', 'slug'], true)) throw new \InvalidArgumentException('Nhập nghiệp vụ Product chỉ ghép theo SKU hoặc slug.');
        $destinations = array_values(array_filter($mapping, static fn ($value) => $value !== '' && $value !== '_skip'));
        if (count($destinations) !== count(array_unique($destinations))) throw new \InvalidArgumentException('Mapping không được ghi nhiều cột vào cùng một trường.');
        $seen = [];
        $rowErrors = [];

        $mapped = $rows->map(function (array $source, int $index) use ($mapping, $matchingKey, &$seen, &$rowErrors): array {
            try {
            $row = [];
            foreach ($source as $key => $value) {
                $target = $mapping[$key] ?? $key;
                if ($target === '' || $target === '_skip') continue;
                if (array_key_exists($target, $row)) throw new \InvalidArgumentException('Mapping cột bị trùng: '.$target);
                $row[$target] = $value;
            }
            foreach (['technical_capacity_status', 'marketing_capacity_status', 'taxonomy_status', 'catalog_source_id', 'catalog_model_id', 'ai_status'] as $protected) {
                if (filled($row[$protected] ?? null)) throw new \InvalidArgumentException('Không nhập trực tiếp trường quản trị: '.$protected);
                unset($row[$protected]);
            }
            foreach ($row as $value) {
                if (is_string($value) && str_starts_with($value, ProductSystemRestoreContract::PAYLOAD_TOKEN_PREFIX)) throw new \InvalidArgumentException('Payload Restore phải nhập qua luồng System Restore.');
            }
            unset($row['id']);
            foreach (['regular_price', 'sale_price'] as $field) {
                if (filled($row[$field] ?? null) && (! is_numeric($row[$field]) || ! is_finite((float) $row[$field]) || (float) $row[$field] < 0)) {
                    throw new \InvalidArgumentException($field.' phải là số không âm.');
                }
            }
            foreach (['is_active', 'is_featured', 'is_bestseller', 'is_new', 'schema_enabled', 'identifier_exists', 'inverter'] as $field) {
                if (filled($row[$field] ?? null) && filter_var($row[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
                    throw new \InvalidArgumentException($field.' phải dùng 1/0 hoặc true/false.');
                }
            }
            foreach ([['brand', Brand::class, 'brand_id'], ['category', ProductCategory::class, 'product_category_id']] as [$prefix, $model, $foreign]) {
                $slug = trim((string) ($row[$prefix.'_slug'] ?? ''));
                $name = trim((string) ($row[$prefix.'_name'] ?? ''));
                if ($slug !== '' || $name !== '') {
                    $matches = $model::query()->where('is_active', true)->where($slug !== '' ? 'slug' : 'name', $slug !== '' ? $slug : $name)->get();
                    if ($matches->count() !== 1) throw new \InvalidArgumentException('Không tìm thấy duy nhất '.$prefix.': '.($slug ?: $name));
                    if (filled($row[$foreign] ?? null) && (string) $row[$foreign] !== (string) $matches->sole()->id) throw new \InvalidArgumentException('FK và slug/name không khớp: '.$prefix);
                    $row[$foreign] = $matches->sole()->id;
                }
                unset($row[$prefix.'_slug'], $row[$prefix.'_name']);
            }
            $key = trim((string) ($row[$matchingKey] ?? ''));
            if ($key === '' || isset($seen[$key])) throw new \InvalidArgumentException('SKU/slug thiếu hoặc trùng trong file: '.$key);
            $seen[$key] = true;
            $target = Product::withTrashed()->where($matchingKey, $key)->first();
            if ($target?->trashed()) throw new \InvalidArgumentException('Sản phẩm đã xóa mềm; cần phục hồi riêng trước khi nhập.');
            if ($target) {
                $row['name'] ??= $target->name;
                $row['product_category_id'] ??= $target->product_category_id;
            }
            $category = ProductCategory::find($row['product_category_id'] ?? 0);
            $allowed = array_unique(array_merge(ProductSystemRestoreContract::fields(),
                ['source_pdf', 'source_sha256', 'source_page', 'source_row', 'source_column', 'extraction_method', 'source_section'],
                $category?->technicalSchemaPermittedFields() ?? []));
            $unknown = array_diff(array_keys($row), $allowed);
            if ($unknown !== []) throw new \InvalidArgumentException('Cột chưa được mapping: '.implode(', ', $unknown));
            // Do not fall through to a different unique identifier in the legacy importer.
            foreach (['sku', 'slug'] as $identity) {
                if (filled($row[$identity] ?? null)) {
                    $collision = Product::withTrashed()->where($identity, $row[$identity])->first();
                    if ($collision && (! $target || $collision->id !== $target->id)) throw new \InvalidArgumentException('Xung đột khóa '.$identity.'; không tự đổi matching key.');
                }
            }
            return $row;
            } catch (\InvalidArgumentException $error) {
                $rowErrors[$index + 2] = [$error->getMessage()];
                return [];
            }
        });
        if ($rowErrors !== []) throw \Illuminate\Validation\ValidationException::withMessages($rowErrors);
        return $mapped;
    }
}
