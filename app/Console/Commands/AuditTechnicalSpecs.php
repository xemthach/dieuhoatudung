<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\ProductImportMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuditTechnicalSpecs extends Command
{
    protected $signature = 'products:audit-technical-specs
        {--report : Write a JSON report to storage/app/reports}
        {--format=json : Report format when --report is set}
        {--category= : Limit to a category ID, slug, or name}
        {--brand= : Limit to a brand ID, slug, or name}';

    protected $description = 'Audit products against category technical schema without modifying data';

    public function handle(ProductImportMapper $mapper): int
    {
        $query = Product::query()->with(['category', 'brand']);
        $this->applyFilters($query);

        $products = $query->get();

        $summary = [
            'total_products' => $products->count(),
            'correct' => 0,
            'category_schema_missing' => 0,
            'wrong_category_mapping' => 0,
            'missing_required_specs' => 0,
            'extra_specs_not_allowed' => 0,
            'wrong_unit' => 0,
            'wrong_format' => 0,
            'suspicious_ai_generated' => 0,
        ];

        $categoryStats = [];
        $brandStats = [];
        $criticalRows = [];
        $rows = [];

        foreach ($products as $product) {
            $row = $this->evaluateProduct($product, $mapper);
            $rows[] = $row;

            $status = $row['validation_status'];
            $summary[$status] = ($summary[$status] ?? 0) + 1;

            $categoryKey = (string) ($row['assigned_category'] ?: 'unassigned');
            $brandKey = (string) ($row['brand'] ?: 'unassigned');

            $categoryStats[$categoryKey] ??= [
                'total' => 0,
                'correct' => 0,
                'category_schema_missing' => 0,
                'wrong_category_mapping' => 0,
                'missing_required_specs' => 0,
                'extra_specs_not_allowed' => 0,
                'wrong_unit' => 0,
                'wrong_format' => 0,
                'suspicious_ai_generated' => 0,
            ];

            $brandStats[$brandKey] ??= [
                'total' => 0,
                'correct' => 0,
                'category_schema_missing' => 0,
                'wrong_category_mapping' => 0,
                'missing_required_specs' => 0,
                'extra_specs_not_allowed' => 0,
                'wrong_unit' => 0,
                'wrong_format' => 0,
                'suspicious_ai_generated' => 0,
            ];

            $categoryStats[$categoryKey]['total']++;
            $brandStats[$brandKey]['total']++;

            if (isset($categoryStats[$categoryKey][$status])) {
                $categoryStats[$categoryKey][$status]++;
            }

            if (isset($brandStats[$brandKey][$status])) {
                $brandStats[$brandKey][$status]++;
            }

            if ($row['severity'] === 'Critical') {
                $criticalRows[] = $row;
            }
        }

        $this->newLine();
        $this->info('=== Technical Specs Audit ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total products', $summary['total_products']],
                ['Correct', $summary['correct']],
                ['Category schema missing', $summary['category_schema_missing']],
                ['Wrong category mapping', $summary['wrong_category_mapping']],
                ['Missing required specs', $summary['missing_required_specs']],
                ['Extra specs not allowed', $summary['extra_specs_not_allowed']],
                ['Wrong unit', $summary['wrong_unit']],
                ['Wrong format', $summary['wrong_format']],
                ['Suspicious AI generated', $summary['suspicious_ai_generated']],
            ]
        );

        $this->line('');
        $this->info('By category');
        $this->table(
            ['Category', 'Total', 'Correct', 'Missing schema', 'Mismatch', 'Missing', 'Extra'],
            collect($categoryStats)
                ->sortKeys()
                ->map(fn (array $stats, string $category) => [
                    $category,
                    $stats['total'],
                    $stats['correct'],
                    $stats['category_schema_missing'],
                    $stats['wrong_category_mapping'] + $stats['wrong_unit'] + $stats['wrong_format'] + $stats['suspicious_ai_generated'],
                    $stats['missing_required_specs'],
                    $stats['extra_specs_not_allowed'],
                ])->values()->all()
        );

        $this->line('');
        $this->info('By brand');
        $this->table(
            ['Brand', 'Total', 'Correct', 'Mismatch'],
            collect($brandStats)
                ->sortKeys()
                ->map(fn (array $stats, string $brand) => [
                    $brand,
                    $stats['total'],
                    $stats['correct'],
                    $stats['category_schema_missing'] + $stats['wrong_category_mapping'] + $stats['missing_required_specs'] + $stats['extra_specs_not_allowed'] + $stats['wrong_unit'] + $stats['wrong_format'] + $stats['suspicious_ai_generated'],
                ])->values()->all()
        );

        if ($criticalRows !== []) {
            $this->line('');
            $this->warn('Top critical errors');
            $this->table(
                ['Product ID', 'Model', 'Category', 'Status', 'Severity'],
                collect($criticalRows)
                    ->take(10)
                    ->map(fn (array $row) => [
                        $row['product_id'],
                        $row['model'],
                        $row['assigned_category'] ?: 'unassigned',
                        $row['validation_status'],
                        $row['severity'],
                    ])->values()->all()
            );
        }

        if ($this->option('report')) {
            $format = strtolower((string) $this->option('format'));
            $path = $this->writeReport($format, [
                'generated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'by_category' => $categoryStats,
                'by_brand' => $brandStats,
                'critical_rows' => $criticalRows,
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

    private function evaluateProduct(Product $product, ProductImportMapper $mapper): array
    {
        $category = $product->category;
        $currentSpecs = $this->collectCurrentTechnicalSpecs($product, $mapper);
        $assignedCategory = $category?->name;

        if (! $category) {
            return $this->buildRow(
                product: $product,
                assignedCategory: null,
                currentSpecs: $currentSpecs,
                validationStatus: 'wrong_category_mapping',
                mismatchDetails: ['product has no assigned category'],
                suggestedFix: 'Assign the product to a real category before schema validation.'
            );
        }

        if (! $category->hasTechnicalSchema()) {
            return $this->buildRow(
                product: $product,
                assignedCategory: $assignedCategory,
                currentSpecs: $currentSpecs,
                validationStatus: 'category_schema_missing',
                mismatchDetails: ['category technical schema is missing or inactive'],
                suggestedFix: 'Add technical_schema_json and activate the category schema before product validation.'
            );
        }

        $schema = $category->technicalSchema();
        $definitions = $category->technicalSchemaFieldDefinitions();
        $allowedFields = $category->technicalSchemaPermittedFields();
        $requiredFields = array_values(array_unique(array_filter(array_merge(
            $category->technicalSchemaRequiredFields(),
            array_map(fn (array $definition) => ($definition['required'] ?? false) ? ($definition['key'] ?? null) : null, $definitions)
        ))));

        $normalizedCurrent = [];
        foreach ($currentSpecs as $key => $value) {
            $normalizedCurrent[$category->normalizeTechnicalSchemaKey((string) $key)] = $value;
        }

        $missingRequired = array_values(array_filter($requiredFields, fn (string $field) => ! array_key_exists($field, $normalizedCurrent)));
        $extraSpecs = array_values(array_filter(array_keys($normalizedCurrent), fn (string $field) => ! in_array($field, $allowedFields, true)));
        $wrongUnits = $this->detectWrongUnits($category, $normalizedCurrent, $definitions);
        $wrongFormats = $this->detectWrongFormats($normalizedCurrent);
        $suspicious = $this->detectSuspiciousSpecs($normalizedCurrent);

        $status = 'correct';
        $severity = 'Low';
        $mismatchDetails = [];

        if ($missingRequired !== []) {
            $status = 'missing_required_specs';
            $severity = 'High';
            $mismatchDetails[] = 'missing required: '.implode(', ', $missingRequired);
        }

        if ($extraSpecs !== []) {
            $status = $status === 'correct' ? 'extra_specs_not_allowed' : $status;
            $severity = $severity === 'Low' ? 'Medium' : $severity;
            $mismatchDetails[] = 'extra fields: '.implode(', ', $extraSpecs);
        }

        if ($wrongUnits !== []) {
            $status = 'wrong_unit';
            $severity = 'Critical';
            $mismatchDetails[] = 'unit mismatch: '.implode('; ', $wrongUnits);
        }

        if ($wrongFormats !== []) {
            $status = $status === 'correct' ? 'wrong_format' : $status;
            $severity = $severity === 'Low' ? 'Medium' : $severity;
            $mismatchDetails[] = 'format issues: '.implode('; ', $wrongFormats);
        }

        if ($suspicious !== []) {
            $status = $status === 'correct' ? 'suspicious_ai_generated' : $status;
            $severity = $severity === 'Low' ? 'Medium' : $severity;
            $mismatchDetails[] = 'suspicious values: '.implode('; ', $suspicious);
        }

        if ($status === 'correct') {
            $mismatchDetails[] = 'schema matched current technical specs';
        }

        return $this->buildRow(
            product: $product,
            assignedCategory: $assignedCategory,
            currentSpecs: $currentSpecs,
            validationStatus: $status,
            mismatchDetails: $mismatchDetails,
            suggestedFix: $this->suggestFix($status, $category->technicalSchemaSummary(), $missingRequired, $extraSpecs, $wrongUnits, $wrongFormats, $suspicious),
            severity: $severity,
            expectedCategorySchema: $category->technicalSchemaSummary(),
        );
    }

    private function collectCurrentTechnicalSpecs(Product $product, ProductImportMapper $mapper): array
    {
        $specs = [];

        foreach (ProductImportMapper::standardColumns() as $column) {
            $value = $product->getAttribute($column);
            if ($value !== null && $value !== '') {
                $specs[$column] = $value;
            }
        }

        foreach ($mapper->flattenSpecs((array) $product->specs_json) as $key => $value) {
            if ($value !== null && $value !== '') {
                $specs[(string) $key] = $value;
            }
        }

        return $specs;
    }

    private function detectWrongUnits(ProductCategory $category, array $currentSpecs, array $definitions): array
    {
        $wrongUnits = [];

        foreach ($definitions as $definition) {
            $key = $definition['key'] ?? null;
            $expectedUnit = $definition['unit'] ?? null;

            if (! is_string($key) || ! is_string($expectedUnit) || $expectedUnit === '') {
                continue;
            }

            $value = $currentSpecs[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $actualUnit = $this->extractUnitFromValue($value);
            if ($actualUnit !== null && $actualUnit !== $expectedUnit) {
                $wrongUnits[] = "{$key}: expected {$expectedUnit}, got {$actualUnit}";
            }
        }

        return $wrongUnits;
    }

    private function detectWrongFormats(array $currentSpecs): array
    {
        $issues = [];

        foreach ($currentSpecs as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (preg_match('/\d{1,3}(?:[.,]\d{3})+[.,]?\d*/u', $value)) {
                $issues[] = "{$key}: thousands separator format";
                continue;
            }

            if (preg_match('/\s{2,}/u', $value) || preg_match('/\s\/\s/u', $value)) {
                $issues[] = "{$key}: spacing/segment format";
            }
        }

        return $issues;
    }

    private function detectSuspiciousSpecs(array $currentSpecs): array
    {
        $issues = [];
        $patterns = ['ước tính', 'tham khảo', 'approx', 'maybe', 'about', 'phù hợp', 'lý tưởng'];

        foreach ($currentSpecs as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $normalized = Str::lower($value);
            if (Str::contains($normalized, $patterns)) {
                $issues[] = "{$key}: marketing or estimated wording";
            }
        }

        return $issues;
    }

    private function extractUnitFromValue(string $value): ?string
    {
        if (! preg_match('/\b(BTU|kW|HP|Pa|dB|mm|kg|W|A|V|m2|m²)\b/iu', $value, $match)) {
            return null;
        }

        return mb_strtolower(trim($match[1]));
    }

    private function suggestFix(
        string $status,
        string $schemaSummary,
        array $missingRequired,
        array $extraSpecs,
        array $wrongUnits,
        array $wrongFormats,
        array $suspicious
    ): string {
        return match ($status) {
            'correct' => 'No fix needed. Preserve current specs.',
            'category_schema_missing' => 'Add or activate category technical schema first.',
            'wrong_category_mapping' => 'Reassign the product to the correct category before spec validation.',
            'missing_required_specs' => 'Backfill only from the category schema source; do not infer missing values.',
            'extra_specs_not_allowed' => 'Remove or remap extra specs that are not declared in the category schema.',
            'wrong_unit' => 'Normalize units to the category rule and keep a dry-run log before applying.',
            'wrong_format' => 'Normalize spacing, separators, and format to the category rule.',
            'suspicious_ai_generated' => 'Review the spec source and strip any marketing or estimated wording.',
            default => 'Review category schema alignment.',
        };
    }

    private function buildRow(
        Product $product,
        ?string $assignedCategory,
        array $currentSpecs,
        string $validationStatus,
        array $mismatchDetails,
        string $suggestedFix,
        string $severity = 'Low',
        ?string $expectedCategorySchema = null
    ): array {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'brand' => $product->brand?->name,
            'model' => $product->model_code,
            'sku' => $product->sku,
            'assigned_category' => $assignedCategory,
            'expected_category_schema' => $expectedCategorySchema ?? 'missing',
            'current_technical_specs' => $currentSpecs,
            'validation_status' => $validationStatus,
            'mismatch_details' => $mismatchDetails,
            'severity' => $severity,
            'suggested_fix' => $suggestedFix,
        ];
    }

    private function writeReport(string $format, array $payload): string
    {
        $timestamp = now()->format('Ymd_His');
        $directory = 'reports';

        if ($format === 'csv') {
            $path = "{$directory}/technical-specs-audit-{$timestamp}.csv";
            Storage::disk('local')->put($path, $this->toCsv($payload['rows'] ?? []));

            return storage_path('app/'.$path);
        }

        $path = "{$directory}/technical-specs-audit-{$timestamp}.json";
        Storage::disk('local')->put(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        return storage_path('app/'.$path);
    }

    private function toCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['product_id', 'product_name', 'brand', 'model', 'sku', 'assigned_category', 'validation_status', 'severity', 'suggested_fix']);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['product_id'] ?? null,
                $row['product_name'] ?? null,
                $row['brand'] ?? null,
                $row['model'] ?? null,
                $row['sku'] ?? null,
                $row['assigned_category'] ?? null,
                $row['validation_status'] ?? null,
                $row['severity'] ?? null,
                $row['suggested_fix'] ?? null,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
