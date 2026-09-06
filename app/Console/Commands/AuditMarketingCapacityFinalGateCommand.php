<?php

namespace App\Console\Commands;

use App\Services\Product\MarketingCapacityFinalGateService;
use Illuminate\Console\Command;

class AuditMarketingCapacityFinalGateCommand extends Command
{
    protected $signature = 'catalog:audit-marketing-capacity-final-gate {--json : Emit machine-readable summary}';

    protected $description = 'Generate the read-only taxonomy-aware commercial marketing-capacity ledger.';

    public function handle(MarketingCapacityFinalGateService $service): int
    {
        $audit = $service->audit();
        $directory = storage_path('app/private/reports');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $json = $directory.'/marketing_capacity_commercial_tier_ledger.json';
        $csv = $directory.'/marketing_capacity_commercial_tier_ledger.csv';
        $md = $directory.'/marketing_capacity_commercial_tier_ledger.md';
        file_put_contents($json, json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->writeCsv($csv, $audit['rows']);
        $this->writeMarkdown($md, $audit);

        $summary = [
            'read_only' => true,
            'product_mutation' => 'NONE',
            'stats' => $audit['stats'],
            'source_identity' => $audit['source_identity'],
            'taxonomy_ledger_digest' => $audit['taxonomy_ledger_digest'],
            'ledger_digest' => $audit['ledger_digest'],
            'reports' => ['json' => $json, 'csv' => $csv, 'markdown' => $md],
        ];
        $this->line($this->option('json')
            ? json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : 'Read-only final gate audit complete: '.json_encode($audit['stats']));

        return self::SUCCESS;
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function writeMarkdown(string $path, array $audit): void
    {
        $stats = $audit['stats'];
        $lines = [
            '# Marketing capacity commercial tier ledger', '',
            '- Generated: '.$audit['generated_at'],
            '- Ledger digest: `'.$audit['ledger_digest'].'`',
            '- Taxonomy ledger digest: `'.$audit['taxonomy_ledger_digest'].'`',
            '- Product mutation: **NONE**', '',
            '| Measure | Count |', '|---|---:|',
        ];
        foreach ($stats as $key => $value) $lines[] = '| `'.$key.'` | '.$value.' |';
        $lines[] = '';
        $lines[] = 'Technical BTU, kW, HP, legacy BTU, model suffixes, titles, and nearest-tier rounding are not accepted commercial evidence.';
        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
    }
}
