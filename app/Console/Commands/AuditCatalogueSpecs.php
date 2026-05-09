<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Product\ProductImportMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditCatalogueSpecs extends Command
{
    protected $signature = 'product:audit-catalogue-specs {--fix : Apply fixes after audit}';
    protected $description = 'Audit all products for spec coverage and data quality';

    public function handle()
    {
        $this->info('=== Product Catalogue Specs Audit ===');
        
        $products = Product::all();
        $this->info("Total products: {$products->count()}");

        $mapper = new ProductImportMapper();
        $lowCoverage = [];
        $criticalMissing = [];
        $duplicateSpecs = [];
        $stdInJson = [];
        $totalStd = 0;
        $totalExtra = 0;

        foreach ($products as $p) {
            $specCount = is_array($p->specs_json) ? count($p->specs_json) : 0;
            $stdFilled = 0;
            $missing = [];
            $stdFields = ['btu','capacity_kw','hp','voltage','refrigerant_gas','power_consumption',
                         'airflow','noise_level','indoor_dimensions','outdoor_dimensions','weight'];
            
            foreach ($stdFields as $f) {
                $v = $p->getRawOriginal($f);
                if (!empty($v) || $v === '0' || $v === 0) {
                    $stdFilled++;
                } else {
                    $missing[] = $f;
                }
            }
            
            $totalStd += $stdFilled;
            $totalExtra += $specCount;

            if (($stdFilled + $specCount) < 10) {
                $lowCoverage[] = $p->model_code;
            }

            if (in_array('btu', $missing) && in_array('capacity_kw', $missing)) {
                $criticalMissing[] = ['model' => $p->model_code, 'missing' => $missing];
            }

            // Check for standard fields in JSON
            if (is_array($p->specs_json)) {
                foreach ($p->specs_json as $spec) {
                    $key = $spec['key'] ?? '';
                    if (isset(ProductImportMapper::FIELD_MAP[$key])) {
                        $stdInJson[] = ['product' => $p->model_code, 'key' => $key];
                    }
                }

                // Check duplicate keys
                $keys = array_column($p->specs_json, 'key');
                $dupes = array_diff_assoc($keys, array_unique($keys));
                if (!empty($dupes)) {
                    $duplicateSpecs[] = ['product' => $p->model_code, 'dupes' => array_values($dupes)];
                }
            }
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Total products', $products->count()],
            ['Total standard fields filled', $totalStd],
            ['Total extra specs', $totalExtra],
            ['Low coverage (<10 data points)', count($lowCoverage)],
            ['Critical missing (no BTU+kW)', count($criticalMissing)],
            ['Std fields in JSON (should migrate)', count($stdInJson)],
            ['Duplicate spec keys', count($duplicateSpecs)],
        ]);

        if (!empty($lowCoverage)) {
            $this->warn("\nLow coverage products:");
            foreach ($lowCoverage as $mc) {
                $this->line("  - {$mc}");
            }
        }

        if (!empty($stdInJson)) {
            $this->warn("\nStandard fields found in JSON (need migration):");
            foreach ($stdInJson as $item) {
                $this->line("  - [{$item['product']}] key={$item['key']}");
            }
        }

        if (!empty($duplicateSpecs)) {
            $this->warn("\nDuplicate spec keys:");
            foreach ($duplicateSpecs as $item) {
                $this->line("  - [{$item['product']}] " . implode(', ', $item['dupes']));
            }
        }

        // Apply fixes if requested
        if ($this->option('fix')) {
            $this->info("\nApplying fixes...");
            $fixed = 0;

            DB::beginTransaction();
            try {
                foreach ($products as $p) {
                    if (!is_array($p->specs_json)) continue;
                    $changed = false;
                    $flat = $mapper->flattenSpecs($p->specs_json);

                    // Move standard fields from JSON to DB columns
                    foreach ($flat as $key => $value) {
                        if (isset(ProductImportMapper::FIELD_MAP[$key])) {
                            $dbCol = ProductImportMapper::FIELD_MAP[$key];
                            $currentVal = $p->getRawOriginal($dbCol);
                            if (empty($currentVal) && $currentVal !== '0') {
                                $p->{$dbCol} = $mapper->castValue($dbCol, $value);
                                $changed = true;
                            }
                            unset($flat[$key]);
                        }
                    }

                    // Deduplicate
                    $p->specs_json = empty($flat) ? null : $mapper->toRepeaterFormat($flat);
                    if ($changed) {
                        $p->save();
                        $fixed++;
                    }
                }

                DB::commit();
                $this->info("Fixed {$fixed} products.");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed: " . $e->getMessage());
            }
        }

        return 0;
    }
}
