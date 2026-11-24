<?php

namespace App\Services\DataTransfer\Modules;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AI\DraftOnlyWriteGuard;
use App\Services\DataTransfer\Contracts\ImportHandlerInterface;
use App\Services\DataTransfer\ImportGovernanceService;
use App\Services\DataTransfer\ProductSnapshotData;
use App\Services\DataTransfer\ProductSystemRestoreContract;
use App\Services\DataTransfer\ProductTransferCatalogReferences;

/** Stable-key Product transfer, with explicit catalog-lineage governance. */
final class ProductTransferImportHandler implements ImportHandlerInterface
{
    public function findExisting(array $row, string $matchingKey): ?Product
    {
        $skuTarget = filled($row['sku'] ?? null) ? Product::withTrashed()->where('sku', $row['sku'])->first() : null;
        $slugTarget = filled($row['slug'] ?? null) ? Product::withTrashed()->where('slug', $row['slug'])->first() : null;
        if ($skuTarget && $slugTarget && $skuTarget->id !== $slugTarget->id) {
            throw new \InvalidArgumentException('PRODUCT_TRANSFER identity conflict: SKU and slug resolve different target Products.');
        }

        return $skuTarget ?? $slugTarget;
    }

    public function validateRow(array $row, string $mode, string $matchingKey): array
    {
        $errors = [];
        if (blank($row['sku'] ?? null) && blank($row['slug'] ?? null)) {
            $errors[] = 'PRODUCT_TRANSFER requires SKU or slug as stable Product identity.';
        }
        if (blank($row['slug'] ?? null) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', (string) $row['slug'])) {
            $errors[] = 'PRODUCT_TRANSFER requires a canonical Product slug.';
        }
        $resolved = [];
        foreach ([['brand_slug', Brand::class], ['category_slug', ProductCategory::class]] as [$field, $model]) {
            $slug = (string) ($row[$field] ?? '');
            $sourceId = $field === 'brand_slug' ? 'source_brand_id' : 'source_category_id';
            $resolved[$field] = null;
            if ($slug === '' && blank($row[$sourceId] ?? null)) {
                continue;
            }
            $matches = $slug === '' ? collect() : $model::query()->where('slug', $slug)->get(['id']);
            $resolved[$field] = $matches->count() === 1 ? (int) $matches->sole()->id : null;
            if ($resolved[$field] === null) {
                $errors[] = "PRODUCT_TRANSFER {$field} must resolve exactly one target record.";
            }
        }
        $skuTarget = filled($row['sku'] ?? null) ? Product::withTrashed()->where('sku', $row['sku'])->first() : null;
        $slugTarget = filled($row['slug'] ?? null) ? Product::withTrashed()->where('slug', $row['slug'])->first() : null;
        if ($skuTarget && $slugTarget && $skuTarget->id !== $slugTarget->id) {
            $errors[] = 'PRODUCT_TRANSFER identity conflict: SKU and slug resolve different target Products.';
        }
        $target = $skuTarget ?? $slugTarget;
        $governance = app(ImportGovernanceService::class);
        if (! $governance->enabled('product_transfer.enabled')) {
            $errors[] = 'PRODUCT_TRANSFER is disabled by governance.';
        }
        if ($target?->trashed()) {
            $errors[] = 'PRODUCT_TRANSFER cannot reactivate a soft-deleted Product.';
        }
        $catalog = null;
        try {
            $catalog = app(ProductTransferCatalogReferences::class)->resolve($row);
        } catch (\InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        }
        if ($target && $catalog !== null
            && ((int) $target->catalog_source_id !== (int) $catalog['source']?->id
                || (int) $target->catalog_model_id !== (int) $catalog['model']?->id)) {
            $errors[] = 'PRODUCT_TRANSFER cannot replace catalog lineage on the target Product.';
        }
        if ($target && ((int) $target->brand_id !== (int) $resolved['brand_slug'] || (int) $target->product_category_id !== (int) $resolved['category_slug'])) {
            $errors[] = 'PRODUCT_TRANSFER cannot change the brand or category of a matched Product.';
        }
        if ($target && filled($target->marketing_capacity_status) && (int) $target->marketing_capacity_btu !== (int) ($row['marketing_capacity_btu'] ?? null)) {
            $errors[] = 'PRODUCT_TRANSFER cannot replace a governed marketing capacity on the target Product.';
        }
        if ($target && ! $governance->enabled('product_transfer.allow_update')) {
            $errors[] = 'PRODUCT_TRANSFER update is disabled by governance.';
        }
        if (! $target && ! $governance->enabled('product_transfer.allow_create')) {
            $errors[] = 'PRODUCT_TRANSFER create is disabled by governance.';
        }
        return $errors;
    }

    public function importRow(array $row, string $mode, string $matchingKey): string
    {
        if (DraftOnlyWriteGuard::isActive()) {
            throw new \RuntimeException('Product transfer is not allowed inside a draft-only operation.');
        }
        $errors = $this->validateRow($row, 'product_transfer', 'sku');
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
        $brand = filled($row['brand_slug'] ?? null) ? Brand::query()->where('slug', $row['brand_slug'])->sole() : null;
        $category = filled($row['category_slug'] ?? null) ? ProductCategory::query()->where('slug', $row['category_slug'])->sole() : null;
        $data = ProductSnapshotData::prepare($row, ProductSystemRestoreContract::fields(1));
        unset($data['id']);
        $data['brand_id'] = $brand?->id;
        $data['product_category_id'] = $category?->id;
        $catalog = app(ProductTransferCatalogReferences::class)->resolve($row);
        $data['catalog_source_id'] = $catalog['source']?->id;
        $data['catalog_model_id'] = $catalog['model']?->id;
        // Exact catalog identity is the only path that preserves catalog
        // provenance. An unlinked snapshot remains explicitly a transfer.
        if ($catalog['source'] === null) {
            $data['technical_specs_source'] = 'PRODUCT_TRANSFER';
        }
        $target = $this->findExisting($row, 'sku');
        if ($target) {
            $target->fill($data);
            $target->saveQuietly();

            return 'updated';
        }
        $created = new Product;
        $created->forceFill($data);
        $created->saveQuietly();

        return 'created';
    }
}
