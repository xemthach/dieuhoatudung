<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSpecsSnapshot;
use App\Services\HVAC\HVACTechnicalNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncSpecsFromCatalogCommand extends Command
{
    protected $signature = 'products:sync-specs-from-catalog
        {--batch= : Audit batch ID}
        {--approved : Explicit approval flag required}
        {--source=storage/catalogs/verified_pdf_extract_gree.json : Source JSON path}';

    protected $description = 'Apply approved catalog spec sync from internal extracted catalog data.';

    public function handle(HVACTechnicalNormalizer $normalizer): int
    {
        if (! $this->option('approved')) {
            $this->error('Blocked: add --approved after manual review.');

            return self::FAILURE;
        }

        $batch = $this->option('batch');
        if (! $batch) {
            $this->error('Missing --batch option.');

            return self::FAILURE;
        }

        $source = (string) $this->option('source');
        $sourcePath = str_starts_with($source, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $source)
            ? $source
            : base_path($source);

        if (! is_file($sourcePath)) {
            $this->error("Source file not found: {$sourcePath}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($sourcePath), true);
        if (! is_array($rows)) {
            $this->error('Invalid JSON source.');

            return self::FAILURE;
        }

        $summary = [
            'total_rows' => count($rows),
            'matched_products' => 0,
            'updated' => 0,
            'skipped_no_product' => 0,
            'skipped_low_confidence_or_missing_source' => 0,
        ];

        DB::transaction(function () use ($rows, $batch, $normalizer, &$summary, $sourcePath): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $model = trim((string) ($row['model'] ?? ''));
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($model === '' && $sku === '') {
                    continue;
                }

                $product = Product::query()
                    ->where(function ($q) use ($model, $sku): void {
                        if ($model !== '') {
                            $q->orWhere('model_code', $model);
                        }
                        if ($sku !== '') {
                            $q->orWhere('sku', $sku);
                        }
                    })
                    ->first();

                if (! $product) {
                    if ($model !== '') {
                        $normalizedModel = $normalizer->normalizeModel($model);
                        $product = Product::query()->get()->first(function (Product $p) use ($normalizer, $normalizedModel): bool {
                            return $normalizer->normalizeModel((string) $p->model_code) === $normalizedModel
                                || $normalizer->normalizeSku((string) $p->sku) === $normalizedModel;
                        });
                    }
                }

                if (! $product) {
                    $summary['skipped_no_product']++;
                    continue;
                }

                $summary['matched_products']++;
                $old = [
                    'btu' => $product->btu,
                    'refrigerant_gas' => $product->refrigerant_gas,
                    'voltage' => $product->voltage,
                    'airflow' => $product->airflow,
                    'noise_level' => $product->noise_level,
                    'indoor_dimensions' => $product->indoor_dimensions,
                    'outdoor_dimensions' => $product->outdoor_dimensions,
                    'specs_json' => $product->specs_json,
                ];

                $specsJson = is_array($product->specs_json) ? $product->specs_json : [];
                $updates = [];
                $hasValidField = false;

                foreach ($row as $key => $value) {
                    if (! is_string($key) || str_contains($key, '__')) {
                        continue;
                    }
                    if (in_array($key, ['model', 'sku'], true)) {
                        continue;
                    }
                    if ($value === null || trim((string) $value) === '') {
                        continue;
                    }

                    $sourceFile = $row[$key.'__source_file'] ?? null;
                    $sourceText = $row[$key.'__source_text'] ?? null;
                    $sourcePage = $row[$key.'__source_page'] ?? null;
                    $confidence = (float) ($row[$key.'__confidence'] ?? 0);

                    if (! $sourceFile || ! $sourceText || $confidence < 0.85) {
                        $summary['skipped_low_confidence_or_missing_source']++;
                        continue;
                    }

                    $hasValidField = true;
                    $rawValue = trim((string) $value);

                    switch ($key) {
                        case 'capacity_btu':
                            preg_match_all('/\d+/', $rawValue, $m);
                            $nums = $m[0] ?? [];
                            $num = 0;
                            if (count($nums) >= 2 && strlen($nums[0]) >= 4 && strlen($nums[1]) >= 4) {
                                // Common catalog format: "24200 / 25200" -> choose first (cooling) value.
                                $num = (int) $nums[0];
                            } elseif (count($nums) >= 1) {
                                $num = (int) $nums[0];
                            }
                            if ($num > 0) {
                                $updates['btu'] = $num;
                            }
                            break;
                        case 'refrigerant':
                            $updates['refrigerant_gas'] = $rawValue;
                            break;
                        case 'voltage':
                            $updates['voltage'] = $rawValue;
                            break;
                        case 'airflow':
                            $updates['airflow'] = $rawValue;
                            break;
                        case 'noise_level':
                            $updates['noise_level'] = $rawValue;
                            break;
                        case 'indoor_dimensions':
                            $updates['indoor_dimensions'] = $rawValue;
                            break;
                        case 'outdoor_dimensions':
                            $updates['outdoor_dimensions'] = $rawValue;
                            break;
                        default:
                            // keep advanced/extra technical fields in specs_json
                            break;
                    }

                    $specsJson[$key] = [
                        'value' => $rawValue,
                        'source_file' => $sourceFile,
                        'source_page' => $sourcePage,
                        'source_text' => $sourceText,
                        'confidence' => $confidence,
                        'batch_id' => $batch,
                    ];
                }

                if (! $hasValidField) {
                    continue;
                }

                $updates['specs_json'] = $specsJson;
                $updates['catalog_match_status'] = 'matched';
                $updates['technical_specs_source'] = 'catalog_verified_specs';
                $updates['updated_at'] = now();

                ProductSpecsSnapshot::query()->create([
                    'product_id' => $product->id,
                    'snapshot_json' => [
                        'batch_id' => $batch,
                        'source' => $sourcePath,
                        'old_specs' => $old,
                        'new_specs' => $updates,
                    ],
                    'reason' => 'catalog_sync_batch:'.$batch,
                    'created_by' => auth()->id(),
                ]);

                $product->update($updates);
                $summary['updated']++;
            }
        });

        $this->table(['Metric', 'Count'], [
            ['Total rows in source', $summary['total_rows']],
            ['Matched products', $summary['matched_products']],
            ['Updated products', $summary['updated']],
            ['Skipped (no product)', $summary['skipped_no_product']],
            ['Skipped (invalid source/confidence)', $summary['skipped_low_confidence_or_missing_source']],
        ]);
        $this->info('Batch applied: '.$batch);

        return self::SUCCESS;
    }
}
