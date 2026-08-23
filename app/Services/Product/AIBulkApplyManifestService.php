<?php

namespace App\Services\Product;

use App\Models\AiBulkApplyBatch;
use App\Models\AiBulkApplyItem;
use App\Models\AiProductDraft;
use Illuminate\Support\Str;
use RuntimeException;
use App\Support\CanonicalJsonHasher;

/** Separate, immutable authorization manifest for bulk apply. */
class AIBulkApplyManifestService
{
    public function __construct(private readonly AIProductDraftApplyService $applyService) {}

    public function create(string $generationBatchUuid, array $drafts, int $approvedBy): AiBulkApplyBatch
    {
        if ($drafts === []) throw new RuntimeException('EMPTY_APPLY_MANIFEST');
        $items = [];
        foreach ($drafts as $draft) {
            $draft = $draft instanceof AiProductDraft ? $draft->fresh(['product']) : AiProductDraft::findOrFail($draft);
            if ($draft->approval_status !== 'APPROVED_FOR_APPLY') throw new RuntimeException('DRAFT_NOT_APPROVED');
            $eligibility = $this->applyService->eligibility($draft);
            if (! $eligibility['eligible_for_approval'] && ! $draft->approved_at) throw new RuntimeException('DRAFT_NOT_ELIGIBLE');
            $payload = $draft->normalized_output_json ?: $draft->raw_output_json ?: [];
            $items[] = [
                'product_id' => (int) $draft->product_id,
                'draft_id' => (int) $draft->id,
                'approved_fields' => $draft->approved_fields_json ?: [],
                'payload_hash' => (string) $draft->approved_payload_hash,
                'technical_context_hash' => (string) $draft->approved_technical_context_hash,
                'before_product_hash' => $this->applyService->contentHash($draft->product),
                'approved_by' => $approvedBy,
                'approved_at' => optional($draft->approved_at)->toIso8601String(),
                'source_provenance' => [
                    'source_pilot_batch_uuid' => data_get($draft->token_usage_json, 'source_pilot_batch_uuid'),
                    'source_pilot_product_id' => data_get($draft->token_usage_json, 'source_pilot_product_id'),
                    'source_pilot_draft_id' => data_get($draft->token_usage_json, 'source_pilot_draft_id'),
                    'provider' => data_get($draft->token_usage_json, 'provider'),
                    'model' => data_get($draft->token_usage_json, 'model'),
                    'generation_timestamp' => data_get($draft->token_usage_json, 'generation_timestamp'),
                    'fact_check_status' => data_get($draft->token_usage_json, 'fact_check_status'),
                ],
            ];
        }
        $manifest = ['generation_batch_uuid' => $generationBatchUuid, 'items' => $items, 'approved_by' => $approvedBy];
        $hash = $this->hash($manifest);
        $batch = AiBulkApplyBatch::create([
            'apply_batch_uuid' => (string) Str::uuid(), 'generation_batch_uuid' => $generationBatchUuid,
            'manifest_json' => $manifest, 'manifest_hash' => $hash, 'approved_by' => $approvedBy,
        ]);
        foreach ($items as $item) {
            $persistedItem = $item;
            unset($persistedItem['source_provenance']);
            $batch->items()->create(array_merge($persistedItem, ['status' => 'READY']));
        }
        return $batch->refresh();
    }

    public function verify(AiBulkApplyBatch $batch): bool
    {
        return hash_equals((string) $batch->manifest_hash, $this->hash((array) $batch->manifest_json));
    }

    private function hash(array $manifest): string
    {
        return app(CanonicalJsonHasher::class)->hash($manifest);
    }
}
