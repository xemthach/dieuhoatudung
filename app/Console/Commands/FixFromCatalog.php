<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSpecsSnapshot;
use App\Services\Catalog\CatalogSpecAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FixFromCatalog extends Command
{
    protected $signature = 'products:fix-from-catalog
        {--dry-run : Preview catalog-sourced fixes without writing product specs}
        {--report : Write a JSON report to storage/app/reports}
        {--snapshot : Store product spec snapshots for rollback planning}
        {--brand= : Limit to a brand ID, slug, or name}
        {--category= : Limit to a category ID, slug, or name}';

    protected $description = 'Preview product spec corrections from verified catalog records';

    public function handle(CatalogSpecAuditor $auditor): int
    {
        if (! $this->option('dry-run')) {
            $this->error('No auto-fix is allowed. Re-run with --dry-run to generate a proposal.');

            return self::FAILURE;
        }

        $query = Product::query()->with(['brand', 'category', 'catalogSource', 'catalogModel']);
        $this->applyFilters($query);

        $rows = [];
        foreach ($query->get() as $product) {
            $audit = $auditor->audit($product);
            if ($this->option('snapshot')) {
                $this->snapshot($product);
            }

            $changes = collect($audit['items'])
                ->filter(fn (array $item) => in_array($item['validation_status'], ['mismatched_value', 'wrong_unit', 'product_missing_specs', 'suspicious_ai_generated', 'product_extra_specs'], true))
                ->map(fn (array $item) => [
                    'product_id' => $audit['product_id'],
                    'field' => $item['field_key'],
                    'old_value' => $item['product_value'],
                    'catalog_value' => $item['catalog_value'],
                    'source_catalog' => $audit['catalog_source'],
                    'source_page' => $item['source_page'],
                    'risk_level' => $item['risk_level'],
                    'action' => $this->actionFor($item['validation_status']),
                ])
                ->values()
                ->all();

            if ($changes !== []) {
                $rows[] = [
                    'product_id' => $audit['product_id'],
                    'model' => $audit['model'],
                    'catalog_model' => $audit['catalog_model'],
                    'validation_status' => $audit['validation_status'],
                    'changes' => $changes,
                ];
            }
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'dry_run' => true,
            'auto_fix_applied' => false,
            'snapshot_created' => (bool) $this->option('snapshot'),
            'rows' => $rows,
        ];

        $this->newLine();
        $this->info('=== Fix From Catalog (Dry Run) ===');
        $this->table(['Metric', 'Count'], [
            ['Products with proposed changes', count($rows)],
            ['Field-level changes', collect($rows)->sum(fn (array $row) => count($row['changes']))],
            ['Auto-fix applied', 0],
        ]);

        if ($rows !== []) {
            $this->warn('Proposed changes');
            $this->table(
                ['Product ID', 'Field', 'Old Value', 'Catalog Value', 'Source Catalog', 'Page', 'Risk'],
                collect($rows)
                    ->flatMap(fn (array $row) => $row['changes'])
                    ->take(30)
                    ->map(fn (array $change) => [
                        $change['product_id'],
                        $change['field'],
                        $change['old_value'],
                        $change['catalog_value'],
                        $change['source_catalog'],
                        $change['source_page'],
                        $change['risk_level'],
                    ])
                    ->values()
                    ->all()
            );
        }

        if ($this->option('report')) {
            $path = $this->writeReport($payload);
            $this->info("Report written: {$path}");
        }

        return self::SUCCESS;
    }

    private function snapshot(Product $product): void
    {
        ProductSpecsSnapshot::query()->create([
            'product_id' => $product->id,
            'snapshot_json' => [
                'btu' => $product->btu,
                'capacity_kw' => $product->capacity_kw,
                'hp' => $product->hp,
                'inverter' => $product->inverter,
                'cooling_type' => $product->cooling_type,
                'voltage' => $product->voltage,
                'refrigerant_gas' => $product->refrigerant_gas,
                'power_consumption' => $product->power_consumption,
                'airflow' => $product->airflow,
                'noise_level' => $product->noise_level,
                'indoor_dimensions' => $product->indoor_dimensions,
                'outdoor_dimensions' => $product->outdoor_dimensions,
                'weight' => $product->weight,
                'recommended_area' => $product->recommended_area,
                'specs_json' => $product->specs_json,
            ],
            'reason' => 'products:fix-from-catalog --dry-run rollback snapshot',
            'created_by' => auth()->id(),
        ]);
    }

    private function actionFor(string $status): string
    {
        return match ($status) {
            'product_missing_specs' => 'backfill_from_catalog',
            'product_extra_specs', 'suspicious_ai_generated' => 'remove_or_quarantine',
            default => 'replace_with_catalog_value',
        };
    }

    private function applyFilters($query): void
    {
        foreach (['brand' => 'brand', 'category' => 'category'] as $option => $relation) {
            $value = trim((string) $this->option($option));
            if ($value === '') {
                continue;
            }

            $column = $option === 'brand' ? 'brand_id' : 'product_category_id';
            $query->where(function ($builder) use ($value, $relation, $column): void {
                if (ctype_digit($value)) {
                    $builder->where($column, (int) $value);

                    return;
                }

                $builder->whereHas($relation, fn ($relationQuery) => $relationQuery
                    ->where('slug', $value)
                    ->orWhere('name', $value));
            });
        }
    }

    private function writeReport(array $payload): string
    {
        $path = 'reports/catalog-fix-dry-run-'.now()->format('Ymd_His').'.json';
        Storage::disk('local')->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return storage_path('app/'.$path);
    }
}
