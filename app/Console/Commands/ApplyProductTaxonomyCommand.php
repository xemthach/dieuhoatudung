<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Product\ProductDataApprovalService;
use App\Services\Product\ProductDataVerificationService;
use App\Services\Product\ProductTaxonomyAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApplyProductTaxonomyCommand extends Command
{
    protected $signature = 'catalog:apply-product-taxonomy
        {--apply : Apply the approved deterministic rows; default is dry-run}
        {--approved : Explicit operator acknowledgement}
        {--batch= : Stable batch UUID}
        {--digest= : Exact approved taxonomy ledger digest}
        {--batch-size=50 : Transaction chunk size}';

    protected $description = 'Dry-run or apply only approved source-backed Product taxonomy rows.';

    public function handle(ProductTaxonomyAuditService $auditService, ProductDataApprovalService $approvals): int
    {
        $audit = $auditService->audit();
        $digest = (string) ($this->option('digest') ?: $audit['ledger_digest']);
        $batch = (string) ($this->option('batch') ?: Str::uuid());
        $rows = collect($audit['rows'])->whereIn('classification', ['KEEP', 'PROPOSE_UPDATE'])->values();
        $summary = [
            'batch_uuid' => $batch,
            'ledger_digest' => $audit['ledger_digest'],
            'mode' => $this->option('apply') ? 'apply' : 'dry_run',
            'APPLIED' => 0, 'UNCHANGED' => 0, 'STALE' => 0, 'CONFLICT' => 0,
            'SKIPPED_REJECTED' => 0,
            'SKIPPED_UNRESOLVED' => collect($audit['rows'])->whereNotIn('classification', ['KEEP', 'PROPOSE_UPDATE'])->count(),
            'product_mutation' => 'NONE',
        ];

        if (! $this->option('apply')) {
            $summary['would_verify'] = $rows->count();
            $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }

        if (! $this->option('approved') || $digest !== $audit['ledger_digest'] || ! $approvals->taxonomyLedgerIsApproved($digest)) {
            $this->error('Apply blocked: exact current ledger digest and append-only Admin approval are required.');
            return self::FAILURE;
        }

        $excluded = $approvals->taxonomyProductsExcludedAfterApproval($digest);
        $summary['SKIPPED_REJECTED'] = count($excluded);
        $rows = $rows->reject(fn (array $row): bool => in_array((int) $row['product_id'], $excluded, true))->values();

        $rows->chunk(max(1, (int) $this->option('batch-size')))->each(function ($chunk) use (&$summary, $digest): void {
            DB::transaction(function () use ($chunk, &$summary, $digest): void {
                foreach ($chunk as $row) {
                    $product = Product::query()->lockForUpdate()->with('brand:id,slug')->find($row['product_id']);
                    if (! $product
                        || $product->sku !== $row['sku']
                        || $product->model_code !== $row['model_code']
                        || $product->brand?->slug !== $row['brand']
                        || (int) $product->product_category_id !== (int) $row['current_category_id']) {
                        $summary['STALE']++;
                        continue;
                    }
                    if (! $row['proposed_category_id']) {
                        $summary['CONFLICT']++;
                        continue;
                    }
                    $changes = [
                        'product_category_id' => (int) $row['proposed_category_id'],
                        'taxonomy_status' => ProductDataVerificationService::VERIFIED,
                        'taxonomy_source' => $row['evidence_type'],
                        'taxonomy_ledger_digest' => $digest,
                        'taxonomy_evidence_json' => [
                            'source' => $row['evidence_source'],
                            'location' => $row['evidence_location'],
                            'detail' => $row['evidence_detail'],
                        ],
                        'taxonomy_verified_at' => now(),
                    ];
                    $already = (int) $product->product_category_id === $changes['product_category_id']
                        && $product->taxonomy_status === ProductDataVerificationService::VERIFIED
                        && $product->taxonomy_ledger_digest === $digest;
                    if ($already) {
                        $summary['UNCHANGED']++;
                        continue;
                    }
                    $product->forceFill($changes)->save();
                    $summary['APPLIED']++;
                }
            });
        });
        $summary['product_mutation'] = $summary['APPLIED'] > 0 ? 'APPROVED_TAXONOMY_ONLY' : 'NONE';
        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
