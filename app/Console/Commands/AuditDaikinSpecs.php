<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditDaikinSpecs extends Command
{
    protected $signature = 'products:audit-daikin-specs
        {--dry-run : Preview changes without writing to DB}
        {--fix : Apply all fixes}';

    protected $description = 'Audit and fix all Daikin product specs: clean empty JSON, enrich from catalogue data';

    // ── Catalogue dimension/weight data from Daikin Sky Air PDF ──
    // Source: parse_skyair.py INDOOR_SPECS / OUTDOOR_SPECS
    private const INDOOR_SPECS = [
        'FCAHG' => ['dim' => '288×840×840', 'wt' => 25.0],
        'FCAG'  => ['dim_sm' => '204×840×840', 'dim_lg' => '246×840×840', 'wt_sm' => 18, 'wt_lg' => 23],
        'FFA'   => ['dim' => '36×950×950', 'wt' => 17],
        'FDXM'  => ['dim' => '200×700×620', 'wt' => 12],
        'FBA'   => ['dim_sm' => '245×700×700', 'dim_lg' => '245×1000×700', 'wt_sm' => 19, 'wt_lg' => 27],
        'FDA125' => ['dim' => '390×1200×800', 'wt' => 48],
        'FDA200' => ['dim' => '610×1650×1050', 'wt' => 100],
        'ADEA'  => ['dim' => '245×700×700', 'wt' => 19],
        'FAA'   => ['dim' => '295×1050×238', 'wt' => 13],
        'FTXM'  => ['dim' => '295×798×272', 'wt' => 10],
        'FHA'   => ['dim_sm' => '224×850×690', 'dim_lg' => '250×1400×690', 'wt_sm' => 24, 'wt_lg' => 35],
        'FUA'   => ['dim' => '350×1400×700', 'wt' => 43],
        'FVA'   => ['dim' => '1845×600×790', 'wt' => 79],
        'FNA'   => ['dim' => '620×750×200', 'wt' => 17],
    ];

    private const OUTDOOR_SPECS = [
        'RZAG35B'      => ['dim' => '734×870×373', 'wt' => 52],
        'RZAG50B'      => ['dim' => '734×870×373', 'wt' => 52],
        'RZAG60B'      => ['dim' => '734×870×373', 'wt' => 52],
        'RZAG71NV1'    => ['dim' => '870×1100×460', 'wt' => 81],
        'RZAG100NV1'   => ['dim' => '870×1100×460', 'wt' => 81],
        'RZAG125NV1'   => ['dim' => '870×1100×460', 'wt' => 85],
        'RZAG140NV1'   => ['dim' => '870×1100×460', 'wt' => 95],
        'RZAG71NY1'    => ['dim' => '870×1100×460', 'wt' => 81],
        'RZAG100NY1'   => ['dim' => '870×1100×460', 'wt' => 81],
        'RZAG125NY1'   => ['dim' => '870×1100×460', 'wt' => 85],
        'RZAG140NY1'   => ['dim' => '870×1100×460', 'wt' => 94],
        'RZASG71MV1'   => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG100MV'   => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG100MV1'  => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG125MV'   => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG125MV1'  => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG140MV'   => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG140MV1'  => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG100MY'   => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG100MY1'  => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG125MY'   => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG125MY1'  => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG140MY'   => ['dim' => '870×1100×460', 'wt' => 76],
        'RZASG140MY1'  => ['dim' => '870×1100×460', 'wt' => 76],
        'ARXM71A'      => ['dim' => '620×800×300', 'wt' => 38],
        'AZAS100MV'    => ['dim' => '870×1100×460', 'wt' => 76],
        'AZAS125MV'    => ['dim' => '870×1100×460', 'wt' => 76],
        'AZAS140MV'    => ['dim' => '870×1100×460', 'wt' => 76],
        'AZAS100MY'    => ['dim' => '870×1100×460', 'wt' => 76],
        'AZAS125MY'    => ['dim' => '870×1100×460', 'wt' => 76],
        'AZAS140MY'    => ['dim' => '870×1100×460', 'wt' => 76],
        'RZA200D'      => ['dim' => '1680×930×765', 'wt' => 175],
        'RZA250D'      => ['dim' => '1680×930×765', 'wt' => 175],
        'RXM25A9'      => ['dim' => '550×658×275', 'wt' => 26],
        'RXM35A9'      => ['dim' => '550×658×275', 'wt' => 26],
        'RXM50A8'      => ['dim' => '734×870×373', 'wt' => 41],
        'RXM60A'       => ['dim' => '734×870×373', 'wt' => 44],
        // Packaged VN outdoor units
        'RZUR200QY1'   => ['dim' => '870×1100×460', 'wt' => 113],
        'RZUR250QY1'   => ['dim' => '1657×930×765', 'wt' => 185],
        'RZUR300QY1'   => ['dim' => '1657×930×765', 'wt' => 185],
        'RZUR400QY1'   => ['dim' => '1657×1240×765', 'wt' => 260],
        'RZUR450QY1'   => ['dim' => '1657×1240×765', 'wt' => 291],
        'RZUR500QY1'   => ['dim' => '1657×1240×765', 'wt' => 291],
    ];

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

        $brand = Brand::where('slug', 'daikin')->first();
        if (!$brand) {
            $this->error('Brand Daikin not found.');
            return 1;
        }

        // Load original import JSON for enrichment
        $importData = $this->loadImportJson();

        $products = Product::where('brand_id', $brand->id)->get();
        $this->info("Found {$products->count()} Daikin products.\n");

        // Ensure audit output directory
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
            File::put($auditDir . '/daikin-backup-' . date('Ymd-His') . '.json',
                json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("✓ Backup saved.\n");
        }

        $stats = [
            'total' => $products->count(),
            'updated' => 0,
            'json_empty_removed' => 0,
            'json_mapped_to_field' => 0,
            'fields_enriched' => 0,
            'dimensions_added' => 0,
            'weight_added' => 0,
            'specs_cleaned' => 0,
        ];
        $missing = [];
        $changes = [];

        foreach ($products as $product) {
            $productChanges = [];
            $dirty = false;

            // ── Step 1: Clean specs_json ──
            $specsRaw = $product->specs_json;
            $cleanedSpecs = [];
            $removedEmpty = 0;
            $movedToField = 0;

            if (is_array($specsRaw) && !empty($specsRaw)) {
                // Check if it's repeater format [{key:..., value:...}] or flat {key: value}
                $isRepeater = isset($specsRaw[0]) && is_array($specsRaw[0]) && array_key_exists('key', $specsRaw[0]);

                if ($isRepeater) {
                    foreach ($specsRaw as $item) {
                        $key = isset($item['key']) ? trim((string) $item['key']) : '';
                        $val = isset($item['value']) ? trim((string) $item['value']) : '';

                        if ($key === '' || $val === '') {
                            $removedEmpty++;
                            continue;
                        }
                        // Skip standard fields that shouldn't be in JSON
                        if (in_array(strtolower($key), self::STANDARD_FIELD_KEYS)) {
                            $movedToField++;
                            continue;
                        }
                        $cleanedSpecs[$key] = $val;
                    }
                } else {
                    // Flat format {indoor_model: ..., outdoor_model: ...}
                    foreach ($specsRaw as $key => $val) {
                        $key = trim((string) $key);
                        $val = is_scalar($val) ? trim((string) $val) : '';

                        if ($key === '' || $val === '') {
                            $removedEmpty++;
                            continue;
                        }
                        if (in_array(strtolower($key), self::STANDARD_FIELD_KEYS)) {
                            $movedToField++;
                            continue;
                        }
                        $cleanedSpecs[$key] = $val;
                    }
                }
            }

            if ($removedEmpty > 0) {
                $stats['json_empty_removed'] += $removedEmpty;
                $productChanges[] = "Removed $removedEmpty empty JSON items";
                $dirty = true;
            }
            if ($movedToField > 0) {
                $stats['json_mapped_to_field'] += $movedToField;
                $dirty = true;
            }

            // ── Step 2: Enrich from import JSON (SEER, SCOP, heating_kw, indoor/outdoor model) ──
            $importRow = $importData[$product->model_code] ?? null;
            if ($importRow) {
                $importSpecs = json_decode($importRow['specs_json'] ?? '{}', true) ?: [];

                // Map useful specs from import
                foreach ($importSpecs as $k => $v) {
                    if ($v === null || $v === '') continue;

                    // These belong in specs_json as extra specs
                    if (in_array($k, ['seer', 'scop', 'heating_kw', 'indoor_model', 'outdoor_model',
                        'cspf', 'fan_type', 'compressor', 'esp',
                        'power_consumption_kw', 'noise_indoor', 'noise_outdoor',
                        'pipe_liquid', 'pipe_gas', 'pipe_max_length', 'height_diff',
                        'indoor_weight', 'outdoor_weight'])) {
                        if (!isset($cleanedSpecs[$k])) {
                            $cleanedSpecs[$k] = (string) $v;
                        }
                    }
                }

                // Map standard fields from import if empty in DB
                if (empty($product->power_consumption) && !empty($importRow['power_consumption'])) {
                    $product->power_consumption = $importRow['power_consumption'];
                    $stats['fields_enriched']++;
                    $productChanges[] = "power_consumption = {$importRow['power_consumption']}";
                    $dirty = true;
                }
                if (empty($product->airflow) && !empty($importRow['airflow'])) {
                    $product->airflow = $importRow['airflow'];
                    $stats['fields_enriched']++;
                    $productChanges[] = "airflow = {$importRow['airflow']}";
                    $dirty = true;
                }
                if (empty($product->noise_level) && !empty($importRow['noise_level'])) {
                    $product->noise_level = $importRow['noise_level'];
                    $stats['fields_enriched']++;
                    $productChanges[] = "noise_level = {$importRow['noise_level']}";
                    $dirty = true;
                }
            }

            // ── Step 3: Enrich dimensions & weight from catalogue constants ──
            $parts = explode('/', $product->model_code);
            $indoor = trim($parts[0] ?? '');
            $outdoor = trim($parts[1] ?? '');

            // Ensure indoor_model/outdoor_model in specs
            if (!empty($indoor) && !isset($cleanedSpecs['indoor_model'])) {
                $cleanedSpecs['indoor_model'] = $indoor;
            }
            if (!empty($outdoor) && !isset($cleanedSpecs['outdoor_model'])) {
                $cleanedSpecs['outdoor_model'] = $outdoor;
            }

            // Indoor dimensions
            if (empty($product->indoor_dimensions)) {
                $indoorDim = $this->lookupIndoorDim($indoor);
                if ($indoorDim) {
                    $product->indoor_dimensions = $indoorDim;
                    $stats['dimensions_added']++;
                    $productChanges[] = "indoor_dimensions = $indoorDim (from catalogue)";
                    $dirty = true;
                }
            }

            // Outdoor dimensions
            if (empty($product->outdoor_dimensions)) {
                $outdoorSpec = self::OUTDOOR_SPECS[$outdoor] ?? null;
                if ($outdoorSpec) {
                    $product->outdoor_dimensions = $outdoorSpec['dim'];
                    $stats['dimensions_added']++;
                    $productChanges[] = "outdoor_dimensions = {$outdoorSpec['dim']} (from catalogue)";
                    $dirty = true;
                }
            }

            // Weight
            if (empty($product->weight)) {
                $indoorWt = $this->lookupIndoorWeight($indoor);
                $outdoorWt = isset(self::OUTDOOR_SPECS[$outdoor]) ? self::OUTDOOR_SPECS[$outdoor]['wt'] : null;

                if ($indoorWt && $outdoorWt) {
                    $product->weight = "{$indoorWt}/{$outdoorWt} kg";
                    $stats['weight_added']++;
                    $productChanges[] = "weight = {$indoorWt}/{$outdoorWt} kg (from catalogue)";
                    $dirty = true;
                } elseif ($indoorWt) {
                    $product->weight = "{$indoorWt} kg (dàn lạnh)";
                    $stats['weight_added']++;
                    $dirty = true;
                }
            }

            // ── Step 4: Add source_catalogue to specs ──
            if (!isset($cleanedSpecs['source_catalogue'])) {
                $cleanedSpecs['source_catalogue'] = 'Daikin Sky Air Catalogue 2025';
            }

            // ── Step 5: Order specs logically ──
            $orderedSpecs = $this->orderSpecs($cleanedSpecs);

            // ── Step 6: Convert back to repeater format ──
            $repeaterSpecs = [];
            foreach ($orderedSpecs as $k => $v) {
                $repeaterSpecs[] = ['key' => $k, 'value' => (string) $v];
            }

            // Check if specs actually changed
            $oldSpecsJson = json_encode($product->specs_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newSpecsJson = json_encode($repeaterSpecs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($oldSpecsJson !== $newSpecsJson) {
                $product->specs_json = $repeaterSpecs;
                $stats['specs_cleaned']++;
                $dirty = true;
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
                    'reason' => 'Not available in Sky Air catalogue (only efficiency table data extracted)',
                ];
            }
        }

        // ── Output summary ──
        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('  DAIKIN PRODUCT SPECS AUDIT REPORT');
        $this->info('═══════════════════════════════════════════');
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->toArray()
        );

        if (!empty($missing)) {
            $this->newLine();
            $this->warn("Products still missing fields: " . count($missing));
            $this->table(
                ['Model', 'Missing Fields'],
                array_map(fn ($m) => [$m['model_code'], implode(', ', $m['missing'])], array_slice($missing, 0, 15))
            );
            if (count($missing) > 15) {
                $this->line("  ... and " . (count($missing) - 15) . " more. See audit JSON for full list.");
            }
        }

        // ── Save audit files ──
        $auditReport = [
            'timestamp' => now()->toIso8601String(),
            'mode' => $fix ? 'FIX' : ($dryRun ? 'DRY_RUN' : 'AUDIT'),
            'stats' => $stats,
            'changes' => $changes,
            'missing' => $missing,
        ];

        File::put($auditDir . '/daikin-product-specs-audit.json',
            json_encode($auditReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // CSV for missing
        if (!empty($missing)) {
            $csv = "id,model_code,missing_fields,reason\n";
            foreach ($missing as $m) {
                $csv .= "{$m['id']},\"{$m['model_code']}\",\"" . implode('; ', $m['missing']) . "\",\"{$m['reason']}\"\n";
            }
            File::put($auditDir . '/daikin-product-specs-missing.csv', "\xEF\xBB\xBF" . $csv);
        }

        $this->newLine();
        $this->info("✓ Audit report: storage/app/audit/daikin-product-specs-audit.json");
        $this->info("✓ Missing CSV:  storage/app/audit/daikin-product-specs-missing.csv");

        if (!$fix && !$dryRun) {
            $this->newLine();
            $this->warn('Run with --fix to apply all changes to the database.');
        }

        return 0;
    }

    private function loadImportJson(): array
    {
        $path = base_path('data dieu hoa/import_output/daikin_products_import.json');
        if (!file_exists($path)) {
            $this->warn("Import JSON not found at: $path");
            return [];
        }

        $data = json_decode(file_get_contents($path), true) ?: [];
        $indexed = [];
        foreach ($data as $row) {
            $indexed[$row['model_code']] = $row;
        }
        return $indexed;
    }

    private function lookupIndoorDim(string $indoor): ?string
    {
        // Try exact prefix match first (FDA125, FDA200)
        foreach (['FDA125', 'FDA200'] as $special) {
            if (str_starts_with($indoor, $special)) {
                return self::INDOOR_SPECS[$special]['dim'];
            }
        }

        // Try prefix match
        foreach (self::INDOOR_SPECS as $prefix => $spec) {
            if (str_starts_with($indoor, $prefix)) {
                if (isset($spec['dim'])) {
                    return $spec['dim'];
                }
                // Size-based: extract capacity number
                preg_match('/(\d+)/', substr($indoor, strlen($prefix)), $m);
                $capacity = (int) ($m[1] ?? 0);
                if ($capacity <= 100) {
                    return $spec['dim_sm'] ?? ($spec['dim'] ?? null);
                }
                return $spec['dim_lg'] ?? ($spec['dim_sm'] ?? null);
            }
        }
        return null;
    }

    private function lookupIndoorWeight(string $indoor): ?float
    {
        foreach (['FDA125', 'FDA200'] as $special) {
            if (str_starts_with($indoor, $special)) {
                return self::INDOOR_SPECS[$special]['wt'];
            }
        }

        foreach (self::INDOOR_SPECS as $prefix => $spec) {
            if (str_starts_with($indoor, $prefix)) {
                if (isset($spec['wt'])) {
                    return $spec['wt'];
                }
                preg_match('/(\d+)/', substr($indoor, strlen($prefix)), $m);
                $capacity = (int) ($m[1] ?? 0);
                if ($capacity <= 100) {
                    return $spec['wt_sm'] ?? ($spec['wt'] ?? null);
                }
                return $spec['wt_lg'] ?? ($spec['wt_sm'] ?? null);
            }
        }
        return null;
    }

    private function orderSpecs(array $specs): array
    {
        $order = [
            'indoor_model', 'outdoor_model', 'compressor',
            'seer', 'scop', 'cspf', 'heating_kw',
            'power_consumption_kw', 'esp',
            'noise_indoor', 'noise_outdoor',
            'indoor_weight', 'outdoor_weight',
            'pipe_liquid', 'pipe_gas', 'pipe_max_length', 'height_diff',
            'fan_type', 'source_catalogue',
        ];

        $ordered = [];
        foreach ($order as $key) {
            if (isset($specs[$key])) {
                $ordered[$key] = $specs[$key];
                unset($specs[$key]);
            }
        }
        // Append remaining specs not in order list
        foreach ($specs as $k => $v) {
            $ordered[$k] = $v;
        }
        return $ordered;
    }
}
