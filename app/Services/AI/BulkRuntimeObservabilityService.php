<?php

namespace App\Services\AI;

use App\Models\AiBulkRuntimeBatch;
use Illuminate\Support\Facades\DB;

class BulkRuntimeObservabilityService
{
    public function snapshot(AiBulkRuntimeBatch $batch): array
    {
        $jobId = (int) $batch->ai_product_job_id;
        $items = DB::table('ai_product_job_items')->where('ai_product_job_id', $jobId);
        $counts = $items->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->all();
        $queue = DB::table('ai_product_jobs')->where('id', $jobId)->value('queue_name') ?: config('ai.governed_queue', 'ai_governed');
        $health = app(AIQueueMonitor::class)->health((string) $queue);
        $lastError = DB::table('ai_product_job_items')->where('ai_product_job_id', $jobId)->whereNotNull('last_error_message')->latest('updated_at')->value('last_error_message');
        return [
            'batch_uuid' => $batch->batch_uuid, 'status' => $batch->status, 'status_reason' => $batch->status_reason,
            'target' => (int) DB::table('ai_product_jobs')->where('id', $jobId)->value('total'),
            'scope' => DB::table('ai_product_jobs')->where('id', $jobId)->value('scope_type') ?: DB::table('ai_product_jobs')->where('id', $jobId)->value('scope'),
            'eligible' => (int) ($counts['queued'] ?? 0) + (int) ($counts['processing'] ?? 0) + (int) ($counts['needs_review'] ?? 0) + (int) ($counts['completed'] ?? 0) + (int) ($counts['completed_verified'] ?? 0) + (int) ($counts['failed'] ?? 0),
            'queued' => (int) ($counts['queued'] ?? 0), 'running' => (int) ($counts['processing'] ?? 0),
            'review' => (int) ($counts['needs_review'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0),
            'blocked' => (int) ($counts['blocked'] ?? 0), 'cancelled' => (int) ($counts['cancelled'] ?? 0),
            'paused' => $batch->status === 'PAUSED' ? 1 : 0,
            'completed' => (int) ($counts['completed'] ?? 0) + (int) ($counts['completed_verified'] ?? 0),
            'tokens_reserved' => (int) $batch->token_reserved, 'tokens_consumed' => (int) $batch->token_consumed,
            'provider_calls' => (int) DB::table('ai_providers')->sum('request_count'),
            'retry_count' => (int) DB::table('ai_product_job_items')->where('ai_product_job_id', $jobId)->sum('retry_count'),
            'last_error' => $lastError,
            'worker_health' => data_get($health, 'worker_heartbeat.health_status') ?: (data_get($health, 'worker_heartbeat.is_running') ? 'ONLINE' : 'OFFLINE'),
            'last_heartbeat' => data_get($health, 'worker_heartbeat.last_seen_at'),
        ];
    }
}
