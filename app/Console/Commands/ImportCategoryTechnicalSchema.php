<?php

namespace App\Console\Commands;

use App\Models\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportCategoryTechnicalSchema extends Command
{
    protected $signature = 'products:import-category-technical-schema
        {path : Path to a JSON file containing category schema records}
        {--dry-run : Preview changes without writing to DB}
        {--report : Write a JSON report to storage/app/reports}';

    protected $description = 'Import category technical schema from a local JSON file without external sources';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON file: '.json_last_error_msg());

            return self::FAILURE;
        }

        $records = $decoded['categories'] ?? $decoded;
        if (! is_array($records)) {
            $this->error('Schema file must contain an array or a {categories: [...]} object.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                $rows[] = [
                    'index' => $index,
                    'status' => 'invalid_record',
                    'message' => 'Record must be an object/array.',
                ];

                continue;
            }

            $category = $this->resolveCategory($record);
            if (! $category) {
                $rows[] = [
                    'index' => $index,
                    'status' => 'category_not_found',
                    'message' => 'Could not resolve category by id/slug/name.',
                ];

                continue;
            }

            $schema = $this->extractSchemaPayload($record);
            if ($schema === null) {
                $rows[] = [
                    'index' => $index,
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'status' => 'missing_schema_payload',
                    'message' => 'No technical_schema_json payload provided.',
                ];

                continue;
            }

            $update = [
                'technical_schema_status' => $record['technical_schema_status'] ?? $category->technical_schema_status ?? 'draft',
                'technical_schema_version' => $record['technical_schema_version'] ?? $category->technical_schema_version,
                'technical_schema_json' => $schema,
                'technical_schema_notes' => $record['technical_schema_notes'] ?? $category->technical_schema_notes,
            ];

            $rows[] = [
                'index' => $index,
                'category_id' => $category->id,
                'category_name' => $category->name,
                'status' => $dryRun ? 'preview' : 'ready',
                'before' => [
                    'technical_schema_status' => $category->technical_schema_status,
                    'technical_schema_version' => $category->technical_schema_version,
                    'technical_schema_json' => $category->technical_schema_json,
                    'technical_schema_notes' => $category->technical_schema_notes,
                ],
                'after' => $update,
            ];

            if (! $dryRun) {
                $category->update($update);
            }
        }

        $this->newLine();
        $this->info($dryRun ? '=== Category Schema Import Preview ===' : '=== Category Schema Import ===');
        $this->table(
            ['Index', 'Category', 'Status', 'Message'],
            collect($rows)->map(fn (array $row) => [
                $row['index'] ?? '-',
                $row['category_name'] ?? ($row['status'] === 'category_not_found' ? 'not found' : '-'),
                $row['status'] ?? '-',
                $row['message'] ?? (($row['status'] ?? '') === 'preview' ? 'ready for apply' : 'updated'),
            ])->values()->all()
        );

        if ($this->option('report')) {
            $path = $this->writeReport([
                'generated_at' => now()->toIso8601String(),
                'dry_run' => $dryRun,
                'rows' => $rows,
            ]);

            $this->info("Report written: {$path}");
        }

        return self::SUCCESS;
    }

    private function resolveCategory(array $record): ?ProductCategory
    {
        if (isset($record['id']) && is_numeric($record['id'])) {
            return ProductCategory::query()->find((int) $record['id']);
        }

        foreach (['slug', 'name'] as $field) {
            if (! empty($record[$field])) {
                $category = ProductCategory::query()->where($field, (string) $record[$field])->first();
                if ($category) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function extractSchemaPayload(array $record): ?array
    {
        $payload = $record['technical_schema_json'] ?? $record['schema'] ?? null;

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        return is_array($payload) ? $payload : null;
    }

    private function writeReport(array $payload): string
    {
        $timestamp = now()->format('Ymd_His');
        $path = "reports/category-technical-schema-import-{$timestamp}.json";
        Storage::disk('local')->put(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        return storage_path('app/'.$path);
    }
}
