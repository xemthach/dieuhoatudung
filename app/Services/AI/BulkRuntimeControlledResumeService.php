<?php

namespace App\Services\AI;

use App\Jobs\AiProductContentSingleJob;
use App\Models\AiProductJob;
use App\Models\AiBulkRuntimeBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BulkRuntimeControlledResumeService
{
    public function markBlockedFinal(int $itemId, string $reason): array
    {
        $item = \App\Models\AiProductJobItem::findOrFail($itemId);
        if ($item->status_reason === 'BLOCKED_FINAL' && $item->canonical_status === AIJobStateMachine::BLOCKED) return ['status' => 'ALREADY_BLOCKED_FINAL', 'item_id' => $itemId];
        AIJobStateMachine::transition($item, AIJobStateMachine::BLOCKED, $reason);
        $item->update(['status' => 'blocked', 'canonical_status' => AIJobStateMachine::BLOCKED, 'status_reason' => 'BLOCKED_FINAL', 'last_error_code' => $reason]);
        return ['status' => 'BLOCKED_FINAL', 'item_id' => $itemId, 'reason' => $reason];
    }

    public function resumeExistingBatch(AiProductJob $job, array $allowlist): array
    {
        $allowlist = array_values(array_unique(array_map('intval', $allowlist)));
        if ($allowlist !== [1247, 1261]) throw new RuntimeException('PHASE2F5_ALLOWLIST_FAILED');
        $manifest = (array) data_get($job->target_manifest_json, 'resolved_product_ids', []);
        sort($manifest);
        $expected = [1237, 1241, 1242, 1247, 1261];
        if ($manifest !== $expected) throw new RuntimeException('PHASE2F5_MANIFEST_CHANGED');

        $runtime = AiBulkRuntimeBatch::where('batch_uuid', $job->batch_uuid)->firstOrFail();
        if ((int) $runtime->token_consumed !== 34207 || (int) $runtime->token_reserved !== 0) throw new RuntimeException('PHASE2F5_TOKEN_PREFLIGHT_FAILED');

        DB::transaction(function () use ($job, $runtime, $allowlist): void {
            $config = (array) $job->config_json;
            $config['controlled_resume_allowlist'] = $allowlist;
            $job->update(['config_json' => $config]);
            app(BulkRuntimeBatchService::class)->resume($runtime);
            foreach ($allowlist as $productId) {
                $item = $job->items()->where('product_id', $productId)->lockForUpdate()->firstOrFail();
                if ($productId === 1247) {
                    if (DB::table('ai_bulk_runtime_leases')->where('runtime_batch_id', $runtime->id)->where('item_id', $item->id)->where('status', 'CLAIMED')->exists()) throw new RuntimeException('PHASE2F5_1247_ACTIVE_LEASE');
                    if (DB::table('ai_bulk_runtime_slots')->where('runtime_batch_id', $runtime->id)->where('item_id', $item->id)->where('status', 'LEASED')->exists()) throw new RuntimeException('PHASE2F5_1247_ACTIVE_SLOT');
                    if ($item->status !== 'queued') AIJobStateMachine::transition($item, AIJobStateMachine::QUEUED, 'CONTROLLED_SAME_BATCH_RESUME');
                    $item->update(['status' => 'queued', 'canonical_status' => AIJobStateMachine::QUEUED, 'status_reason' => 'CONTROLLED_SAME_BATCH_RESUME', 'finished_at' => null, 'error_message' => null, 'last_error_code' => null, 'last_error_message' => null]);
                }
            }
        });

        foreach ($allowlist as $productId) {
            $item = $job->items()->where('product_id', $productId)->firstOrFail();
            AiProductContentSingleJob::dispatch($productId, $job->id, $item->id)->onQueue(config('ai.governed_queue', 'ai_governed'));
        }
        return ['status' => 'RESUMED_ALLOWLIST_ONLY', 'batch_uuid' => $job->batch_uuid, 'allowlist' => $allowlist, 'dispatched_product_ids' => $allowlist];
    }
}
