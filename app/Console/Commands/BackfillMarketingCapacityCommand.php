<?php

namespace App\Console\Commands;

use App\Models\CatalogModelField;
use App\Models\Product;
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
        $auditResult = $audit->audit($productId !== null ? (int) $productId : null);
        $proposals = collect($auditResult['products'])->where('action', 'PROPOSE_UPDATE')->values();
        $ledger = [
            'batch_uuid' => $batch, 'mode' => $this->option('apply') ? 'apply' : 'dry_run',
            'read_only' => ! $this->option('apply'), 'created_at' => now()->toIso8601String(),
            'stats' => $auditResult['stats'], 'items' => [],
        ];

        foreach ($proposals->chunk($batchSize) as $chunk) {
            if (! $this->option('apply')) {
                foreach ($chunk as $proposal) {
                    $ledger['items'][] = $this->ledgerItem($proposal, 'PROPOSE_UPDATE');
                }
                continue;
            }
            DB::transaction(function () use ($chunk, &$ledger): void {
                foreach ($chunk as $proposal) {
                    $ledger['items'][] = $this->applyProposal($proposal);
                }
            });
        }

        $ledger['finished_at'] = now()->toIso8601String();
        $path = storage_path('app/private/reports/marketing_capacity_backfill_'.$batch.'.json');
        if (! is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->line((string) json_encode([
            'batch_uuid' => $batch, 'mode' => $ledger['mode'], 'proposals' => $proposals->count(),
            'ledger' => $path, 'database_mutation' => $this->option('apply') ? 'MARKETING_CAPACITY_ONLY' : 'NONE',
        ], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function applyProposal(array $proposal): array
    {
        $product = Product::query()->lockForUpdate()->findOrFail((int) $proposal['product_id']);
        if ($product->marketing_capacity_btu !== null) return $this->ledgerItem($proposal, 'SKIPPED_ALREADY_SET');
        $field = CatalogModelField::query()->lockForUpdate()->find($proposal['catalog_field_id']);
        if (! $field
            || $field->catalog_model_id !== $product->catalog_model_id
            || $field->field_key !== 'marketing_capacity_btu'
            || $field->source_section !== 'PRODUCT_LIST'
            || ! in_array($field->verification_status, ['verified', 'verified_candidate', 'approved'], true)
            || (int) ($field->normalized_value ?: $field->field_value) !== (int) $proposal['proposed_marketing']) {
            throw new RuntimeException('STALE_OR_INVALID_PRODUCT_LIST_EVIDENCE for Product '.$product->id);
        }
        $before = $product->marketing_capacity_btu;
        $product->forceFill(['marketing_capacity_btu' => (int) $proposal['proposed_marketing']])->save();

        return $this->ledgerItem($proposal, 'APPLIED', $before, $product->marketing_capacity_btu);
    }

    private function ledgerItem(array $proposal, string $status, mixed $before = null, mixed $after = null): array
    {
        return [
            'product_id' => $proposal['product_id'], 'before_marketing' => $before ?? $proposal['current_marketing'],
            'after_marketing' => $after ?? $proposal['proposed_marketing'], 'catalog_field_id' => $proposal['catalog_field_id'],
            'source' => $proposal['source'], 'source_section' => $proposal['source_section'],
            'evidence' => $proposal['evidence'], 'reason' => $proposal['reason'], 'status' => $status,
        ];
    }
}
