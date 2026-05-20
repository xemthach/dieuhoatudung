<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\ProductImportMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FixTechnicalSpecs extends Command
{
    protected $signature = 'products:fix-technical-specs
        {--dry-run : Preview changes without writing to DB}
        {--report : Write a JSON report to storage/app/reports}
        {--category= : Limit to a category ID, slug, or name}
        {--brand= : Limit to a brand ID, slug, or name}';

    protected $description = 'Preview technical spec normalization against category schema without changing data';

    public function handle(ProductImportMapper $mapper): int
    {
        if (! $this->option('dry-run')) {
            $this->error('This command is preview-only. Use --dry-run to generate a fix proposal.');

            return self::FAILURE;
        }

        $query = Product::query()->with(['category', 'brand']);
        $this->applyFilters($query);

        $products = $query->get();
        $rows = [];
        $summary = [
            'total_products' => $products->count(),
            'preview_changes' => 0,
            'category_schema_missing' => 0,
            'products_unchanged' => 0,
        ];

        foreach ($products as $product) {
            $row = $this->previewProduct($product, $mapper);
            $rows[] = $row;

            if ($row['validation_status'] === 'category_schema_missing') {
                $summary['category_schema_missing']++;
            }

            if ($row['proposed_changes'] !== []) {
                $summary['preview_changes']++;
            } else {
                $summary['products_unchanged']++;
            }
        }

        $this->newLine();
        $this->info('=== Technical Specs Fix (Dry Run) ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total products', $summary['total_products']],
                ['Products with proposed changes', $summary['preview_changes']],
                ['Category schema missing', $summary['category_schema_missing']],
                ['Unchanged', $summary['products_unchanged']],
            ]
        );

        $problemRows = collect($rows)
            ->filter(fn (array $row): bool => $row['proposed_changes'] !== [] || $row['validation_status'] !== 'correct')
            ->take(20)
            ->values()
            ->all();

        if ($problemRows !== []) {
            $this->line('');
            $this->warn('Proposed changes');
            $this->table(
                ['ID', 'Model', 'Category', 'Status', 'Changes'],
                collect($problemRows)->map(fn (array $row) => [
                    $row['product_id'],
                    $row['model'],
                    $row['assigned_category'] ?: 'unassigned',
                    $row['validation_status'],
                    implode(' | ', $row['proposed_changes']) ?: 'none',
                ])->values()->all()
            );
        }

        if ($this->option('report')) {
            $path = $this->writeReport([
                'generated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'rows' => $rows,
                'filters' => [
                    'category' => $this->option('category'),
                    'brand' => $this->option('brand'),
                ],
            ]);

            $this->info("Report written: {$path}");
        }

        return self::SUCCESS;
    }

    private function applyFilters($query): void
    {
        $category = trim((string) $this->option('category'));
        if ($category !== '') {
            $query->where(function ($builder) use ($category): void {
                if (ctype_digit($category)) {
                    $builder->where('product_category_id', (int) $category);

                    return;
                }

                $builder->whereHas('category', function ($categoryQuery) use ($category): void {
                    $categoryQuery->where('slug', $category)
                        ->orWhere('name', $category);
                });
            });
        }

        $brand = trim((string) $this->option('brand'));
        if ($brand !== '') {
            $query->where(function ($builder) use ($brand): void {
                if (ctype_digit($brand)) {
                    $builder->where('brand_id', (int) $brand);

                    return;
                }

                $builder->whereHas('brand', function ($brandQuery) use ($brand): void {
                    $brandQuery->where('slug', $brand)
                        ->orWhere('name', $brand);
                });
            });
        }
    }

    private function previewProduct(Product $product, ProductImportMapper $mapper): array
    {
        $category = $product->category;
        $assignedCategory = $category?->name;
        $currentSpecs = $mapper->flattenSpecs((array) $product->specs_json);

        if (! $category) {
            return $this->buildRow(
                $product,
                $assignedCategory,
                'wrong_category_mapping',
                [],
                ['product has no assigned category']
            );
        }

        if (! $category->hasTechnicalSchema()) {
            return $this->buildRow(
                $product,
                $assignedCategory,
                'category_schema_missing',
                [],
                ['category technical schema missing']
            );
        }

        $allowed = $category->technicalSchemaPermittedFields();
        $aliases = $category->technicalSchemaFieldAliases();
        $normalized = [];
        $proposedChanges = [];

        foreach ($currentSpecs as $key => $value) {
            $canonicalKey = $category->normalizeTechnicalSchemaKey((string) $key);

            if (! in_array($canonicalKey, $allowed, true)) {
                $proposedChanges[] = "remove {$key}";
                continue;
            }

            if ($canonicalKey !== $key) {
                $proposedChanges[] = "rename {$key} -> {$canonicalKey}";
            }

            $normalized[$canonicalKey] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($normalized === $currentSpecs) {
            $status = 'correct';
        } elseif ($proposedChanges === []) {
            $status = 'correct';
        } else {
            $status = 'mismatch';
        }

        return $this->buildRow(
            $product,
            $assignedCategory,
            $status,
            $proposedChanges,
            $status === 'correct' ? ['no preview changes'] : array_values(array_unique($proposedChanges))
        );
    }

    private function buildRow(Product $product, ?string $assignedCategory, string $status, array $proposedChanges, array $details): array
    {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'brand' => $product->brand?->name,
            'model' => $product->model_code,
            'sku' => $product->sku,
            'assigned_category' => $assignedCategory,
            'validation_status' => $status,
            'proposed_changes' => $proposedChanges,
            'details' => $details,
        ];
    }

    private function writeReport(array $payload): string
    {
        $timestamp = now()->format('Ymd_His');
        $path = "reports/technical-specs-fix-dry-run-{$timestamp}.json";
        Storage::disk('local')->put(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        return storage_path('app/'.$path);
    }
}
