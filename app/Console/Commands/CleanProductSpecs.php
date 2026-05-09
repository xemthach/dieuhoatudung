<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Product\ProductImportMapper;
use Illuminate\Console\Command;

class CleanProductSpecs extends Command
{
    protected $signature = 'product:clean-specs
        {--dry-run : Preview changes without saving}
        {--verbose-output : Show detailed per-product output}';

    protected $description = 'Clean specs_json: move standard fields to DB columns, remove duplicates, metadata, and normalize cooling_type';

    public function handle(): int
    {
        $mapper = new ProductImportMapper();
        $dryRun = $this->option('dry-run');
        $verbose = $this->option('verbose-output');

        $this->info($dryRun ? '=== DRY RUN ===' : '=== Cleaning product specs ===');

        $products = Product::all();
        $this->info("Processing {$products->count()} products");

        $totalMoved = 0;
        $totalCleaned = 0;
        $totalCoolingFixed = 0;

        foreach ($products as $product) {
            $changed = false;

            // 1. Normalize cooling_type values
            $rawCooling = $product->getRawOriginal('cooling_type');
            if ($rawCooling && !in_array($rawCooling, ['1_chieu', '2_chieu'])) {
                $normalized = $mapper->normalizeCoolingType($rawCooling);
                if ($normalized !== $rawCooling) {
                    $product->cooling_type = $normalized;
                    $totalCoolingFixed++;
                    $changed = true;
                    if ($verbose) {
                        $this->line("  [{$product->model_code}] cooling_type: <comment>{$rawCooling}</comment> → <info>{$normalized}</info>");
                    }
                }
            }

            // 2. Clean specs_json
            if (is_array($product->specs_json) && !empty($product->specs_json)) {
                $result = $mapper->cleanSpecs($product);
                $moved = $result['moved'];
                $cleanedSpecs = $result['cleaned_specs'];

                if (!empty($moved)) {
                    $totalMoved += count($moved);
                    $changed = true;
                    foreach ($moved as $key => $info) {
                        $this->line("  [{$product->model_code}] Moved <info>{$key}</info> → <comment>{$info['to']}</comment> = {$info['value']}");
                    }
                }

                // Check if specs actually changed
                $currentFlat = $mapper->flattenSpecs($product->specs_json);
                if ($cleanedSpecs !== $currentFlat || !empty($moved)) {
                    $product->specs_json = empty($cleanedSpecs) ? null : $mapper->toRepeaterFormat($cleanedSpecs);
                    $changed = true;
                    $totalCleaned++;
                }
            }

            // 3. Save
            if ($changed && !$dryRun) {
                $product->save();
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Products processed', $totalCleaned],
                ['Fields moved to DB columns', $totalMoved],
                ['cooling_type normalized', $totalCoolingFixed],
            ]
        );

        if ($dryRun) {
            $this->warn('No changes saved (dry-run mode). Remove --dry-run to apply.');
        } else {
            $this->info('All changes saved successfully.');
        }

        return self::SUCCESS;
    }

    /**
     * Expose normalizeCoolingType from mapper for the command.
     */
    private function normalizeCoolingType(string $value): ?string
    {
        return (new ProductImportMapper())->normalizeCoolingType($value);
    }
}
