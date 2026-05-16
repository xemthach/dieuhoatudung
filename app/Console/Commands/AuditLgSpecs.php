<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditLgSpecs extends Command
{
    protected $signature = 'products:audit-lg-specs
        {--dry-run : Preview changes without writing to DB}
        {--fix : Apply all fixes}';

    protected $description = 'Audit and fix all LG product specs: convert flat JSON to repeater format, clean empty items, map standard fields';

    // Standard DB fields that should NOT be in specs_json
    private const STANDARD_FIELD_KEYS = [
        'btu', 'capacity_btu', 'capacity_kw', 'kw', 'hp', 'horsepower',
        'inverter', 'is_inverter', 'cooling_type', 'cooling_heating_type',
        'phase', 'voltage', 'dien_ap', 'refrigerant', 'refrigerant_gas', 'gas',
        'power_consumption', 'power_input_kw', 'airflow', 'airflow_m3h',
        'noise_level', 'noise', 'sound_level_db', 'indoor_dimensions',
        'outdoor_dimensions', 'weight', 'recommended_area', 'series',
        'name', 'slug', 'brand', 'brand_id', 'product_category_id', 'model_code', 'sku',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fix = $this->option('fix');

        if (!$dryRun && !$fix) {
            $this->info('Running in AUDIT mode (no changes). Use --fix to apply changes.');
        }
        if ($dryRun) {
            $this->info('[DRY RUN] No database changes will be made.');
        }

        $brand = Brand::where('slug', 'lg')->first();
        if (!$brand) {
            $this->error('Brand LG not found.');
            return 1;
        }

        $products = Product::where('brand_id', $brand->id)->get();
        $this->info("Found {$products->count()} LG products.\n");

        $auditDir = storage_path('app/audit');
        File::ensureDirectoryExists($auditDir);

        // Backup before changes
        if ($fix) {
            $backup = $products->map(fn ($p) => [
                'id' => $p->id,
                'model_code' => $p->model_code,
                'specs_json' => $p->getRawOriginal('specs_json'),
                'power_consumption' => $p->power_consumption,
                'airflow' => $p->airflow,
                'noise_level' => $p->noise_level,
                'indoor_dimensions' => $p->indoor_dimensions,
                'outdoor_dimensions' => $p->outdoor_dimensions,
                'weight' => $p->weight,
            ])->toArray();
            File::put($auditDir . '/lg-backup-' . date('Ymd-His') . '.json',
                json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("✓ Backup saved.\n");
        }

        $stats = [
            'total' => $products->count(),
            'updated' => 0,
            'json_empty_removed' => 0,
            'json_duplicate_removed' => 0,
            'json_format_converted' => 0,
            'fields_enriched' => 0,
            'specs_cleaned' => 0,
        ];
        $missing = [];
        $changes = [];

        foreach ($products as $product) {
            $productChanges = [];
            $dirty = false;

            // ── Step 1: Parse and clean specs_json ──
            $specsRaw = $product->specs_json;
            $cleanedSpecs = [];
            $removedEmpty = 0;
            $removedDuplicate = 0;

            if (is_array($specsRaw) && !empty($specsRaw)) {
                $isRepeater = isset($specsRaw[0]) && is_array($specsRaw[0]) && array_key_exists('key', $specsRaw[0]);

                if ($isRepeater) {
                    foreach ($specsRaw as $item) {
                        $key = isset($item['key']) ? trim((string) $item['key']) : '';
                        $val = isset($item['value']) ? trim((string) $item['value']) : '';
                        if ($key === '' || $val === '') { $removedEmpty++; continue; }
                        if (in_array(strtolower($key), self::STANDARD_FIELD_KEYS)) { $removedDuplicate++; continue; }
                        $cleanedSpecs[$key] = $val;
                    }
                } else {
                    // Flat format — convert to proper format
                    $stats['json_format_converted']++;
                    foreach ($specsRaw as $key => $val) {
                        $key = trim((string) $key);
                        $val = is_scalar($val) ? trim((string) $val) : '';
                        if ($key === '' || $val === '') { $removedEmpty++; continue; }
                        if (in_array(strtolower($key), self::STANDARD_FIELD_KEYS)) { $removedDuplicate++; continue; }
                        $cleanedSpecs[$key] = $val;
                    }
                }
            }

            if ($removedEmpty > 0) {
                $stats['json_empty_removed'] += $removedEmpty;
                $productChanges[] = "Removed $removedEmpty empty JSON items";
                $dirty = true;
            }
            if ($removedDuplicate > 0) {
                $stats['json_duplicate_removed'] += $removedDuplicate;
                $productChanges[] = "Removed $removedDuplicate duplicate items";
                $dirty = true;
            }

            // ── Step 2: Ensure indoor_model/outdoor_model in specs ──
            $parts = explode('/', $product->model_code);
            $indoor = trim($parts[0] ?? '');
            $outdoor = trim($parts[1] ?? '');

            if (!empty($indoor) && !isset($cleanedSpecs['indoor_model'])) {
                $cleanedSpecs['indoor_model'] = $indoor;
            }
            if (!empty($outdoor) && !isset($cleanedSpecs['outdoor_model'])) {
                $cleanedSpecs['outdoor_model'] = $outdoor;
            }

            // ── Step 3: Add source_catalogue if missing ──
            if (!isset($cleanedSpecs['source_catalogue'])) {
                $cleanedSpecs['source_catalogue'] = 'LG SCAC Catalogue R32 2025';
            }

            // ── Step 4: Order specs logically ──
            $orderedSpecs = $this->orderSpecs($cleanedSpecs);

            // ── Step 5: Convert to repeater format ──
            $repeaterSpecs = [];
            foreach ($orderedSpecs as $k => $v) {
                $repeaterSpecs[] = ['key' => $k, 'value' => (string) $v];
            }

            // Check if specs changed
            $oldJson = json_encode($product->specs_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newJson = json_encode($repeaterSpecs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($oldJson !== $newJson) {
                $product->specs_json = $repeaterSpecs;
                $stats['specs_cleaned']++;
                $dirty = true;
                if (empty($productChanges)) {
                    $productChanges[] = 'Converted flat JSON → repeater format';
                }
            }

            // ── Save ──
            if ($dirty) {
                $stats['updated']++;
                if ($fix && !$dryRun) {
                    $product->save();
                }
                $changes[$product->model_code] = $productChanges;
            }

            // ── Track missing fields ──
            $missingFields = [];
            if (empty($product->power_consumption)) $missingFields[] = 'power_consumption';
            if (empty($product->airflow)) $missingFields[] = 'airflow';
            if (empty($product->noise_level)) $missingFields[] = 'noise_level';
            if (empty($product->indoor_dimensions)) $missingFields[] = 'indoor_dimensions';
            if (empty($product->outdoor_dimensions)) $missingFields[] = 'outdoor_dimensions';
            if (empty($product->weight)) $missingFields[] = 'weight';
            if (empty($product->recommended_area)) $missingFields[] = 'recommended_area';

            if (!empty($missingFields)) {
                $missing[] = [
                    'id' => $product->id,
                    'model_code' => $product->model_code,
                    'missing' => $missingFields,
                ];
            }
        }

        // ── Output ──
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('  LG PRODUCT SPECS AUDIT REPORT');
        $this->info('═══════════════════════════════════════');
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->toArray()
        );

        if (!empty($missing)) {
            $this->newLine();
            $this->warn("Products still missing fields: " . count($missing));
            $this->table(
                ['Model', 'Missing Fields'],
                array_map(fn ($m) => [$m['model_code'], implode(', ', $m['missing'])], array_slice($missing, 0, 10))
            );
        }

        // ── Save audit files ──
        $report = [
            'timestamp' => now()->toIso8601String(),
            'mode' => $fix ? 'FIX' : ($dryRun ? 'DRY_RUN' : 'AUDIT'),
            'stats' => $stats,
            'changes' => $changes,
            'missing' => $missing,
        ];

        File::put($auditDir . '/lg-products-clean.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (!empty($missing)) {
            $csv = "id,model_code,missing_fields\n";
            foreach ($missing as $m) {
                $csv .= "{$m['id']},\"{$m['model_code']}\",\"" . implode('; ', $m['missing']) . "\"\n";
            }
            File::put($auditDir . '/lg-products-missing.csv', "\xEF\xBB\xBF" . $csv);
        }

        $this->newLine();
        $this->info("✓ Audit report: storage/app/audit/lg-products-clean.json");
        if (!empty($missing)) {
            $this->info("✓ Missing CSV:  storage/app/audit/lg-products-missing.csv");
        }

        if (!$fix && !$dryRun) {
            $this->newLine();
            $this->warn('Run with --fix to apply all changes to the database.');
        }

        return 0;
    }

    private function orderSpecs(array $specs): array
    {
        $order = [
            'indoor_model', 'outdoor_model', 'compressor', 'eer',
            'cooling_heating', 'sub_type',
            'airflow_detail', 'noise_detail',
            'indoor_weight', 'outdoor_weight',
            'pipe_liquid', 'pipe_gas', 'pipe_length', 'height_diff',
            'source_catalogue',
        ];

        $ordered = [];
        foreach ($order as $key) {
            if (isset($specs[$key])) {
                $ordered[$key] = $specs[$key];
                unset($specs[$key]);
            }
        }
        foreach ($specs as $k => $v) {
            $ordered[$k] = $v;
        }
        return $ordered;
    }
}
