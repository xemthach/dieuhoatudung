<?php

namespace App\Services\AI;

use App\Models\AiBulkRuntimeBatch;
use App\Models\AiProductJob;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BulkRuntimeBatchService
{
    public function ensure(AiProductJob $job): AiBulkRuntimeBatch
    {
        if (! $job->batch_uuid) throw new RuntimeException('GENERATION_MANIFEST_REQUIRED');
        $config = is_array($job->config_json) ? $job->config_json : [];
        return DB::transaction(function () use ($job, $config): AiBulkRuntimeBatch {
            $batch = AiBulkRuntimeBatch::where('batch_uuid', $job->batch_uuid)->lockForUpdate()->first();
            if ($batch) return $batch;
            $limit = max(1, min((int) ($config['concurrency'] ?? $config['concurrency_limit'] ?? 1), 50));
            $batch = AiBulkRuntimeBatch::create([
                'batch_uuid' => $job->batch_uuid, 'ai_product_job_id' => $job->id,
                'status' => 'QUEUED', 'concurrency_limit' => $limit,
                'token_budget_total' => isset($config['token_budget']) ? (int) $config['token_budget'] : null,
                'max_attempts' => max(1, (int) ($config['max_attempts'] ?? 3)),
            ]);
            for ($slot = 1; $slot <= $limit; $slot++) {
                DB::table('ai_bulk_runtime_slots')->insert([
                    'runtime_batch_id' => $batch->id, 'slot_no' => $slot, 'status' => 'FREE',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $batch->refresh();
        });
    }

    public function pause(AiBulkRuntimeBatch $batch, string $reason = 'PAUSED_BY_OPERATOR'): AiBulkRuntimeBatch
    {
        if (in_array($batch->status, ['COMPLETED', 'CANCELLED', 'FAILED'], true)) throw new RuntimeException('INVALID_RUNTIME_TRANSITION');
        $batch->update(['status' => 'PAUSED', 'status_reason' => $reason, 'pause_requested_at' => now()]);
        return $batch->refresh();
    }

    public function resume(AiBulkRuntimeBatch $batch): AiBulkRuntimeBatch
    {
        if ($batch->status !== 'PAUSED') throw new RuntimeException('INVALID_RUNTIME_TRANSITION');
        if ($batch->status_reason === 'TOKEN_BUDGET_EXCEEDED' && $batch->token_budget_total !== null && $batch->token_reserved >= $batch->token_budget_total) {
            throw new RuntimeException('TOKEN_BUDGET_EXCEEDED');
        }
        $batch->update(['status' => 'RUNNING', 'status_reason' => null, 'pause_requested_at' => null]);
        return $batch->refresh();
    }

    public function cancel(AiBulkRuntimeBatch $batch): AiBulkRuntimeBatch
    {
        if (in_array($batch->status, ['COMPLETED', 'CANCELLED'], true)) return $batch->refresh();
        $batch->update(['status' => 'CANCELLED', 'status_reason' => 'CANCELLED_BY_OPERATOR', 'cancelled_at' => now()]);
        return $batch->refresh();
    }
}
