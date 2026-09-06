<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Product\ProductDataVerificationService;
use App\Services\Product\ProductMarketingCapacityAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BackfillMarketingCapacityCommand extends Command
{
    protected $signature = 'catalog:backfill-marketing-capacity
        {--apply : Apply only provenance-backed proposals; default is dry-run}
        {--approved : Required with --apply}
        {--product= : Optional Product ID scope}
        {--batch-size=50 : Products per transaction}
        {--batch= : Stable ledger batch UUID; generated for dry-runs when absent}';

    protected $description = 'Backfill only missing marketing_capacity_btu from verified PRODUCT_LIST catalog facts.';

    public function handle(ProductMarketingCapacityAuditService $audit): int
    {
        if ($this->option('apply') && ! $this->option('approved')) {
            $this->error('Blocked: --apply requires --approved.');
            return self::FAILURE;
        }
        $productId = $this->option('product');
        if ($productId !== null && ! ctype_digit((string) $productId)) {
            $this->error('Blocked: --product must be a numeric Product ID.');
            return self::FAILURE;
        }
        $batchSize = max(1, min((int) $this->option('batch-size'), 200));
        $batch = (string) ($this->option('batch') ?: Str::uuid());
        if ($this->option('apply') && trim((string) $this->option('batch')) === '') {
            $this->error('Blocked: --apply requires the exact --batch from a reviewed dry-run ledger.');
            return self::FAILURE;
        }
        $auditResult = $audit->audit($productId !== null ? (int) $productId : null);
        $proposals = collect($auditResult['products'])->where('action', 'PROPOSE_UPDATE')->values();
        $proposalDigest = $this->proposalDigest($proposals->all());
        $dryRunPath = storage_path('app/private/reports/marketing_capacity_backfill_'.$batch.'.dry-run.json');
        if ($this->option('apply')) {
            if (! is_file($dryRunPath)) {
                $this->error('Blocked: reviewed dry-run ledger not found for batch '.$batch.'.');
                return self::FAILURE;
            }
            $reviewed = json_decode((string) file_get_contents($dryRunPath), true);
            if (! is_array($reviewed)
                || ($reviewed['batch_uuid'] ?? null) !== $batch
                || ($reviewed['mode'] ?? null) !== 'dry_run'
                || ! hash_equals((string) ($reviewed['proposal_sha256'] ?? ''), $proposalDigest)) {
                $this->error('Blocked: current proposals do not match the reviewed dry-run ledger.');
                return self::FAILURE;
            }
        }
        $ledger = [
            'batch_uuid' => $batch, 'mode' => $this->option('apply') ? 'apply' : 'dry_run',
            'read_only' => ! $this->option('apply'), 'created_at' => now()->toIso8601String(),
            'proposal_sha256' => $proposalDigest,
            'stats' => $auditResult['stats'], 'items' => [],
        ];

        foreach ($proposals->chunk($batchSize) as $chunk) {
            if (! $this->option('apply')) {
                foreach ($chunk as $proposal) {
                    $ledger['items'][] = $this->ledgerItem($proposal, 'PROPOSE_UPDATE');
                }
                continue;
            }
            DB::transaction(function () use ($chunk, &$ledger, $audit): void {
                foreach ($chunk as $proposal) {
                    $ledger['items'][] = $this->applyProposal($proposal, $audit);
                }
            });
        }

        $ledger['finished_at'] = now()->toIso8601String();
        $path = $this->option('apply')
            ? storage_path('app/private/reports/marketing_capacity_backfill_'.$batch.'.apply.json')
            : $dryRunPath;
        if (! is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->line((string) json_encode([
            'batch_uuid' => $batch, 'mode' => $ledger['mode'], 'proposals' => $proposals->count(),
            'ledger' => $path, 'database_mutation' => $this->option('apply') ? 'MARKETING_CAPACITY_ONLY' : 'NONE',
        ], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /** @param array<int,array<string,mixed>> $proposals */
    private function proposalDigest(array $proposals): string
    {
        $canonical = collect($proposals)->map(fn (array $proposal): array => [
            'product_id' => (int) $proposal['product_id'],
            'current_marketing' => $proposal['current_marketing'],
            'proposed_marketing' => (int) $proposal['proposed_marketing'],
            'catalog_field_id' => $proposal['catalog_field_id'] ? (int) $proposal['catalog_field_id'] : null,
            'mapping_digest' => $proposal['mapping_digest'] ?? null,
        ])->sortBy('product_id')->values()->all();

        return hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_SLASHES));
    }

    private function applyProposal(array $proposal, ProductMarketingCapacityAuditService $audit): array
    {
        $product = Product::query()->lockForUpdate()->findOrFail((int) $proposal['product_id']);
        $fresh = $audit->audit($product->id)['products'][0] ?? null;
        if (! $fresh
            || $fresh['action'] !== 'PROPOSE_UPDATE'
            || (int) $fresh['proposed_marketing'] !== (int) $proposal['proposed_marketing']
            || ($fresh['catalog_field_id'] ?? null) !== ($proposal['catalog_field_id'] ?? null)
            || ($fresh['mapping_digest'] ?? null) !== ($proposal['mapping_digest'] ?? null)) {
            throw new RuntimeException('STALE_OR_INVALID_MARKETING_CAPACITY_EVIDENCE for Product '.$product->id);
        }
        $before = $product->marketing_capacity_btu;
        $product->forceFill([
            'marketing_capacity_btu' => (int) $proposal['proposed_marketing'],
            'marketing_capacity_status' => ProductDataVerificationService::VERIFIED,
            'marketing_capacity_source' => $proposal['source'],
            'marketing_capacity_mapping_digest' => $proposal['mapping_digest']
                ?: hash('sha256', 'catalog-field:'.(string) $proposal['catalog_field_id']),
            'marketing_capacity_evidence_json' => $proposal['evidence'],
            'marketing_capacity_verified_at' => now(),
        ])->save();

        return $this->ledgerItem($proposal, 'APPLIED', $before, $product->marketing_capacity_btu);
    }

    private function ledgerItem(array $proposal, string $status, mixed $before = null, mixed $after = null): array
    {
        return [
            'product_id' => $proposal['product_id'], 'before_marketing' => $before ?? $proposal['current_marketing'],
            'after_marketing' => $after ?? $proposal['proposed_marketing'], 'catalog_field_id' => $proposal['catalog_field_id'],
            'mapping_digest' => $proposal['mapping_digest'] ?? null,
            'source' => $proposal['source'], 'source_section' => $proposal['source_section'],
            'evidence' => $proposal['evidence'], 'reason' => $proposal['reason'], 'status' => $status,
        ];
    }
}
