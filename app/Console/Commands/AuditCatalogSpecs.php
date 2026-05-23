<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCatalogAuditItem;
use App\Models\ProductCatalogAuditRun;
use App\Services\Catalog\CatalogSpecAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditCatalogSpecs extends Command
{
    protected $signature = 'products:audit-catalog-specs
        {--report : Write a JSON report to storage/app/reports}
        {--persist : Persist audit run/items to DB}
        {--brand= : Limit to a brand ID, slug, or name}
        {--category= : Limit to a category ID, slug, or name}';

    protected $description = 'Audit product technical specs against imported catalog records without modifying product data';

    public function handle(CatalogSpecAuditor $auditor): int
    {
        $query = Product::query()->with(['brand', 'category', 'catalogSource', 'catalogModel']);
        $this->applyFilters($query);

        $rows = [];
        $summary = array_fill_keys(CatalogSpecAuditor::STATUSES, 0);
        $summary['total_products'] = 0;

        foreach ($query->get() as $product) {
            $row = $auditor->audit($product);
            $rows[] = $row;
            $summary['total_products']++;
            $summary[$row['validation_status']] = ($summary[$row['validation_status']] ?? 0) + 1;
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'rows' => $rows,
            'filters' => [
                'brand' => $this->option('brand'),
                'category' => $this->option('category'),
            ],
            'source_policy' => [
                'source_of_truth' => 'catalog_imports',
                'external_sources_used' => false,
                'auto_fix_applied' => false,
            ],
        ];

        if ($this->option('persist')) {
            $this->persistAudit($payload);
        }

        $this->newLine();
        $this->info('=== Product vs Catalog Specs Audit ===');
        $this->table(['Metric', 'Count'], collect($summary)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        $problems = collect($rows)
            ->reject(fn (array $row) => $row['validation_status'] === 'correct')
            ->take(20)
            ->map(fn (array $row) => [
                $row['product_id'],
                $row['model'],
                $row['catalog_model'] ?? 'unmatched',
                $row['validation_status'],
                $row['risk_level'],
            ])
            ->values()
            ->all();

        if ($problems !== []) {
            $this->warn('Detailed mismatch sample');
            $this->table(['Product ID', 'Model', 'Catalog Model', 'Status', 'Risk'], $problems);
        }

        if ($this->option('report')) {
            $path = $this->writeReport($payload);
            $this->info("Report written: {$path}");
        }

        return self::SUCCESS;
    }

    private function persistAudit(array $payload): void
    {
        $run = ProductCatalogAuditRun::query()->create([
            'status' => 'completed',
            'summary_json' => $payload['summary'],
            'filters_json' => $payload['filters'],
            'created_by' => auth()->id(),
        ]);

        foreach ($payload['rows'] as $row) {
            foreach ($row['items'] ?: [[
                'validation_status' => $row['validation_status'],
                'field_key' => null,
                'product_value' => null,
                'catalog_value' => null,
                'product_unit' => null,
                'catalog_unit' => null,
                'source_page' => null,
                'risk_level' => $row['risk_level'],
                'details' => $row['details'] ?? [],
            ]] as $item) {
                ProductCatalogAuditItem::query()->create([
                    'audit_run_id' => $run->id,
                    'product_id' => $row['product_id'],
                    'catalog_source_id' => $row['catalog_source_id'],
                    'catalog_model_id' => $row['catalog_model_id'],
                    'validation_status' => $item['validation_status'],
                    'field_key' => $item['field_key'],
                    'product_value' => $item['product_value'],
                    'catalog_value' => $item['catalog_value'],
                    'product_unit' => $item['product_unit'],
                    'catalog_unit' => $item['catalog_unit'],
                    'source_page' => $item['source_page'],
                    'risk_level' => $item['risk_level'],
                    'details_json' => $item['details'] ?? [],
                ]);
            }
        }
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
        $path = 'reports/catalog-specs-audit-'.now()->format('Ymd_His').'.json';
        Storage::disk('local')->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return storage_path('app/'.$path);
    }
}
