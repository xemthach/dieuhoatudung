<?php

namespace App\Services\AI;

use App\Models\AiBulkRuntimeBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Jobs\AiProductFieldRetryJob;
use App\Support\CanonicalJsonHasher;

class BulkFieldRetryService
{
    public function restoreInfrastructureAllowance(int $operationId, string $authorizedBy, string $sourceFailure, string $reason): array
    {
        return DB::transaction(function () use ($operationId, $authorizedBy, $sourceFailure, $reason): array {
            $op = DB::table('ai_bulk_field_operations')->where('id', $operationId)->lockForUpdate()->first();
            if (! $op) throw new RuntimeException('FIELD_OPERATION_NOT_FOUND');
            if ($op->last_error_code !== 'field_retry_failed' || ! str_contains((string) $op->last_error_message, 'contents is required')) {
                throw new RuntimeException('INFRASTRUCTURE_FAILURE_NOT_PROVEN');
            }
            $marker = 'retry_allowance_restored:'.$operationId.':'.$sourceFailure;
            if (DB::table('ai_technical_logs')->where('event', 'retry_allowance_restored')->where('message', $marker)->exists()) {
                return ['status' => 'ALREADY_RESTORED', 'old_max_attempts' => (int) $op->max_attempts, 'new_max_attempts' => (int) $op->max_attempts];
            }
            $old = (int) $op->max_attempts;
            $new = $old + 1;
            DB::table('ai_bulk_field_operations')->where('id', $operationId)->update(['max_attempts' => $new, 'updated_at' => now()]);
            DB::table('ai_technical_logs')->insert([
                'module' => 'ai_bulk_runtime',
                'ai_job_type' => null,
                'ai_job_id' => null,
                'level' => 'warning',
                'event' => 'retry_allowance_restored',
                'message' => $marker,
                'context_json' => json_encode(['operation_id' => $operationId, 'old_max_attempts' => $old, 'new_max_attempts' => $new, 'authorized_by' => $authorizedBy, 'source_failure' => $sourceFailure, 'reason' => $reason], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return ['status' => 'RESTORED', 'old_max_attempts' => $old, 'new_max_attempts' => $new, 'authorized_by' => $authorizedBy, 'source_failure' => $sourceFailure];
        });
    }

    public function retry(AiBulkRuntimeBatch $batch, int $itemId, int $productId, string $field, int $maxAttempts = 3, ?object $actor = null): array
    {
        if (! in_array($field, ['content', 'content_html', 'seo', 'merchant', 'faq', 'tags'], true)) throw new RuntimeException('INVALID_FIELD');
        if ($actor) app(BulkRuntimeAuthorizationService::class)->requireRetry($actor);
        $op = DB::table('ai_bulk_field_operations')->where(['runtime_batch_id' => $batch->id, 'item_id' => $itemId, 'field' => $field])->lockForUpdate()->first();
        if (! $op) throw new RuntimeException('FIELD_OPERATION_NOT_FOUND');
        if ($op->status === 'DONE') return ['status' => 'REUSED_EXISTING_RESULT', 'field' => $field, 'attempt' => (int) $op->attempts];
        if (in_array($op->status, ['RUNNING', 'QUEUED'], true)) return ['status' => 'DUPLICATE_IN_PROGRESS', 'field' => $field, 'attempt' => (int) $op->attempts];
        if ((int) $op->attempts >= $maxAttempts) throw new RuntimeException('FIELD_MAX_ATTEMPTS');
        $item = DB::table('ai_product_job_items')->where('id', $itemId)->first();
        $contextHash = (string) ($item->technical_context_hash ?? '');
        $key = app(CanonicalJsonHasher::class)->hash(['product_id' => $productId, 'field' => $field, 'operation' => 'field_retry', 'technical_context_hash' => $contextHash, 'prompt_version' => AIContentGovernance::PROMPT_VERSION, 'provider_policy' => 'fake_or_governed', 'source_version' => (int) ($item->draft_id ?? 0)]);
        if (in_array($batch->status, ['COMPLETED', 'COMPLETED_WITH_ERRORS'], true)) {
            $batch->update(['status' => 'RUNNING', 'status_reason' => 'FIELD_RETRY']);
        }
        DB::table('ai_bulk_field_operations')->where('id', $op->id)->update(['status' => 'QUEUED', 'attempts' => (int) $op->attempts + 1, 'max_attempts' => $maxAttempts, 'idempotency_key' => $key, 'actor_id' => $actor?->getAuthIdentifier(), 'next_retry_at' => null, 'last_error_code' => null, 'updated_at' => now()]);
        AiProductFieldRetryJob::dispatch((int) $op->id)->onQueue(config('ai.governed_queue', 'ai_governed'));
        return ['status' => 'QUEUED', 'field' => $field, 'attempt' => (int) $op->attempts + 1, 'idempotency_key' => $key, 'successful_fields_preserved' => true];
    }
}
