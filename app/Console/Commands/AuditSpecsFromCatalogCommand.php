<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\CatalogSpecAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditSpecsFromCatalogCommand extends Command
{
    protected $signature = 'products:audit-specs-from-catalog
        {--dry-run : Required safety flag}
        {--report : Write JSON + CSV report}';

    protected $description = 'Dry-run audit of product specs against catalog sources with per-field diff rows.';

    public function handle(CatalogSpecAuditor $auditor): int
    {
        if (! $this->option('dry-run')) {
            $this->error('Safety guard: run with --dry-run only.');

            return self::FAILURE;
        }

        $rows = [];
        foreach (Product::query()->with(['brand', 'category', 'catalogSource', 'catalogModel'])->get() as $product) {
            $audit = $auditor->audit($product);
            if (($audit['items'] ?? []) === []) {
                $rows[] = [
                    'product_id' => $audit['product_id'],
                    'model' => $audit['model'],
                    'sku' => $audit['sku'],
                    'brand' => $audit['brand'],
                    'catalog_file' => $audit['catalog_source'],
                    'field' => '*',
                    'current_value' => null,
                    'catalog_value' => null,
                    'issue' => $audit['validation_status'],
                    'confidence' => null,
                    'action' => 'skip',
                ];
                continue;
            }

            foreach ($audit['items'] as $item) {
                $rows[] = [
                    'product_id' => $audit['product_id'],
                    'model' => $audit['model'],
                    'sku' => $audit['sku'],
                    'brand' => $audit['brand'],
                    'catalog_file' => $audit['catalog_source'],
                    'field' => $item['field_key'],
                    'current_value' => $item['product_value'],
                    'catalog_value' => $item['catalog_value'],
                    'issue' => $item['validation_status'],
                    'confidence' => $item['details']['confidence'] ?? null,
                    'action' => $this->suggestAction((string) $item['validation_status']),
                ];
            }
        }

        $summary = collect($rows)->groupBy('issue')->map->count()->sortDesc();
        $this->table(['Issue', 'Count'], $summary->map(fn ($count, $issue) => [$issue, $count])->values()->all());

        if ($this->option('report')) {
            $stamp = now()->format('Ymd_His');
            $jsonPath = 'private/reports/products-audit-specs-from-catalog-'.$stamp.'.json';
            $csvPath = 'private/reports/products-audit-specs-from-catalog-'.$stamp.'.csv';
            Storage::disk('local')->put($jsonPath, json_encode([
                'generated_at' => now()->toIso8601String(),
                'dry_run' => true,
                'summary' => $summary,
                'rows' => $rows,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->writeCsv(storage_path('app/'.$csvPath), $rows);
            $this->info('Report written: '.storage_path('app/'.$jsonPath));
            $this->info('CSV written: '.storage_path('app/'.$csvPath));
        }

        return self::SUCCESS;
    }

    private function suggestAction(string $issue): string
    {
        return match ($issue) {
            'correct' => 'keep',
            'product_missing_specs' => 'update_from_catalog',
            'product_extra_specs' => 'remove_extra',
            'wrong_format' => 'normalize_format',
            'catalog_source_missing', 'ambiguous_catalog_match', 'missing_catalog_specs' => 'manual_review',
            default => 'manual_review',
        };
    }

    private function writeCsv(string $absolutePath, array $rows): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $fp = fopen($absolutePath, 'wb');
        if (! $fp) {
            return;
        }

        // UTF-8 BOM for Excel on Windows
        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, ['product_id', 'model', 'sku', 'brand', 'catalog_file', 'field', 'current_value', 'catalog_value', 'issue', 'confidence', 'action']);
        foreach ($rows as $row) {
            fputcsv($fp, [
                $row['product_id'],
                $row['model'],
                $row['sku'],
                $row['brand'],
                $row['catalog_file'],
                $row['field'],
                $row['current_value'],
                $row['catalog_value'],
                $row['issue'],
                $row['confidence'],
                $row['action'],
            ]);
        }
        fclose($fp);
    }
}
