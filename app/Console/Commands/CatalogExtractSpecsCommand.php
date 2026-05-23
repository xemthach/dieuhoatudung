<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogFileScanner;
use App\Services\Catalog\CatalogStructuredExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CatalogExtractSpecsCommand extends Command
{
    protected $signature = 'catalog:extract-specs
        {--source=all : all|json|csv|xlsx|xls}
        {--persist : Persist parsed records into catalog_* tables}
        {--report : Write JSON report}';

    protected $description = 'Extract technical specs from structured internal catalog files only.';

    public function handle(CatalogFileScanner $scanner, CatalogStructuredExtractor $extractor): int
    {
        $source = strtolower((string) $this->option('source'));
        $persist = (bool) $this->option('persist');
        $files = $scanner->scan();

        if ($source !== 'all') {
            $files = $files->filter(fn (array $file): bool => $file['extension'] === $source)->values();
        }

        $results = [];
        $totals = ['files' => 0, 'models' => 0, 'fields' => 0, 'skipped' => 0];

        foreach ($files as $file) {
            $totals['files']++;
            $result = $extractor->extractFile($file, $persist);
            $results[] = [
                'file' => $file['relative_path'],
                'brand' => $file['brand'],
                'type' => $file['extension'],
                'status' => $result['status'],
                'reason' => $result['reason'] ?? null,
                'models' => $result['models'],
                'fields' => $result['fields'],
                'catalog_source_id' => $result['source']?->id,
            ];

            $totals['models'] += $result['models'];
            $totals['fields'] += $result['fields'];
            if ($result['status'] !== 'ok') {
                $totals['skipped']++;
            }
        }

        $this->info('Catalog extraction completed.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Files scanned', $totals['files']],
                ['Models extracted', $totals['models']],
                ['Field rows extracted', $totals['fields']],
                ['Skipped files', $totals['skipped']],
                ['Persisted', $persist ? 'yes' : 'no'],
            ]
        );

        if ($this->option('report')) {
            $payload = [
                'generated_at' => now()->toIso8601String(),
                'policy' => ['internal_sources_only' => true, 'external_sources_used' => false],
                'totals' => $totals,
                'results' => $results,
            ];
            $path = 'private/reports/catalog-extract-'.now()->format('Ymd_His').'.json';
            Storage::disk('local')->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->info('Report written: '.storage_path('app/'.$path));
        }

        return self::SUCCESS;
    }
}

