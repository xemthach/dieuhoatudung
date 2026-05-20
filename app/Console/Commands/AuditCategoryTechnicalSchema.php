<?php

namespace App\Console\Commands;

use App\Models\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditCategoryTechnicalSchema extends Command
{
    protected $signature = 'products:audit-category-technical-schema
        {--report : Write a JSON report to storage/app/reports}
        {--format=json : Report format when --report is set}';

    protected $description = 'Audit category technical schema coverage and structure without modifying data';

    public function handle(): int
    {
        $categories = ProductCategory::query()->withCount('products')->orderBy('name')->get();

        $summary = [
            'total_categories' => $categories->count(),
            'schema_missing' => 0,
            'schema_active' => 0,
            'schema_locked' => 0,
            'schema_draft' => 0,
            'schema_issues' => 0,
            'schema_ready' => 0,
        ];

        $rows = [];

        foreach ($categories as $category) {
            $issues = $category->technicalSchemaIssues();
            $status = $category->technicalSchemaStatus();

            $summary['schema_'.$status] = ($summary['schema_'.$status] ?? 0) + 1;
            $summary['schema_issues'] += $issues === [] ? 0 : 1;
            $summary['schema_ready'] += $category->hasTechnicalSchema() ? 1 : 0;

            $rows[] = [
                'category_id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $status,
                'products' => (int) $category->products_count,
                'summary' => $category->technicalSchemaSummary(),
                'issues' => $issues,
                'has_schema' => $category->hasTechnicalSchema(),
            ];
        }

        $this->newLine();
        $this->info('=== Category Technical Schema Audit ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total categories', $summary['total_categories']],
                ['Schema ready', $summary['schema_ready']],
                ['Schema missing', $summary['schema_missing']],
                ['Schema draft', $summary['schema_draft']],
                ['Schema active', $summary['schema_active']],
                ['Schema locked', $summary['schema_locked']],
                ['Categories with issues', $summary['schema_issues']],
            ]
        );

        $problemRows = collect($rows)
            ->filter(fn (array $row): bool => $row['issues'] !== [] || ! $row['has_schema'])
            ->values()
            ->all();

        if ($problemRows !== []) {
            $this->line('');
            $this->warn('Categories needing cleanup');
            $this->table(
                ['ID', 'Category', 'Status', 'Products', 'Issues'],
                collect($problemRows)->map(fn (array $row) => [
                    $row['category_id'],
                    $row['name'],
                    $row['status'],
                    $row['products'],
                    implode(', ', $row['issues']) ?: 'schema_missing_or_empty',
                ])->values()->all()
            );
        }

        if ($this->option('report')) {
            $path = $this->writeReport([
                'generated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'rows' => $rows,
            ], strtolower((string) $this->option('format')));

            $this->info("Report written: {$path}");
        }

        return self::SUCCESS;
    }

    private function writeReport(array $payload, string $format): string
    {
        $timestamp = now()->format('Ymd_His');
        $directory = 'reports';

        if ($format === 'csv') {
            $path = "{$directory}/category-technical-schema-audit-{$timestamp}.csv";
            Storage::disk('local')->put($path, $this->toCsv($payload['rows'] ?? []));

            return storage_path('app/'.$path);
        }

        $path = "{$directory}/category-technical-schema-audit-{$timestamp}.json";
        Storage::disk('local')->put(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        return storage_path('app/'.$path);
    }

    private function toCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['category_id', 'name', 'slug', 'status', 'products', 'summary', 'issues']);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['category_id'] ?? null,
                $row['name'] ?? null,
                $row['slug'] ?? null,
                $row['status'] ?? null,
                $row['products'] ?? null,
                $row['summary'] ?? null,
                implode(', ', $row['issues'] ?? []),
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
