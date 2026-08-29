<?php

namespace App\Services\Catalog;

use App\Enums\CatalogSectionType;
use App\Models\CatalogModel;
use App\Models\CatalogModelField;
use App\Models\CatalogSource;
use App\Models\Brand;
use App\Services\HVAC\HVACTechnicalNormalizer;
use App\Support\Spreadsheet\SpreadsheetLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogStructuredExtractor
{
    private const MODEL_KEYS = ['model', 'model_code', 'model_indoor', 'model_outdoor', 'sku', 'ma_model'];

    public function __construct(private readonly HVACTechnicalNormalizer $normalizer) {}

    /**
     * @return array{source: ?CatalogSource, models: int, fields: int, status: string, reason?: string}
     */
    public function extractFile(array $fileMeta, bool $persist = false): array
    {
        $ext = strtolower((string) ($fileMeta['extension'] ?? pathinfo((string) $fileMeta['path'], PATHINFO_EXTENSION)));
        $path = (string) $fileMeta['path'];
        $defaultSection = $this->sectionType($fileMeta['section_type'] ?? null);

        if (! in_array($ext, ['json', 'csv', 'xlsx', 'xls'], true)) {
            return ['source' => null, 'models' => 0, 'fields' => 0, 'status' => 'skipped', 'reason' => 'unsupported_extension'];
        }

        $rows = match ($ext) {
            'json' => $this->readJsonRows($path),
            'csv' => $this->readCsvRows($path),
            default => $this->readSpreadsheetRows($path),
        };

        if ($rows->isEmpty()) {
            return ['source' => null, 'models' => 0, 'fields' => 0, 'status' => 'skipped', 'reason' => 'no_structured_rows'];
        }

        $source = null;
        if ($persist) {
            $brandId = $this->resolveBrandId((string) ($fileMeta['brand'] ?? 'unknown'));
            $source = CatalogSource::query()->firstOrCreate(
                ['uploaded_file' => $path],
                [
                    'source_name' => basename($path),
                    'source_type' => $ext,
                    'section_type' => $defaultSection->value,
                    'authority' => $fileMeta['authority'] ?? null,
                    'source_status' => $fileMeta['source_status'] ?? 'unverified',
                    'version' => date('Y-m-d'),
                    'brand_id' => $brandId,
                    'parsed_status' => 'parsed',
                    'imported_status' => 'pending',
                ]
            );

            if (! $source->brand_id && $brandId) {
                $source->update(['brand_id' => $brandId]);
            }
        }

        $modelCount = 0;
        $fieldCount = 0;

        foreach ($rows as $index => $row) {
            $identity = $this->extractIdentity($row);
            if ($identity['model'] === '' && $identity['sku'] === '') {
                continue;
            }

            $section = $this->sectionType($row['section_type'] ?? $row['source_section'] ?? $defaultSection->value);
            $fieldPayloads = $this->extractFieldPayloads($row, $path, $index + 2, $section);
            if ($fieldPayloads === []) {
                continue;
            }

            $modelCount++;
            $fieldCount += count($fieldPayloads);

            if (! $persist || ! $source) {
                continue;
            }

            $catalogModel = CatalogModel::query()->create([
                'catalog_source_id' => $source->id,
                'model' => $identity['model'] ?: null,
                'sku' => $identity['sku'] ?: null,
                'normalized_model' => $this->normalizer->normalizeModel($identity['model']),
                'normalized_sku' => $this->normalizer->normalizeSku($identity['sku']),
                'technical_data_json' => collect($fieldPayloads)->mapWithKeys(fn (array $item): array => [$item['field_key'] => $item['field_value']])->all(),
                'source_page' => null,
                'section_type' => $section->value,
                'confidence_score' => 0.95,
                'import_status' => 'parsed',
                'verification_status' => $section->isTechnicalAuthority() ? 'pending_verification' : 'not_technical',
            ]);

            foreach ($fieldPayloads as $payload) {
                CatalogModelField::query()->create([
                    'catalog_model_id' => $catalogModel->id,
                    'field_key' => $payload['field_key'],
                    'field_label' => $payload['field_label'],
                    'field_value' => $payload['field_value'],
                    'normalized_value' => $payload['normalized_value'],
                    'unit' => $payload['unit'],
                    'source_text' => $payload['source_text'],
                    'source_page' => null,
                    'source_section' => $payload['source_section'],
                    'source_table_title' => $payload['source_table_title'],
                    'source_row_label' => $payload['source_row_label'],
                    'source_column_model' => $payload['source_column_model'],
                    'extraction_method' => 'structured_row',
                    'verification_status' => $payload['verification_status'],
                    'confidence_score' => $payload['confidence_score'],
                ]);
            }
        }

        if ($source) {
            $source->update([
                'parsed_status' => $modelCount > 0 ? 'parsed' : 'skipped',
                'imported_status' => 'pending',
            ]);
        }

        return ['source' => $source, 'models' => $modelCount, 'fields' => $fieldCount, 'status' => 'ok'];
    }

    private function resolveBrandId(string $brand): ?int
    {
        $brand = Str::lower(trim($brand));
        if ($brand === '' || $brand === 'unknown') {
            return null;
        }

        $record = Brand::query()
            ->where('slug', $brand)
            ->orWhereRaw('LOWER(name) = ?', [$brand])
            ->first();

        return $record?->id;
    }

    private function extractIdentity(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[Str::of(Str::ascii((string) $key))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString()] = is_scalar($value) ? trim((string) $value) : '';
        }

        $model = '';
        $sku = '';
        foreach (self::MODEL_KEYS as $key) {
            if ($model === '' && ! empty($normalized[$key] ?? null)) {
                $model = (string) $normalized[$key];
            }
            if ($sku === '' && in_array($key, ['sku', 'model_code'], true) && ! empty($normalized[$key] ?? null)) {
                $sku = (string) $normalized[$key];
            }
        }

        if ($sku === '' && $model !== '') {
            $sku = str_replace('/', '-', $model);
        }

        return ['model' => $model, 'sku' => $sku];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function extractFieldPayloads(array $row, string $path, int $rowNumber, CatalogSectionType $section): array
    {
        $payloads = [];

        foreach ($row as $key => $value) {
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            $normalizedKey = $this->normalizer->normalizeFieldKey((string) $key);
            if ($normalizedKey === '' || in_array($normalizedKey, [
                'name', 'slug', 'model', 'model_code', 'sku', 'brand', 'category',
                'section_type', 'source_section', 'source_page', 'source_table_title',
                'source_row_label', 'source_column_model', 'authority', 'source_status',
            ], true)) {
                continue;
            }

            $normalized = $this->normalizer->normalizeValue($value, $normalizedKey, inferUnit: false);
            $payloads[] = [
                'field_key' => $normalizedKey,
                'field_label' => (string) $key,
                'field_value' => trim((string) $value),
                'normalized_value' => $normalized['value'],
                'unit' => $normalized['unit'] ?: null,
                'source_text' => sprintf('file=%s;row=%d;col=%s;value=%s', $path, $rowNumber, (string) $key, trim((string) $value)),
                'source_section' => $section->value,
                'source_table_title' => isset($row['source_table_title']) ? (string) $row['source_table_title'] : null,
                'source_row_label' => isset($row['source_row_label']) ? (string) $row['source_row_label'] : (string) $key,
                'source_column_model' => isset($row['source_column_model']) ? (string) $row['source_column_model'] : null,
                'verification_status' => $section->isTechnicalAuthority() ? 'pending_verification' : 'not_technical',
                'confidence_score' => 0.95,
            ];
        }

        return $payloads;
    }

    private function sectionType(mixed $value): CatalogSectionType
    {
        $normalized = strtolower(trim((string) $value));

        return CatalogSectionType::tryFrom($normalized) ?? CatalogSectionType::UNKNOWN;
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    private function readJsonRows(string $path): Collection
    {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return collect();
        }

        if (isset($decoded[0]) && is_array($decoded[0])) {
            return collect($decoded)->filter(fn ($row) => is_array($row))->values();
        }

        if (array_is_list($decoded)) {
            return collect();
        }

        return collect([$decoded]);
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    private function readCsvRows(string $path): Collection
    {
        $rows = [];
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return collect();
        }

        $headers = null;
        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => trim((string) $h), $data);
                continue;
            }

            if ($headers === [] || $data === []) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($data, count($headers), null)) ?: [];
        }

        fclose($handle);

        return collect($rows)->filter(fn ($row) => is_array($row) && $row !== [])->values();
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    private function readSpreadsheetRows(string $path): Collection
    {
        try {
            $sheet = SpreadsheetLoader::load($path)->getActiveSheet();
        } catch (\Throwable) {
            return collect();
        }

        $rows = $sheet->toArray(null, true, true, true);
        if ($rows === []) {
            return collect();
        }

        $headerRow = array_shift($rows);
        if (! is_array($headerRow)) {
            return collect();
        }

        $headers = [];
        foreach ($headerRow as $column => $headerValue) {
            $headers[$column] = trim((string) $headerValue);
        }

        return collect($rows)
            ->map(function ($row) use ($headers): array {
                $item = [];
                foreach ($headers as $column => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $item[$header] = $row[$column] ?? null;
                }

                return $item;
            })
            ->filter(fn (array $row): bool => collect($row)->filter(fn ($v) => ! is_null($v) && trim((string) $v) !== '')->isNotEmpty())
            ->values();
    }
}
