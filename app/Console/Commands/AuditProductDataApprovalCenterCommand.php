<?php

namespace App\Console\Commands;

use App\Services\Product\ProductDataApprovalCenterService;
use Illuminate\Console\Command;

final class AuditProductDataApprovalCenterCommand extends Command
{
    protected $signature = 'catalog:audit-product-data-approval-center {--json : Emit machine-readable summary}';
    protected $description = 'Generate read-only taxonomy and marketing-capacity review datasets.';

    public function handle(ProductDataApprovalCenterService $service): int
    {
        $taxonomy = $service->taxonomyReview();
        $marketing = $service->marketingReview();
        $unresolved = collect($taxonomy['rows'])->whereNotIn('classification', ['KEEP', 'PROPOSE_UPDATE'])->values()->all();
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'product_mutation' => 'NONE',
            'taxonomy' => $taxonomy,
            'marketing' => $marketing,
            'unresolved_products' => $unresolved,
        ];
        $directory = storage_path('app/private/reports');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $json = $directory.'/product_data_approval_center.json';
        $markdown = $directory.'/product_data_approval_center.md';
        file_put_contents($json, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        file_put_contents($markdown, $this->markdown($payload));
        $summary = [
            'read_only' => true,
            'product_mutation' => 'NONE',
            'taxonomy_stats' => $taxonomy['stats'],
            'marketing_stats' => $marketing['stats'],
            'unresolved' => count($unresolved),
            'reports' => ['json' => $json, 'markdown' => $markdown],
        ];
        $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function markdown(array $payload): string
    {
        $taxonomy = $payload['taxonomy']['stats'];
        $marketing = $payload['marketing']['stats'];
        $lines = [
            '# Product Data Approval Center dry-run', '',
            '- Product mutation: **NONE**',
            '- Taxonomy ledger: `'.$payload['taxonomy']['ledger_digest'].'`',
            '- Deterministic taxonomy rows: '.($taxonomy['keep'] + $taxonomy['propose_update']),
            '- Unresolved/conflict taxonomy rows: '.count($payload['unresolved_products']),
            '- Public Products requiring marketing review: '.$marketing['products_to_review'],
            '- Deterministic review groups: '.$marketing['review_groups'],
            '- Source-explicit tiers: '.$marketing['source_explicit_tier_products'],
            '- Approved mappings: '.$marketing['approved_product_mappings'], '',
            '## Unresolved taxonomy rows', '',
            '| ID | SKU | Model | Brand | Source | Current category | Candidate | Block reason |',
            '|---:|---|---|---|---|---|---|---|',
        ];
        foreach ($payload['unresolved_products'] as $row) {
            $lines[] = '| '.$row['product_id'].' | '.$row['sku'].' | '.$row['model_code'].' | '.$row['brand'].' | '.$row['evidence_source'].' | '.$row['current_category_slug'].' | '.($row['proposed_category_slug'] ?? '—').' | '.$row['reason'].' |';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
