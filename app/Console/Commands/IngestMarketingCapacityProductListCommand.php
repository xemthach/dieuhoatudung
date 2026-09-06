<?php

namespace App\Console\Commands;

use App\Services\Product\ProductListMarketingCapacityEvidenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class IngestMarketingCapacityProductListCommand extends Command
{
    protected $signature = 'catalog:ingest-marketing-capacity-product-list
        {file : Official structured Product List XLSX/XLS/CSV/JSON}
        {--brand= : Default exact brand slug when rows do not contain brand_slug}
        {--source-name= : Source document title}
        {--source-version= : Source document edition/version}
        {--apply : Persist approved PRODUCT_LIST evidence; default is read-only}
        {--approved : Required with --apply}
        {--batch= : Stable batch identifier; required with --apply}';

    protected $description = 'Audit or persist source-backed Product List marketing-capacity evidence without changing Products.';

    public function handle(ProductListMarketingCapacityEvidenceService $service): int
    {
        if ($this->option('apply') && (! $this->option('approved') || trim((string) $this->option('batch')) === '')) {
            $this->error('Blocked: --apply requires --approved and --batch.');
            return self::FAILURE;
        }
        $path = (string) $this->argument('file');
        if (! preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path)) $path = base_path($path);

        try {
            $inspection = $service->inspect($path, $this->option('brand') ? (string) $this->option('brand') : null);
            $batch = trim((string) ($this->option('batch') ?: Str::uuid()));
            $result = [
                'batch' => $batch,
                'mode' => $this->option('apply') ? 'apply_evidence' : 'dry_run',
                'product_mutation' => 'NONE',
                'inspection' => $inspection,
            ];
            if ($this->option('apply')) {
                $sourceName = trim((string) $this->option('source-name'));
                if ($sourceName === '') throw new InvalidArgumentException('--source-name is required with --apply.');
                $result['persistence'] = $service->persist($inspection, $sourceName, $this->option('source-version') ? (string) $this->option('source-version') : null, $batch);
            }

            $reportPath = storage_path('app/private/reports/marketing_capacity_product_list_'.$batch.'.json');
            if (! is_dir(dirname($reportPath))) mkdir(dirname($reportPath), 0775, true);
            file_put_contents($reportPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line((string) json_encode([
                'batch' => $batch,
                'mode' => $result['mode'],
                'stats' => $inspection['stats'],
                'source_sha256' => $inspection['source']['sha256'],
                'report' => $reportPath,
                'product_mutation' => 'NONE',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
