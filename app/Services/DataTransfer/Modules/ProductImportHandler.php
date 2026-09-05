<?php

namespace App\Services\DataTransfer\Modules;

use App\Models\Brand;
use App\Models\CatalogModel;
use App\Models\CatalogSource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\DataTransfer\Contracts\ImportHandlerInterface;
use App\Services\DataTransfer\ProductSystemRestoreContract;
use App\Services\DataTransfer\ImportGovernanceService;
use App\Services\Product\ProductImportMapper;
use App\Services\Product\ProductTechnicalSpecWriter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductImportHandler implements ImportHandlerInterface
{
    public function __construct(private readonly ProductTechnicalSpecWriter $technicalWriter) {}

    public function validateRow(array $row, string $mode, string $matchingKey): array
    {
        if ($mode === 'system_restore') {
            return $this->validateSystemRestoreRow($row);
        }
        if ($mode === 'product_transfer') {
            return $this->validateProductTransferRow($row);
        }

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
            if (! empty($row['slug'] ?? null)) {
                $slugExists = Product::withTrashed()->where('slug', $row['slug'])->exists();
                if ($slugExists) {
                    $errors[] = "Slug \"{$row['slug']}\" đã tồn tại. Slug sẽ được tự động tạo mới nếu bỏ trống cột slug.";
                }
            }
        }

        // ── Validate foreign keys exist in DB ──
        if (! empty($row['brand_id'] ?? null) && is_numeric($row['brand_id'])) {
            if (! Brand::find((int) $row['brand_id'])) {
                $errors[] = "Brand ID {$row['brand_id']} không tồn tại trong hệ thống.";
            }
        }

        if (! empty($row['product_category_id'] ?? null) && is_numeric($row['product_category_id'])) {
            if (! ProductCategory::find((int) $row['product_category_id'])) {
                $errors[] = "Category ID {$row['product_category_id']} không tồn tại trong hệ thống.";
            }
        }

        if (blank($row['product_category_id'] ?? null)) {
            $errors[] = 'Danh muc san pham (product_category_id) la bat buoc de doi chieu schema category.';
        }

        // Validate prices
        foreach (['regular_price', 'sale_price'] as $priceField) {
            if (! empty($row[$priceField] ?? null) && ! is_numeric($row[$priceField])) {
                $errors[] = "{$priceField} phải là số.";
            }
        }

        // Validate numeric fields
        foreach (['btu', 'discount_percent', 'sort_order'] as $numField) {
            if (! empty($row[$numField] ?? null) && ! is_numeric($row[$numField])) {
                $errors[] = "{$numField} phải là số nguyên.";
            }
        }

        if ($this->technicalInput($row) !== [] && ! $this->hasCatalogProvenance($row)) {
            $errors[] = 'Technical catalog fields require complete appendix provenance; direct product-column import is blocked.';
        }

        // Validate brand_id exists (if provided as name, we'll resolve it)
        if (! empty($row['brand_id'] ?? null) && ! is_numeric($row['brand_id'])) {
            $brand = Brand::where('name', $row['brand_id'])->first();
            if (! $brand) {
                $errors[] = "Brand \"{$row['brand_id']}\" không tìm thấy.";
            }
        }

        // Validate category name resolution
        if (! empty($row['product_category_id'] ?? null) && ! is_numeric($row['product_category_id'])) {
            $cat = ProductCategory::where('name', $row['product_category_id'])->first();
            if (! $cat) {
                $errors[] = "Category \"{$row['product_category_id']}\" không tìm thấy.";
            }
        }

        $category = $this->resolveCategory($row);
        if ($category && ! $category->hasTechnicalSchema()) {
            $errors[] = "Category schema missing for {$category->name}. Product technical specs cannot be validated until the category schema is defined.";
        }

        if ($category && $category->hasTechnicalSchema()) {
            $errors = array_merge($errors, $this->validateSpecsAgainstCategorySchema($category, $row));
        }

        // Validate JSON fields
        foreach (['specs_json', 'gallery_json', 'documents_json'] as $jsonField) {
            if (! empty($row[$jsonField] ?? null) && is_string($row[$jsonField])) {
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
            'sku' => ! empty($row['sku']) ? Product::withTrashed()->where('sku', $row['sku'])->first() : null,
            'slug' => ! empty($row['slug']) ? Product::withTrashed()->where('slug', $row['slug'])->first() : null,
            'id' => ! empty($row['id']) ? Product::withTrashed()->find($row['id']) : null,
            default => null,
        };
    }

    public function importRow(array $row, string $mode, string $matchingKey): string
    {
        if ($mode === 'system_restore') {
            return $this->restoreSystemRow($row);
        }
        if ($mode === 'product_transfer') {
            return $this->transferProductRow($row);
        }

        $technical = $this->technicalInput($row);
        $data = $this->prepareData(array_diff_key($row, $technical));
        $existing = $this->findExisting($row, $matchingKey);
        $existingByUnique = $this->findExistingByUniqueIdentifiers($row, $data);

        // ── UPDATE mode ──
        if ($mode === 'update') {
            $target = $existing ?: $existingByUnique;
            if (! $target) {
                return 'skipped';
            }
            $target->update($data);
            $this->writeTechnical($target->fresh(), $technical, $row);

            return 'updated';
        }

        // ── UPSERT mode ──
        if ($mode === 'upsert') {
            $target = $existing ?: $existingByUnique;
            if ($target) {
                $target->update($data);
                $this->writeTechnical($target->fresh(), $technical, $row);

                return 'updated';
            }
            // Fall through to create
        }

        // ── CREATE mode ──
        // Skip if record already exists (defensive — prevents duplicate errors)
        if ($mode === 'create' && ($existing || $existingByUnique)) {
            return 'skipped';
        }

        // Always ensure slug uniqueness — even when slug is provided from file
        $data['slug'] = $this->ensureUniqueSlug(
            $data['slug'] ?? null,
            $data['name'] ?? ''
        );

        $created = Product::create($data);
        $this->writeTechnical($created, $technical, $row);

        return 'created';
    }

    protected function findExistingByUniqueIdentifiers(array $row, array $preparedData = []): ?Product
    {
        $sku = $row['sku'] ?? $preparedData['sku'] ?? null;
        if (filled($sku)) {
            $product = Product::withTrashed()->where('sku', $sku)->first();
            if ($product) {
                return $product;
            }
        }

        $slug = $row['slug'] ?? $preparedData['slug'] ?? null;
        if (filled($slug)) {
            return Product::withTrashed()->where('slug', $slug)->first();
        }

        return null;
    }

    /**
     * Generate or validate a unique slug.
     * Uses withTrashed() because MySQL unique index includes soft-deleted rows.
     */
    protected function ensureUniqueSlug(?string $slug, string $name): string
    {
        if (empty($slug) && ! empty($name)) {
            $slug = Str::slug($name);
        }

        if (empty($slug)) {
            $slug = Str::slug('product-'.Str::random(8));
        }

        // Truncate to 200 chars to prevent utf8mb4 index overflow
        if (mb_strlen($slug) > 200) {
            $slug = mb_substr($slug, 0, 200);
        }

        $baseSlug = $slug;
        $counter = 1;

        // CRITICAL: withTrashed() — MySQL unique index includes soft-deleted rows
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = mb_substr($baseSlug, 0, 200).'-'.$counter++;
        }

        return $slug;
    }

    protected function prepareData(array $row): array
    {
        $data = [];
        $fillableFields = [
            'name', 'slug', 'sku', 'model_code', 'brand_id', 'product_category_id',
            'series',
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
        if (! empty($data['brand_id']) && ! is_numeric($data['brand_id'])) {
            $brand = Brand::where('name', $data['brand_id'])->first();
            $data['brand_id'] = $brand?->id;
        }

        // Resolve category from name if not numeric
        if (! empty($data['product_category_id']) && ! is_numeric($data['product_category_id'])) {
            $cat = ProductCategory::where('name', $data['product_category_id'])->first();
            $data['product_category_id'] = $cat?->id;
        }

        // Defensive: verify numeric FK IDs exist (prevent FK constraint violation)
        if (! empty($data['brand_id']) && is_numeric($data['brand_id'])) {
            if (! Brand::find((int) $data['brand_id'])) {
                $data['brand_id'] = null;
            }
        }
        if (! empty($data['product_category_id']) && is_numeric($data['product_category_id'])) {
            if (! ProductCategory::find((int) $data['product_category_id'])) {
                $data['product_category_id'] = null;
            }
        }

        // Parse JSON fields
        foreach (['specs_json', 'gallery_json', 'documents_json'] as $jsonField) {
            if (! empty($row[$jsonField]) && is_string($row[$jsonField])) {
                $decoded = json_decode($row[$jsonField], true);
                if ($decoded !== null) {
                    $data[$jsonField] = $decoded;
                }
            }
        }

        if (Schema::hasColumn('products', 'ai_status') && $this->missingVerifiedSource($data['specs_json'] ?? null)) {
            $data['ai_status'] = 'needs_review';
            $data['ai_error_message'] = 'missing_catalogue_source';
        }

        // Parse boolean fields
        foreach (['inverter', 'is_featured', 'is_bestseller', 'is_new', 'is_active', 'schema_enabled', 'identifier_exists'] as $boolField) {
            if (isset($data[$boolField])) {
                $data[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        return $data;
    }

    private function technicalInput(array $row): array
    {
        $keys = [
            'marketing_capacity_btu', 'technical_capacity_btu', 'capacity_kw', 'power_input_kw',
            'power_consumption', 'btu', 'hp', 'inverter', 'cooling_type', 'voltage',
            'refrigerant', 'refrigerant_gas', 'airflow', 'noise_level', 'indoor_dimensions',
            'outdoor_dimensions', 'weight', 'recommended_area',
        ];

        $category = $this->resolveCategory($row);
        if ($category?->hasTechnicalSchema()) {
            $keys = array_merge($keys, $category->technicalSchemaPermittedFields());
        }

        return array_filter(
            array_intersect_key($row, array_flip(array_values(array_unique($keys)))),
            fn ($value): bool => $value !== null && $value !== ''
        );
    }

    private function hasCatalogProvenance(array $row): bool
    {
        $section = (string) ($row['source_section'] ?? '');
        return in_array($section, ['TECHNICAL_APPENDIX', 'PRODUCT_LIST'], true)
            && filled($row['source_pdf'] ?? null)
            && filled($row['source_sha256'] ?? null)
            && filled($row['source_page'] ?? null)
            && filled($row['source_row'] ?? null)
            && filled($row['source_column'] ?? null)
            && filled($row['extraction_method'] ?? null);
    }

    private function writeTechnical(Product $product, array $technical, array $row): void
    {
        if ($technical === []) {
            return;
        }
        if (! $this->hasCatalogProvenance($row)) {
            throw new \InvalidArgumentException('Catalog technical import requires TECHNICAL_APPENDIX provenance.');
        }

        $map = [
            'btu' => (($row['source_section'] ?? '') === 'PRODUCT_LIST' ? 'marketing_capacity_btu' : 'technical_capacity_btu'),
            'capacity_kw' => 'capacity_kw',
            'power_input_kw' => 'power_input_kw',
            'power_consumption' => 'power_input_kw',
            'refrigerant' => 'refrigerant_gas',
        ];
        $provenance = [
            'source_pdf' => $row['source_pdf'],
            'source_sha256' => $row['source_sha256'],
            'source_page' => $row['source_page'],
            'source_row' => $row['source_row'],
            'source_column' => $row['source_column'],
            'source_section' => $row['source_section'],
            'extraction_method' => $row['extraction_method'],
        ];
        foreach ($technical as $key => $value) {
            $field = $map[$key] ?? $key;
            $this->technicalWriter->write($product, $field, $value, $provenance);
        }
    }

    private function missingVerifiedSource(mixed $specs): bool
    {
        if (! is_array($specs) || $specs === []) {
            return true;
        }

        return empty($specs['source_catalogue'])
            && empty($specs['source_page'])
            && empty($specs['source_table']);
    }

    private function resolveCategory(array $row): ?ProductCategory
    {
        $raw = $row['product_category_id'] ?? null;

        if (is_numeric($raw)) {
            return ProductCategory::query()->find((int) $raw);
        }

        if (! empty($raw)) {
            return ProductCategory::query()->where('name', (string) $raw)->first();
        }

        return null;
    }

    private function validateSpecsAgainstCategorySchema(ProductCategory $category, mixed $specs): array
    {
        $row = is_array($specs) ? $specs : [];
        // A full import row is not itself specs_json. Only an explicit technical
        // payload may be validated against a category's technical schema.
        $rawSpecs = is_array($specs) && array_key_exists('specs_json', $specs)
            ? $specs['specs_json']
            : [];

        if (is_string($specs)) {
            $specs = json_decode($specs, true);
        }

        if (is_string($rawSpecs)) {
            $rawSpecs = json_decode($rawSpecs, true);
        }

        $allowed = $category->technicalSchemaPermittedFields();
        $flatSpecs = is_array($rawSpecs) ? $this->flattenSpecs($rawSpecs) : [];
        foreach ($this->technicalSchemaInput($row) as $key => $value) {
            if ($value !== null && $value !== '') {
                $flatSpecs[$key] ??= $value;
            }
        }
        $errors = [];

        if ($allowed === []) {
            return ["Category schema for {$category->name} is incomplete: allowed fields are missing."];
        }

        $normalizedSpecs = [];

        foreach ($flatSpecs as $key => $value) {
            $normalizedKey = $category->normalizeTechnicalSchemaKey((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $normalizedSpecs[$normalizedKey] = $value;

            if (! in_array($normalizedKey, $allowed, true)) {
                $errors[] = "Spec key '{$key}' is outside the category schema for {$category->name}.";
            }
        }

        foreach ($category->technicalSchemaRequiredFields() as $requiredKey) {
            if (blank($normalizedSpecs[$requiredKey] ?? null)) {
                $errors[] = "Required spec '{$requiredKey}' is missing for {$category->name}.";
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Product metadata must never be promoted into the category technical schema.
     * marketing_capacity_btu is a commercial field and intentionally excluded.
     */
    private function technicalSchemaInput(array $row): array
    {
        $keys = [
            'technical_capacity_btu', 'capacity_kw', 'power_input_kw', 'power_consumption',
            'btu', 'hp', 'inverter', 'cooling_type', 'voltage', 'refrigerant',
            'refrigerant_gas', 'airflow', 'noise_level', 'indoor_dimensions',
            'outdoor_dimensions', 'weight', 'recommended_area',
        ];

        $category = $this->resolveCategory($row);
        if ($category?->hasTechnicalSchema()) {
            $keys = array_merge($keys, $category->technicalSchemaPermittedFields());
        }

        return array_filter(
            array_intersect_key($row, array_flip(array_values(array_unique($keys)))),
            static fn ($value): bool => $value !== null && $value !== '',
        );
    }

    private function validateSystemRestoreRow(array $row): array
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
        }

        foreach (['brand_id' => Brand::class, 'product_category_id' => ProductCategory::class, 'catalog_source_id' => CatalogSource::class, 'catalog_model_id' => CatalogModel::class] as $field => $model) {
            if (blank($row[$field] ?? null)) {
                continue;
            }
            if (! is_numeric($row[$field]) || ! $model::query()->whereKey((int) $row[$field])->exists()) {
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

    private function restoreSystemRow(array $row): string
    {
        $data = $this->prepareSystemRestoreData($row);
        $id = (int) $data['id'];
        $target = Product::withTrashed()->find($id);

        if ($target) {
            if ($target->trashed()) {
                $target->restore();
            }
            $target->fill($data);
            $target->save();

            return 'updated';
        }

        Product::create($data);

        return 'created';
    }

    private function validateProductTransferRow(array $row): array
    {
        $errors = [];
        if (blank($row['sku'] ?? null) && blank($row['slug'] ?? null)) $errors[] = 'PRODUCT_TRANSFER requires SKU or slug as stable Product identity.';
        foreach ([['brand_slug', Brand::class], ['category_slug', ProductCategory::class]] as [$field, $model]) {
            $slug = (string) ($row[$field] ?? '');
            $count = $slug === '' ? 0 : $model::withTrashed()->where('slug', $slug)->count();
            if ($count !== 1) $errors[] = "PRODUCT_TRANSFER {$field} must resolve exactly one target record.";
        }
        $skuTarget = filled($row['sku'] ?? null) ? Product::withTrashed()->where('sku', $row['sku'])->first() : null;
        $slugTarget = filled($row['slug'] ?? null) ? Product::withTrashed()->where('slug', $row['slug'])->first() : null;
        if ($skuTarget && $slugTarget && $skuTarget->id !== $slugTarget->id) $errors[] = 'PRODUCT_TRANSFER identity conflict: SKU and slug resolve different target Products.';
        $target = $skuTarget ?? $slugTarget;
        $governance = app(ImportGovernanceService::class);
        if ($target && ! $governance->enabled('product_transfer.allow_update')) $errors[] = 'PRODUCT_TRANSFER update is disabled by governance.';
        if (! $target && ! $governance->enabled('product_transfer.allow_create')) $errors[] = 'PRODUCT_TRANSFER create is disabled by governance.';
        if ((filled($row['catalog_source_id'] ?? null) || filled($row['catalog_model_id'] ?? null)) && ! $governance->catalogDetachEnabled()) {
            $errors[] = 'PRODUCT_TRANSFER catalog lineage is not portable. Enable the governed detach policy or transfer a row without catalog lineage.';
        }
        return $errors;
    }

    private function transferProductRow(array $row): string
    {
        $brand = Brand::withTrashed()->where('slug', $row['brand_slug'])->sole();
        $category = ProductCategory::withTrashed()->where('slug', $row['category_slug'])->sole();
        $data = $this->prepareSystemRestoreData($row);
        unset($data['id']);
        $data['brand_id'] = $brand->id; $data['product_category_id'] = $category->id;
        // A transfer carries the current persisted technical snapshot, not the
        // source environment's catalog authority. Keep that distinction
        // explicit even when the source row had no catalog relationship.
        $data['technical_specs_source'] = 'PRODUCT_TRANSFER';
        $hasCatalogLineage = filled($row['catalog_source_id'] ?? null) || filled($row['catalog_model_id'] ?? null);
        if ($hasCatalogLineage) {
            if (! app(ImportGovernanceService::class)->catalogDetachEnabled()) throw new \InvalidArgumentException('PRODUCT_TRANSFER catalog lineage detach is not enabled.');
            $data['catalog_source_id'] = null; $data['catalog_model_id'] = null;
        }
        $target = $this->findExisting(['sku' => $row['sku'] ?? null, 'slug' => $row['slug'] ?? null], 'sku')
            ?? $this->findExisting(['slug' => $row['slug'] ?? null], 'slug');
        if ($target) { if ($target->trashed()) $target->restore(); $target->fill($data); $target->save(); return 'updated'; }
        Product::create($data); return 'created';
    }

    private function prepareSystemRestoreData(array $row): array
    {
        $data = [];
        foreach (ProductSystemRestoreContract::fields() as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];
            if (in_array($field, ['specs_json', 'gallery_json', 'documents_json'], true) && is_string($value)) {
                $value = blank($value) ? null : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            }
            if (in_array($field, ['inverter', 'is_featured', 'is_bestseller', 'is_new', 'is_active', 'schema_enabled', 'identifier_exists', 'price_includes_vat'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
            if (in_array($field, ['promotion_start_at', 'promotion_end_at', 'technical_specs_overridden_at'], true) && blank($value)) {
                $value = null;
            }

            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }

    private function flattenSpecs(array $specs): array
    {
        $flat = [];

        if (isset($specs[0]) && is_array($specs[0])) {
            foreach ($specs as $item) {
                $key = (string) ($item['key'] ?? $item['label'] ?? '');
                if ($key === '') {
                    continue;
                }

                $flat[$key] = $item['value'] ?? $item['text'] ?? null;
            }

            return $flat;
        }

        foreach ($specs as $key => $value) {
            if (is_string($key) && $key !== '') {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }
}
