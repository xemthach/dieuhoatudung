<?php

namespace App\Services\DataTransfer\Modules;

use App\Models\Brand;
use App\Models\CatalogModel;
use App\Models\CatalogSource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AI\DraftOnlyWriteGuard;
use App\Services\DataTransfer\Contracts\ImportHandlerInterface;
use App\Services\DataTransfer\ProductSnapshotData;
use App\Services\DataTransfer\ProductSystemRestoreContract;
use Illuminate\Support\Str;

/** Manifest-bound, ID-preserving Product restore; no catalog inference. */
final class ProductRestoreImportHandler implements ImportHandlerInterface
{
    public function findExisting(array $row, string $matchingKey): ?Product
    {
        return filled($row['id'] ?? null) ? Product::withTrashed()->find((int) $row['id']) : null;
    }

    public function validateRow(array $row, string $mode, string $matchingKey): array
    {
        $errors = [];

        if (! isset($row['id']) || ! filter_var($row['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            $errors[] = 'SYSTEM RESTORE requires a positive Product ID.';
        }
        if (blank($row['name'] ?? null)) {
            $errors[] = 'SYSTEM RESTORE requires Product name.';
        }
        if (blank($row['slug'] ?? null)) {
            $errors[] = 'SYSTEM RESTORE requires Product slug.';
        } elseif (Str::slug((string) $row['slug']) !== (string) $row['slug']) {
            // Restore is a byte-for-byte data contract. A non-canonical slug
            // would be rewritten by the Product model's saving hook, so reject
            // it during preview instead of silently changing the manifest.
            $errors[] = 'SYSTEM RESTORE requires a canonical Product slug.';
        }

        foreach (['brand_id' => Brand::class, 'product_category_id' => ProductCategory::class, 'catalog_source_id' => CatalogSource::class, 'catalog_model_id' => CatalogModel::class] as $field => $model) {
            if (blank($row[$field] ?? null)) {
                continue;
            }
            $query = in_array($model, [Brand::class, ProductCategory::class], true) ? $model::withTrashed() : $model::query();
            if (! filter_var($row[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || ! $query->whereKey((int) $row[$field])->exists()) {
                $errors[] = "SYSTEM RESTORE foreign key {$field} is missing on this target.";
            }
        }

        if (filled($row['catalog_model_id'] ?? null) && filled($row['catalog_source_id'] ?? null)) {
            $matchesSource = CatalogModel::query()
                ->whereKey((int) $row['catalog_model_id'])
                ->where('catalog_source_id', (int) $row['catalog_source_id'])
                ->exists();
            if (! $matchesSource) {
                $errors[] = 'SYSTEM RESTORE catalog_model_id does not belong to catalog_source_id.';
            }
        }

        foreach (['specs_json', 'gallery_json', 'documents_json'] as $jsonField) {
            if (blank($row[$jsonField] ?? null) || is_array($row[$jsonField])) {
                continue;
            }
            json_decode((string) $row[$jsonField], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "SYSTEM RESTORE {$jsonField} must contain valid JSON.";
            }
        }

        $byId = filled($row['id'] ?? null) ? Product::withTrashed()->find((int) $row['id']) : null;
        if ($byId && $byId->slug !== $row['slug'] && (blank($row['sku'] ?? null) || $byId->sku !== $row['sku'])) {
            $errors[] = 'SYSTEM RESTORE Product ID is occupied by a different SKU/slug.';
        }
        foreach (['sku', 'slug'] as $field) {
            if (blank($row[$field] ?? null)) {
                continue;
            }
            $byUnique = Product::withTrashed()->where($field, $row[$field])->first();
            if ($byUnique && (! $byId || $byUnique->id !== $byId->id)) {
                $errors[] = "SYSTEM RESTORE {$field} belongs to Product #{$byUnique->id}, not the exported ID.";
            }
        }

        return $errors;
    }

    public function importRow(array $row, string $mode, string $matchingKey): string
    {
        if (DraftOnlyWriteGuard::isActive()) {
            throw new \RuntimeException('Product restore is not allowed inside a draft-only operation.');
        }
        $errors = $this->validateRow($row, 'system_restore', 'id');
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
        $exact = array_key_exists('updated_at', $row);
        $data = $exact ? array_intersect_key($row, array_flip(ProductSystemRestoreContract::fields()))
            : ProductSnapshotData::prepare($row, ProductSystemRestoreContract::fields(1));
        $id = (int) $data['id'];
        $target = Product::withTrashed()->find($id);

        if ($target) {
            if (! array_key_exists('deleted_at', $data)) {
                $data['deleted_at'] = null;
            }
            $this->fillSnapshot($target, $data, $exact);
            // Product::saving normalises slugs and may apply other runtime
            // policies. A validated restore must preserve the manifest value
            // exactly, therefore bypass model events for this write.
            $this->saveSnapshot($target, $data);

            return 'updated';
        }

        $created = new Product;
        $this->fillSnapshot($created, $data, $exact);
        $this->saveSnapshot($created, $data);

        return 'created';
    }

    private function fillSnapshot(Product $product, array $data, bool $exact): void
    {
        if ($exact) {
            $product->setRawAttributes(array_replace($product->getAttributes(), $data));
        } else {
            $product->fill($data);
        }
    }

    private function saveSnapshot(Product $product, array $data): void
    {
        if (array_key_exists('updated_at', $data)) {
            $product->timestamps = false;
        }
        $product->saveQuietly();
    }
}
