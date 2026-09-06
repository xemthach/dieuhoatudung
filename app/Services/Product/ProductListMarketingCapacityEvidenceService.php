<?php

namespace App\Services\Product;

use App\Models\Brand;
use App\Models\CatalogModel;
use App\Models\CatalogModelField;
use App\Models\CatalogSource;
use App\Models\Product;
use App\Services\HVAC\HVACTechnicalNormalizer;
use App\Support\Spreadsheet\SpreadsheetLoader;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProductListMarketingCapacityEvidenceService
{
    private const REQUIRED_IDENTITY = ['sku', 'model_code'];

    public function __construct(private readonly HVACTechnicalNormalizer $normalizer) {}

    /** @return array<string,mixed> */
    public function inspect(string $path, ?string $defaultBrandSlug = null): array
    {
        $realPath = realpath($path);
        if ($realPath === false || ! is_file($realPath)) {
            throw new InvalidArgumentException('Product List source file does not exist.');
        }
        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xls', 'csv', 'json'], true)) {
            throw new InvalidArgumentException('Product List source must be XLSX, XLS, CSV, or JSON.');
        }

        $sourceRows = $this->readRows($realPath, $extension);
        $classified = [];
        foreach ($sourceRows as $sourceRow) {
            $classified[] = $this->classifyRow($sourceRow, $defaultBrandSlug);
        }
        $classified = $this->markDuplicateAssignments($classified);
        $counts = collect($classified)->countBy('action')->all();

        return [
            'read_only' => true,
            'source' => [
                'filename' => basename($realPath),
                'extension' => $extension,
                'sha256' => hash_file('sha256', $realPath),
                'size' => filesize($realPath),
            ],
            'stats' => [
                'rows' => count($classified),
                'propose_evidence' => (int) ($counts['PROPOSE_EVIDENCE'] ?? 0),
                'keep' => (int) ($counts['KEEP'] ?? 0),
                'ambiguous' => (int) ($counts['AMBIGUOUS'] ?? 0),
                'no_evidence' => (int) ($counts['NO_EVIDENCE'] ?? 0),
                'conflict' => (int) ($counts['CONFLICT'] ?? 0),
            ],
            'rows' => $classified,
        ];
    }

    /** @return array<string,mixed> */
    public function persist(array $inspection, string $sourceName, ?string $version, string $batch): array
    {
        $blocked = collect($inspection['rows'])->whereIn('action', ['AMBIGUOUS', 'NO_EVIDENCE', 'CONFLICT']);
        if ($blocked->isNotEmpty()) {
            throw new RuntimeException('Evidence apply blocked because the ledger contains ambiguous, missing, or conflicting rows.');
        }

        return DB::transaction(function () use ($inspection, $sourceName, $version, $batch): array {
            $createdSources = 0;
            $createdModels = 0;
            $createdFields = 0;
            $keptFields = 0;

            foreach (collect($inspection['rows'])->where('action', 'PROPOSE_EVIDENCE') as $row) {
                $brand = Brand::query()->findOrFail((int) $row['brand_id']);
                $sourceQuery = CatalogSource::query()
                    ->where('brand_id', $brand->id)
                    ->where('source_sha256', $inspection['source']['sha256']);
                if ($sourceQuery->count() > 1) {
                    throw new RuntimeException('Duplicate CatalogSource hash for brand '.$brand->slug.'.');
                }
                $source = $sourceQuery->first();
                if (! $source) {
                    $source = CatalogSource::query()->create([
                        'brand_id' => $brand->id,
                        'source_name' => $sourceName,
                        'source_type' => $inspection['source']['extension'],
                        'section_type' => 'PRODUCT_LIST',
                        'authority' => 'OFFICIAL_PRODUCT_LIST',
                        'source_status' => 'verified',
                        'version' => $version,
                        'uploaded_file' => $inspection['source']['filename'],
                        'source_sha256' => $inspection['source']['sha256'],
                        'parsed_status' => 'parsed',
                        'imported_status' => 'approved',
                    ]);
                    $createdSources++;
                }

                $modelQuery = CatalogModel::query()
                    ->where('catalog_source_id', $source->id)
                    ->where(function ($query) use ($row): void {
                        if ($row['normalized_sku'] !== '') $query->orWhere('normalized_sku', $row['normalized_sku']);
                        if ($row['normalized_model'] !== '') $query->orWhere('normalized_model', $row['normalized_model']);
                    });
                if ($modelQuery->count() > 1) {
                    throw new RuntimeException('Duplicate CatalogModel identity for Product '.$row['product_id'].'.');
                }
                $model = $modelQuery->first();
                if (! $model) {
                    $model = CatalogModel::query()->create([
                        'catalog_source_id' => $source->id,
                        'model' => $row['model_code'] ?: null,
                        'sku' => $row['sku'] ?: null,
                        'normalized_model' => $row['normalized_model'] ?: null,
                        'normalized_sku' => $row['normalized_sku'] ?: null,
                        'section_type' => 'PRODUCT_LIST',
                        'confidence_score' => 100,
                        'import_status' => 'approved',
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                    ]);
                    $createdModels++;
                }

                $fields = CatalogModelField::query()
                    ->where('catalog_model_id', $model->id)
                    ->where('field_key', 'marketing_capacity_btu')
                    ->where('source_section', 'PRODUCT_LIST')
                    ->get();
                if ($fields->count() > 1) {
                    throw new RuntimeException('Duplicate marketing capacity evidence for Product '.$row['product_id'].'.');
                }
                $field = $fields->first();
                if ($field) {
                    $existing = (int) ($field->normalized_value ?: $field->field_value);
                    if ($existing !== (int) $row['proposed_marketing']) {
                        throw new RuntimeException('Conflicting persisted marketing capacity evidence for Product '.$row['product_id'].'.');
                    }
                    $keptFields++;
                    continue;
                }

                CatalogModelField::query()->create([
                    'catalog_model_id' => $model->id,
                    'field_key' => 'marketing_capacity_btu',
                    'field_label' => 'Marketing capacity BTU',
                    'field_value' => (string) $row['proposed_marketing'],
                    'normalized_value' => (string) $row['proposed_marketing'],
                    'unit' => 'btu',
                    'source_text' => $row['source_text'],
                    'source_page' => $row['source_page'],
                    'source_section' => 'PRODUCT_LIST',
                    'source_table_title' => $row['source_sheet'],
                    'source_row_label' => (string) $row['source_row'],
                    'source_column_model' => $row['identity_source'],
                    'extraction_method' => 'approved_structured_product_list',
                    'verification_status' => 'approved',
                    'verified_at' => now(),
                    'confidence_score' => 100,
                ]);
                $createdFields++;
            }

            return compact('batch', 'createdSources', 'createdModels', 'createdFields', 'keptFields');
        });
    }

    /** @return array<int,array<string,mixed>> */
    private function readRows(string $path, string $extension): array
    {
        if ($extension === 'json') {
            $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($rows) || ! array_is_list($rows)) throw new InvalidArgumentException('JSON must contain a list of rows.');
            return array_map(fn (array $row, int $index): array => $this->normalizeRow($row, 'JSON', $index + 1), $rows, array_keys($rows));
        }
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb');
            if ($handle === false) throw new InvalidArgumentException('Unable to read CSV.');
            $headers = fgetcsv($handle) ?: [];
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                $values = array_pad($values, count($headers), null);
                $rows[] = $this->normalizeRow(array_combine($headers, array_slice($values, 0, count($headers))) ?: [], 'CSV', $line);
            }
            fclose($handle);
            return $rows;
        }

        $workbook = SpreadsheetLoader::load($path);
        $rows = [];
        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $matrix = $sheet->toArray(null, true, true, false);
            if ($matrix === []) continue;
            $headers = array_map(fn ($value): string => $this->header((string) $value), array_shift($matrix));
            foreach ($matrix as $offset => $values) {
                if (collect($values)->filter(fn ($value) => $value !== null && trim((string) $value) !== '')->isEmpty()) continue;
                $values = array_pad($values, count($headers), null);
                $rows[] = $this->normalizeRow(array_combine($headers, array_slice($values, 0, count($headers))) ?: [], $sheet->getTitle(), $offset + 2);
            }
        }
        $workbook->disconnectWorksheets();
        return $rows;
    }

    /** @return array<string,mixed> */
    private function normalizeRow(array $row, string $sheet, int $number): array
    {
        $normalized = [];
        foreach ($row as $key => $value) $normalized[$this->header((string) $key)] = is_scalar($value) ? trim((string) $value) : '';
        return ['data' => $normalized, 'sheet' => $sheet, 'row' => $number];
    }

    private function header(string $value): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value), '_'));
    }

    /** @return array<string,mixed> */
    private function classifyRow(array $sourceRow, ?string $defaultBrandSlug): array
    {
        $row = $sourceRow['data'];
        $brandSlug = strtolower(trim((string) ($row['brand_slug'] ?? $row['brand'] ?? $defaultBrandSlug ?? '')));
        $sku = trim((string) ($row['sku'] ?? ''));
        $model = trim((string) ($row['model_code'] ?? $row['model'] ?? ''));
        $capacity = $this->positiveInteger($row['marketing_capacity_btu'] ?? $row['commercial_capacity_btu'] ?? $row['capacity_tier_btu'] ?? null);
        $base = [
            'source_sheet' => $sourceRow['sheet'], 'source_row' => $sourceRow['row'],
            'brand_slug' => $brandSlug, 'sku' => $sku, 'model_code' => $model,
            'normalized_sku' => $this->normalizer->normalizeSku($sku),
            'normalized_model' => $this->normalizer->normalizeModel($model),
            'proposed_marketing' => $capacity,
            'source_page' => $this->positiveInteger($row['source_page'] ?? $row['page'] ?? null),
            'source_text' => trim((string) ($row['source_text'] ?? $row['evidence'] ?? json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))),
            'identity_source' => $sku !== '' ? 'sku' : ($model !== '' ? 'model_code' : null),
            'product_id' => null, 'brand_id' => null, 'current_marketing' => null,
            'action' => null, 'reason' => null,
        ];
        if ($brandSlug === '' || ($sku === '' && $model === '') || $capacity === null) {
            return array_merge($base, ['action' => 'NO_EVIDENCE', 'reason' => 'REQUIRED_PRODUCT_LIST_FIELDS_MISSING_OR_INVALID']);
        }
        $brandMatches = Brand::query()->where('slug', $brandSlug)->get();
        if ($brandMatches->count() !== 1) {
            return array_merge($base, ['action' => $brandMatches->isEmpty() ? 'NO_EVIDENCE' : 'AMBIGUOUS', 'reason' => 'BRAND_SLUG_NOT_UNIQUE']);
        }
        $brand = $brandMatches->first();
        $normalizedSku = $base['normalized_sku'];
        $normalizedModel = $base['normalized_model'];
        $matches = Product::query()->where('brand_id', $brand->id)->get(['id', 'sku', 'model_code', 'marketing_capacity_btu'])->filter(function (Product $product) use ($normalizedSku, $normalizedModel): bool {
            return ($normalizedSku !== '' && $this->normalizer->normalizeSku($product->sku) === $normalizedSku)
                || ($normalizedModel !== '' && $this->normalizer->normalizeModel($product->model_code) === $normalizedModel);
        })->values();
        if ($matches->count() !== 1) {
            return array_merge($base, ['brand_id' => $brand->id, 'action' => $matches->isEmpty() ? 'NO_EVIDENCE' : 'AMBIGUOUS', 'reason' => 'PRODUCT_IDENTITY_MATCH_COUNT_'.$matches->count()]);
        }
        $product = $matches->first();
        if ($product->marketing_capacity_btu !== null && (int) $product->marketing_capacity_btu !== $capacity) {
            return array_merge($base, ['brand_id' => $brand->id, 'product_id' => $product->id, 'current_marketing' => (int) $product->marketing_capacity_btu, 'action' => 'CONFLICT', 'reason' => 'CANONICAL_VALUE_DIFFERS_FROM_PRODUCT_LIST']);
        }

        return array_merge($base, [
            'brand_id' => $brand->id, 'product_id' => $product->id,
            'current_marketing' => $product->marketing_capacity_btu === null ? null : (int) $product->marketing_capacity_btu,
            'action' => $product->marketing_capacity_btu === null ? 'PROPOSE_EVIDENCE' : 'KEEP',
            'reason' => $product->marketing_capacity_btu === null ? 'EXACT_PRODUCT_LIST_IDENTITY' : 'CANONICAL_VALUE_MATCHES_PRODUCT_LIST',
        ]);
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function markDuplicateAssignments(array $rows): array
    {
        $groups = collect($rows)->filter(fn (array $row): bool => $row['product_id'] !== null)->groupBy('product_id');
        foreach ($groups as $productRows) {
            if ($productRows->count() < 2) continue;
            foreach ($productRows as $duplicate) {
                $index = array_search($duplicate, $rows, true);
                if ($index !== false) {
                    $rows[$index]['action'] = 'CONFLICT';
                    $rows[$index]['reason'] = 'DUPLICATE_PRODUCT_LIST_ASSIGNMENT';
                }
            }
        }
        return $rows;
    }

    private function positiveInteger(mixed $value): ?int
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        return preg_match('/^[1-9]\d*$/D', $value) ? (int) $value : null;
    }
}
