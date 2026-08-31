<?php

namespace App\Services\Product;

use App\Models\AiBulkApplyBatch;
use App\Models\AiBulkApplyItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Models\User;

class AIBulkApplyExecutor
{
    public function __construct(private readonly AIBulkApplyManifestService $manifests, private readonly AIProductDraftApplyService $applyService) {}

    public function apply(string $applyBatchUuid, int $actorId, User $actor, int $chunkSize = 1, bool $injectFailure = false, ?string $confirmation = null): array
    {
        if ($chunkSize < 1) throw new RuntimeException('INVALID_CHUNK_SIZE');
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireApply($actor);
        $batch = AiBulkApplyBatch::where('apply_batch_uuid', $applyBatchUuid)->firstOrFail();
        if (! $this->manifests->verify($batch)) throw new RuntimeException('MANIFEST_TAMPERED');
        $items = $batch->items()->where('status', 'READY')->orderBy('id')->get();
        if ($items->isEmpty()) {
            return ['status' => $batch->items()->where('status', 'APPLIED')->exists() ? 'NOOP_ALREADY_APPLIED' : 'NOOP_EMPTY', 'items' => []];
        }
        $expected = 'APPLY '.$items->count().' PRODUCTS';
        if (! is_string($confirmation) || trim($confirmation) !== $expected) {
            throw new RuntimeException('BULK_APPLY_CONFIRMATION_REQUIRED');
        }

        $results = [];
        foreach ($items as $index => $item) {
            try {
                $result = DB::transaction(function () use ($batch, $item, $index, $actorId, $injectFailure): array {
                    $draft = \App\Models\AiProductDraft::findOrFail($item->draft_id);
                    $product = $draft->product()->with(['tags', 'faqs'])->firstOrFail();
                    if (! hash_equals((string) $item->before_product_hash, $this->applyService->contentHash($product))) {
                        throw new RuntimeException('STALE_BEFORE_PRODUCT_HASH');
                    }
                    if (! hash_equals((string) $item->technical_context_hash, app(AIProductContentSystem::class)->technicalContextHash($product))) {
                        throw new RuntimeException('STALE_TECHNICAL_CONTEXT');
                    }
                    $snapshot = app(AIProductContentSystem::class)->contentSnapshot($product);
                    $snapshotId = DB::table('ai_bulk_apply_snapshots')->insertGetId([
                        'apply_batch_id' => $batch->id, 'apply_item_id' => $item->id, 'product_id' => $product->id,
                        'chunk_no' => $index + 1, 'before_payload_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'before_hash' => $this->applyService->contentHash($product), 'status' => 'CAPTURED', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $singleConfirmation = app(\App\Services\AI\SingleOperatorControlledRolloutPolicy::class)
                        ->expectedApplyConfirmation(($product->model_code ?: 'UNKNOWN').'#'.$product->id);
                    $result = $this->applyService->apply($draft, $actorId, false, $singleConfirmation);
                    DB::table('ai_bulk_apply_snapshots')->where('id', $snapshotId)->update(['after_hash' => $result['after_hash'], 'status' => 'APPLIED', 'updated_at' => now()]);
                    $item->update(['status' => 'APPLIED', 'chunk_no' => $index + 1, 'reason' => null]);
                    if ($injectFailure && $index === 1) throw new RuntimeException('CONTROLLED_ITEM_FAILURE');

                    return $result;
                });
                $results[] = [
                    'product_id' => (int) $item->product_id,
                    'draft_id' => (int) $item->draft_id,
                    'result' => 'SUCCESS',
                    'reason' => $result['result'] ?? 'APPLIED',
                    'after_state' => 'APPLIED',
                    'fields_applied' => $result['fields_applied'] ?? [],
                ];
            } catch (\Throwable $exception) {
                $blocked = \Illuminate\Support\Str::contains($exception->getMessage(), ['STALE', 'BLOCK', 'CONFLICT', 'FORBIDDEN', 'CONFIRMATION']);
                $item->update(['status' => $blocked ? 'BLOCKED' : 'FAILED', 'chunk_no' => $index + 1, 'reason' => $exception->getMessage()]);
                $results[] = [
                    'product_id' => (int) $item->product_id,
                    'draft_id' => (int) $item->draft_id,
                    'result' => $blocked ? 'BLOCKED' : 'FAILED',
                    'reason' => $exception->getMessage(),
                    'after_state' => 'APPROVED',
                    'fields_applied' => [],
                ];
            }
        }
        $failed = collect($results)->whereIn('result', ['BLOCKED', 'FAILED'])->count();
        $batch->update(['status' => $failed > 0 ? 'COMPLETED_PARTIAL' : 'APPLIED']);
        return ['status' => $failed > 0 ? 'COMPLETED_PARTIAL' : 'APPLIED', 'items' => $results];
    }

    public function applyAsActor(string $applyBatchUuid, User $actor, int $chunkSize = 1, bool $injectFailure = false, ?string $confirmation = null): array
    {
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireApply($actor);
        return $this->apply($applyBatchUuid, (int) $actor->id, $actor, $chunkSize, $injectFailure, $confirmation);
    }

    public function rollbackItem(AiBulkApplyItem $item, User $actor): bool
    {
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireRollback($actor);
        return $this->rollbackItemInternal($item);
    }

    private function rollbackItemInternal(AiBulkApplyItem $item): bool
    {
        if ($item->status === 'ROLLED_BACK') return true;
        $snapshot = DB::table('ai_bulk_apply_snapshots')->where('apply_item_id', $item->id)->first();
        if (! $snapshot) return false;
        $product = \App\Models\Product::findOrFail($item->product_id);
        if ($this->applyService->contentHash($product) !== (string) $snapshot->after_hash) throw new RuntimeException('ROLLBACK_DRIFT_REVIEW_REQUIRED');
        app(AIProductContentSystem::class)->restoreContentSnapshot($product, json_decode($snapshot->before_payload_json, true) ?: []);
        $item->update(['status' => 'ROLLED_BACK']);
        return true;
    }

    public function rollbackBatch(string $applyBatchUuid, User $actor): array
    {
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireRollback($actor);
        return $this->rollbackBatchInternal($applyBatchUuid);
    }

    private function rollbackBatchInternal(string $applyBatchUuid): array
    {
        $batch = AiBulkApplyBatch::where('apply_batch_uuid', $applyBatchUuid)->firstOrFail();
        $results = [];
        foreach ($batch->items()->orderByDesc('id')->get() as $item) $results[$item->id] = $this->rollbackItemInternal($item);
        $batch->update(['status' => 'ROLLED_BACK']);
        return ['status' => 'ROLLED_BACK', 'items' => $results];
    }

    public function rollbackBatchAsActor(string $applyBatchUuid, User $actor): array
    {
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireRollback($actor);
        return $this->rollbackBatchInternal($applyBatchUuid);
    }

    public function rollbackItemAsActor(AiBulkApplyItem $item, User $actor): bool
    {
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireRollback($actor);
        return $this->rollbackItemInternal($item);
    }
}
