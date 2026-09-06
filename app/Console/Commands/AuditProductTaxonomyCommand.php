<?php

namespace App\Console\Commands;

use App\Services\Product\ProductTaxonomyAuditService;
use Illuminate\Console\Command;

class AuditProductTaxonomyCommand extends Command
{
    protected $signature = 'catalog:audit-product-taxonomy {--json : Emit machine-readable summary}';

    protected $description = 'Generate a read-only, source-backed Product taxonomy remediation ledger.';

    public function handle(ProductTaxonomyAuditService $service): int
    {
        $audit = $service->audit();
        $directory = storage_path('app/private/reports');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $jsonPath = $directory.'/product_taxonomy_remediation.json';
        $csvPath = $directory.'/product_taxonomy_remediation.csv';
        $mdPath = $directory.'/product_taxonomy_remediation.md';
        file_put_contents($jsonPath, json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->writeCsv($csvPath, $audit['rows']);
        $this->writeMarkdown($mdPath, $audit);

        $summary = [
            'read_only' => true,
            'product_mutation' => 'NONE',
            'stats' => $audit['stats'],
            'post_taxonomy_classes' => $audit['post_taxonomy_classes'],
            'ledger_digest' => $audit['ledger_digest'],
            'reports' => ['json' => $jsonPath, 'csv' => $csvPath, 'markdown' => $mdPath],
        ];

        $this->line($this->option('json')
            ? json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : 'Read-only taxonomy audit complete: '.json_encode($audit['stats']));

        return self::SUCCESS;
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value, $row));
            }
        }
        fclose($handle);
    }

    private function writeMarkdown(string $path, array $audit): void
    {
        $stats = $audit['stats'];
        $lines = [
            '# Product taxonomy remediation dry-run', '',
            '- Generated: '.$audit['generated_at'],
            '- Ledger digest: `'.$audit['ledger_digest'].'`',
            '- Product mutation: **NONE**', '',
            '| Classification | Count |', '|---|---:|',
            '| KEEP | '.$stats['keep'].' |',
            '| PROPOSE_UPDATE | '.$stats['propose_update'].' |',
            '| AMBIGUOUS | '.$stats['ambiguous'].' |',
            '| NO_EVIDENCE | '.$stats['no_evidence'].' |',
            '| CONFLICT | '.$stats['conflict'].' |', '',
            'Eligibility is derived only from canonical ProductCategory slugs. Unresolved taxonomy fails closed.',
        ];
        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
    }
}
