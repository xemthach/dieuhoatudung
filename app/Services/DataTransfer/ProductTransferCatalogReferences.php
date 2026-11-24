<?php

namespace App\Services\DataTransfer;

use App\Models\Brand;
use App\Models\CatalogModel;
use App\Models\CatalogSource;
use App\Models\Product;
use App\Models\ProductCategory;

/** Stable catalog references for transfer; source numeric IDs are never lookup keys. */
final class ProductTransferCatalogReferences
{
    public function export(Product $product): array
    {
        $empty = array_fill_keys([
            'catalog_source_sha256', 'catalog_source_brand_slug', 'catalog_source_category_slug',
            'catalog_normalized_model', 'catalog_normalized_sku',
        ], '');
        if (blank($product->catalog_source_id) && blank($product->catalog_model_id)) {
            return $empty;
        }

        $source = $product->catalogSource;
        $model = $product->catalogModel;
        if (! $source || (filled($product->catalog_model_id) && (! $model || $model->catalog_source_id !== $source->id))) {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER source catalog relationship is missing or inconsistent.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/D', (string) $source->source_sha256)
            || ($model && blank($model->normalized_model) && blank($model->normalized_sku))) {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog linkage requires source_sha256 and a normalized model or SKU.');
        }
        foreach ([['brand_id', 'brand'], ['category_id', 'category']] as [$foreign, $relation]) {
            if (filled($source->$foreign) && (! $source->$relation || $source->$relation->trashed())) {
                throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog source references a missing or deleted '.$relation.'.');
            }
        }
        if ((filled($source->brand_id) && (int) $source->brand_id !== (int) $product->brand_id)
            || (filled($source->category_id) && (int) $source->category_id !== (int) $product->product_category_id)) {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog source does not belong to the Product brand/category.');
        }

        return [
            'catalog_source_sha256' => $source->source_sha256,
            'catalog_source_brand_slug' => $source->brand?->slug ?? '',
            'catalog_source_category_slug' => $source->category?->slug ?? '',
            'catalog_normalized_model' => $model?->normalized_model ?? '',
            'catalog_normalized_sku' => $model?->normalized_sku ?? '',
        ];
    }

    /** @return array{source: ?CatalogSource, model: ?CatalogModel} */
    public function resolve(array $row): array
    {
        $hasSource = filled($row['catalog_source_id'] ?? null);
        $hasModel = filled($row['catalog_model_id'] ?? null);
        $stableFields = ['catalog_source_sha256', 'catalog_source_brand_slug', 'catalog_source_category_slug', 'catalog_normalized_model', 'catalog_normalized_sku'];
        if (! $hasSource && ! $hasModel) {
            foreach ($stableFields as $field) {
                if (filled($row[$field] ?? null)) {
                    throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog stable keys exist without source lineage.');
                }
            }
            return ['source' => null, 'model' => null];
        }
        if (! $hasSource || ! preg_match('/^[a-f0-9]{64}$/D', (string) ($row['catalog_source_sha256'] ?? ''))) {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog lineage requires v2 stable keys; v1 lineage cannot be verified. Re-export from the source environment.');
        }
        if ((string) ($row['source_catalog_source_id'] ?? '') !== (string) $row['catalog_source_id']
            || (string) ($row['source_catalog_model_id'] ?? '') !== (string) ($row['catalog_model_id'] ?? '')) {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER source catalog audit identifiers are inconsistent.');
        }

        $scope = [];
        foreach ([['brand', Brand::class, 'brand_id', 'brand_slug'], ['category', ProductCategory::class, 'category_id', 'category_slug']] as [$prefix, $class, $foreign, $productKey]) {
            $slug = (string) ($row['catalog_source_'.$prefix.'_slug'] ?? '');
            $scope[$foreign] = null;
            if ($slug === '') continue;
            if ($slug !== (string) ($row[$productKey] ?? '')) {
                throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog source '.$prefix.' differs from the Product reference.');
            }
            $matches = $class::query()->where('slug', $slug)->get(['id']);
            if ($matches->count() !== 1) {
                throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog source '.$prefix.' is missing, deleted or ambiguous.');
            }
            $scope[$foreign] = (int) $matches->sole()->id;
        }
        $query = CatalogSource::query()->where('source_sha256', $row['catalog_source_sha256']);
        foreach ($scope as $foreign => $id) {
            $id === null ? $query->whereNull($foreign) : $query->where($foreign, $id);
        }
        $sources = $query->get();
        if ($sources->count() !== 1) {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog source SHA256 and brand/category must resolve exactly one target source.');
        }
        $source = $sources->sole();
        $normalizedModel = (string) ($row['catalog_normalized_model'] ?? '');
        $normalizedSku = (string) ($row['catalog_normalized_sku'] ?? '');
        if (! $hasModel) {
            if ($normalizedModel !== '' || $normalizedSku !== '') {
                throw new \InvalidArgumentException('PRODUCT_TRANSFER model stable keys exist without model lineage.');
            }
            return ['source' => $source, 'model' => null];
        }
        if ($normalizedModel === '' && $normalizedSku === '') {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog model requires a normalized model or SKU.');
        }
        $matches = [];
        foreach (['normalized_model' => $normalizedModel, 'normalized_sku' => $normalizedSku] as $field => $key) {
            if ($key === '') continue;
            $models = CatalogModel::query()->where('catalog_source_id', $source->id)->where($field, $key)->get();
            if ($models->count() !== 1) {
                throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog '.$field.' must resolve exactly one model within the verified source.');
            }
            $matches[] = $models->sole();
        }
        if (count($matches) === 2 && $matches[0]->id !== $matches[1]->id) {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog model/SKU stable keys resolve different models.');
        }

        return ['source' => $source, 'model' => $matches[0]];
    }

    /** Include full target reference snapshots in the Preview/Confirm hash. */
    public function snapshot(array $row): array
    {
        $resolved = $this->resolve($row);
        return [
            'source' => $resolved['source']?->getRawOriginal(),
            'model' => $resolved['model']?->getRawOriginal(),
        ];
    }
}
