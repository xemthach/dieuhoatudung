<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditPanasonicSpecs extends Command
{
    protected $signature = 'products:audit-panasonic-specs
        {--dry-run : Preview changes without writing to DB}
        {--fix : Apply all fixes}';

    protected $description = 'Audit and fix all Panasonic product specs: convert flat JSON to repeater format, clean empty items';

    // Standard DB fields that should NOT be in specs_json
    private const STANDARD_FIELD_KEYS = [
        'btu', 'capacity_btu', 'capacity_kw', 'kw', 'hp', 'horsepower',
        'inverter', 'is_inverter', 'cooling_type', 'cooling_heating_type',
        'phase', 'voltage', 'dien_ap', 'refrigerant', 'refrigerant_gas', 'gas',
        'power_consumption', 'power_input_kw', 'airflow', 'airflow_m3h',
        'noise_level', 'noise', 'sound_level_db', 'indoor_dimensions',
        'outdoor_dimensions', 'weight', 'recommended_area',
        'name', 'slug', 'brand', 'brand_id', 'product_category_id', 'model_code', 'sku',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fix = $this->option('fix');

        if (!$dryRun && !$fix) {
            $this->info('Running in AUDIT mode (no changes). Use --fix to apply changes.');
        }

        $brand = Brand::where('slug', 'panasonic')->first();
        if (!$brand) {
            $this->error('Brand Panasonic not found.');
            return 1;
        }

        $products = Product::where('brand_id', $brand->id)->get();
        $this->info("Found {$products->count()} Panasonic products.\n");

        $auditDir = storage_path('app/audit');
        File::ensureDirectoryExists($auditDir);

        // Backup
        if ($fix) {
            $backup = $products->map(fn ($p) => [
                'id' => $p->id,
                'model_code' => $p->model_code,
                'specs_json' => $p->getRawOriginal('specs_json'),
            ])->toArray();
            File::put($auditDir . '/panasonic-backup-' . date('Ymd-His') . '.json',
                json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("✓ Backup saved.\n");
        }

        $stats = [
            'total' => $products->count(),
            'updated' => 0,
            'json_empty_removed' => 0,
            'json_duplicate_removed' => 0,
            'json_format_converted' => 0,
            'specs_cleaned' => 0,
            'indoor_count' => 0,
            'outdoor_count' => 0,
            'combination_count' => 0,
        ];
        $missing = [];
        $changes = [];
        $indoorModels = [];
        $outdoorModels = [];

        foreach ($products as $product) {
            $productChanges = [];
            $dirty = false;

            // ── Parse and clean specs_json ──
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

            if ($removedEmpty > 0) { $stats['json_empty_removed'] += $removedEmpty; $dirty = true; }
            if ($removedDuplicate > 0) { $stats['json_duplicate_removed'] += $removedDuplicate; $dirty = true; }

            // ── Ensure indoor/outdoor model ──
            $parts = explode('/', $product->model_code);
            $indoor = trim($parts[0] ?? '');
            $outdoor = trim($parts[1] ?? '');

            if (!empty($indoor) && !isset($cleanedSpecs['indoor_model'])) {
                $cleanedSpecs['indoor_model'] = $indoor;
            }
            if (!empty($outdoor) && !isset($cleanedSpecs['outdoor_model'])) {
                $cleanedSpecs['outdoor_model'] = $outdoor;
            }

            // Track indoor/outdoor counts
            if (!empty($indoor)) $indoorModels[$indoor] = true;
            if (!empty($outdoor)) $outdoorModels[$outdoor] = true;
            $stats['combination_count']++;

            // ── Add source_catalogue if missing ──
            if (!isset($cleanedSpecs['source_catalogue'])) {
                $cleanedSpecs['source_catalogue'] = 'Panasonic Commercial AC Catalogue 2025';
            }

            // ── Order specs logically ──
            $ordered = $this->orderSpecs($cleanedSpecs);

            // ── Convert to repeater format ──
            $repeaterSpecs = [];
            foreach ($ordered as $k => $v) {
                $repeaterSpecs[] = ['key' => $k, 'value' => (string) $v];
            }

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

            if ($dirty) {
                $stats['updated']++;
                if ($fix && !$dryRun) { $product->save(); }
                $changes[$product->model_code] = $productChanges;
            }

            // ── Missing fields ──
            $mf = [];
            foreach (['power_consumption','airflow','noise_level','indoor_dimensions','outdoor_dimensions','weight','recommended_area'] as $f) {
                if (empty($product->$f)) $mf[] = $f;
            }
            if (!empty($mf)) {
                $missing[] = ['id' => $product->id, 'model_code' => $product->model_code, 'missing' => $mf];
            }
        }

        $stats['indoor_count'] = count($indoorModels);
        $stats['outdoor_count'] = count($outdoorModels);

        // ── Output ──
        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('  PANASONIC PRODUCT SPECS AUDIT REPORT');
        $this->info('═══════════════════════════════════════════');
        $this->table(['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->toArray());

        if (!empty($missing)) {
            $this->newLine();
            $this->warn("Products still missing fields: " . count($missing));
            $this->table(['Model', 'Missing'],
                array_map(fn ($m) => [$m['model_code'], implode(', ', $m['missing'])], array_slice($missing, 0, 10)));
        }

        // ── Save files ──
        $report = [
            'timestamp' => now()->toIso8601String(),
            'mode' => $fix ? 'FIX' : ($dryRun ? 'DRY_RUN' : 'AUDIT'),
            'stats' => $stats,
            'changes' => $changes,
            'missing' => $missing,
            'indoor_models' => array_keys($indoorModels),
            'outdoor_models' => array_keys($outdoorModels),
        ];
        File::put($auditDir . '/panasonic-products-clean.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (!empty($missing)) {
            $csv = "id,model_code,missing_fields\n";
            foreach ($missing as $m) {
                $csv .= "{$m['id']},\"{$m['model_code']}\",\"" . implode('; ', $m['missing']) . "\"\n";
            }
            File::put($auditDir . '/panasonic-products-missing.csv', "\xEF\xBB\xBF" . $csv);
        }

        $this->newLine();
        $this->info("✓ Report: storage/app/audit/panasonic-products-clean.json");
        if (!empty($missing)) $this->info("✓ Missing: storage/app/audit/panasonic-products-missing.csv");
        if (!$fix && !$dryRun) { $this->newLine(); $this->warn('Run with --fix to apply changes.'); }

        return 0;
    }

    private function orderSpecs(array $specs): array
    {
        $order = [
            'indoor_model', 'outdoor_model', 'series',
            'cspf', 'eer_cop', 'nanoe_x',
            'power_consumption_kw', 'temp_range',
            'noise_outdoor',
            'pipe_liquid', 'pipe_gas', 'pipe_max_length', 'height_diff',
            'source_catalogue',
        ];
        $ordered = [];
        foreach ($order as $key) {
            if (isset($specs[$key])) { $ordered[$key] = $specs[$key]; unset($specs[$key]); }
        }
        foreach ($specs as $k => $v) { $ordered[$k] = $v; }
        return $ordered;
    }
}
