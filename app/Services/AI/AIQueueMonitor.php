<?php

namespace App\Services\AI;

use App\Enums\AIContentJobStatus;
use App\Jobs\AiProductContentSingleJob;
use App\Jobs\GenerateBlogDraftJob;
use App\Models\AiContentJob;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\QueueWorkerHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class AIQueueMonitor
{
    /**
     * Bounded status snapshot for polling surfaces. It deliberately omits logs,
     * command diagnostics and stuck-job scans from the full health report.
     */
    public function liveStatusHealth(?string $queue = null, bool $includeContentProcessing = true): array
    {
        $governedQueue = $queue ?: config('ai.governed_queue', 'ai_governed');
        $hasHeartbeats = Schema::hasTable('queue_worker_heartbeats');
        $worker = $hasHeartbeats
            ? QueueWorkerHeartbeat::query()->where('worker_name', 'queue-worker')->where('queue', $governedQueue)->latest('last_seen_at')->first()
            : null;
        $scheduler = $hasHeartbeats
            ? QueueWorkerHeartbeat::query()->where('worker_name', 'scheduler')->latest('last_seen_at')->first()
            : null;
        $desiredStatePayload = app(AIWorkerDesiredStateService::class)->current();
        $desired = $desiredStatePayload['desired_state'];

        return [
            'pending_jobs_count' => Schema::hasTable('jobs')
                ? DB::table('jobs')->where('queue', $governedQueue)->count()
                : null,
            'failed_jobs_count' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'ai_product_processing_count' => Schema::hasTable('ai_product_job_items')
                ? AiProductJobItem::query()->whereIn('canonical_status', ['RUNNING', 'VALIDATING', 'FACT_CHECKING'])->count()
                : 0,
            'ai_content_processing_count' => $includeContentProcessing && Schema::hasTable('ai_content_jobs')
                ? AiContentJob::query()->where('status', AIContentJobStatus::Processing->value)->count()
                : 0,
            'worker_heartbeat' => $this->workerHeartbeatView($worker, $desired),
            'scheduler_is_running' => optional($scheduler?->last_seen_at)->gt(now()->subMinutes(10)) ?: false,
            'worker_desired_state' => $desired,
            'worker_desired_changed_at' => $desiredStatePayload['changed_at'],
            'worker_desired_changed_by' => $desiredStatePayload['changed_by'],
        ];
    }

    public function heartbeat(string $workerName, ?string $queue = null, string $status = 'running'): void
    {
        if (! Schema::hasTable('queue_worker_heartbeats')) {
            return;
        }

        QueueWorkerHeartbeat::updateOrCreate(
            [
                'worker_name' => $workerName,
                'queue' => $queue ?: 'default',
                'hostname' => gethostname() ?: null,
            ],
            [
                'pid' => getmypid() ?: null,
                'last_seen_at' => now(),
                'status' => $status,
            ],
        );
    }

    public function health(?string $queue = null): array
    {
        $hasJobs = Schema::hasTable('jobs');
        $hasFailedJobs = Schema::hasTable('failed_jobs');
        $governedQueue = $queue ?: config('ai.governed_queue', 'ai_governed');
        $lastWorker = Schema::hasTable('queue_worker_heartbeats')
            ? QueueWorkerHeartbeat::query()->where('worker_name', 'queue-worker')->where('queue', $governedQueue)->latest('last_seen_at')->first()
            : null;
        $lastScheduler = Schema::hasTable('queue_worker_heartbeats')
            ? QueueWorkerHeartbeat::where('worker_name', 'scheduler')->latest('last_seen_at')->first()
            : null;
        $lastWatchdog = Schema::hasTable('queue_worker_heartbeats')
            ? QueueWorkerHeartbeat::where('worker_name', 'ai-worker-watchdog')->where('queue', $governedQueue)->latest('last_seen_at')->first()
            : null;
        $lastProcessed = Schema::hasTable('ai_technical_logs')
            ? DB::table('ai_technical_logs')->whereIn('event', ['job_completed', 'job_failed'])->latest('id')->first()
            : null;
        $desiredStatePayload = app(AIWorkerDesiredStateService::class)->current();
        $workerDesiredState = $desiredStatePayload['desired_state'];
        $applicationRuntime = app(AIWorkerRuntimeIdentityService::class)->current();
        $workerRuntime = $this->managedWorkerRuntime($governedQueue);

        return [
            'queue_connection' => config('queue.default'),
            'jobs_table_exists' => $hasJobs,
            'failed_jobs_table_exists' => $hasFailedJobs,
            'pending_jobs_count' => $hasJobs ? DB::table('jobs')->where('queue', $governedQueue)->count() : null,
            'failed_jobs_count' => $hasFailedJobs ? DB::table('failed_jobs')->count() : null,
            'worker_command' => 'php artisan ai:managed-worker --queue='.$governedQueue.' --sleep=3 --tries=3 --timeout=900',
            'scheduler_command' => 'php artisan schedule:run',
            'ai_content_processing_count' => Schema::hasTable('ai_content_jobs')
                ? AiContentJob::where('status', AIContentJobStatus::Processing->value)->count()
                : null,
            'ai_product_processing_count' => Schema::hasTable('ai_product_job_items')
                ? AiProductJobItem::where('canonical_status', 'RUNNING')
                    ->whereHas('job', fn ($query) => $query->where('queue_name', $governedQueue))
                    ->count()
                : 0,
            'legacy_ai_processing_count' => Schema::hasTable('ai_product_jobs')
                ? AiProductJob::where('canonical_status', 'RUNNING')
                    ->where('status_reason', 'LEGACY_PRE_GOVERNANCE')
                    ->count()
                : 0,
            'ai_jobs_stuck_count' => $this->stuckCount(),
            'last_processed_job' => $lastProcessed ? [
                'module' => $lastProcessed->module,
                'event' => $lastProcessed->event,
                'created_at' => $lastProcessed->created_at,
            ] : null,
            'worker_heartbeat' => $this->workerHeartbeatView($lastWorker, $workerDesiredState),
            'scheduler_heartbeat' => optional($lastScheduler?->last_seen_at)->toDateTimeString(),
            'scheduler_is_running' => optional($lastScheduler?->last_seen_at)->gt(now()->subMinutes(10)) ?: false,
            'watchdog_heartbeat' => optional($lastWatchdog?->last_seen_at)->toDateTimeString(),
            'watchdog_is_running' => optional($lastWatchdog?->last_seen_at)->gt(now()->subMinutes(3)) ?: false,
            'worker_desired_state' => $workerDesiredState,
            'worker_desired_changed_at' => $desiredStatePayload['changed_at'],
            'worker_desired_changed_by' => $desiredStatePayload['changed_by'],
            'application_runtime' => $applicationRuntime,
            'worker_runtime' => $workerRuntime,
            'worker_deployment_status' => $this->deploymentStatus($applicationRuntime, $workerRuntime),
            'worker_self_test' => app(AIWorkerSelfTestService::class)->latest(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function workerHeartbeatView(?QueueWorkerHeartbeat $worker, string $desired): ?array
    {
        if (! $worker) {
            return null;
        }

        $fresh = optional($worker->last_seen_at)->gt(now()->subMinutes(5));
        $recent = optional($worker->last_seen_at)->gt(now()->subHour());
        $processState = strtolower((string) $worker->status);
        $health = ! $recent
            ? 'OFFLINE'
            : (! $fresh ? 'STALE' : (in_array($processState, ['running', 'paused'], true) ? 'ONLINE' : 'OFFLINE'));

        return [
            'worker_name' => $worker->worker_name,
            'queue' => $worker->queue,
            'hostname' => $worker->hostname,
            'pid' => $worker->pid,
            'last_seen_at' => optional($worker->last_seen_at)->toDateTimeString(),
            'last_seen_human' => optional($worker->last_seen_at)->diffForHumans(),
            'process_status' => strtoupper($processState ?: 'unknown'),
            'process_online' => $health === 'ONLINE',
            'is_running' => $health === 'ONLINE' && $processState === 'running',
            'accepting_new_jobs' => $desired === AIWorkerDesiredStateService::ENABLED
                && $health === 'ONLINE'
                && $processState === 'running',
            'health_status' => $health,
        ];
    }

    /** @return array<string, mixed>|null */
    private function managedWorkerRuntime(string $queue): ?array
    {
        $path = app(AIWorkerDesiredStateService::class)->managedStatePath($queue);
        if (! File::exists($path)) {
            return null;
        }

        $state = json_decode(File::get($path), true) ?: [];
        $runtime = is_array($state['runtime'] ?? null) ? $state['runtime'] : null;
        if ($runtime === null) {
            return null;
        }

        return array_merge($runtime, [
            'supervisor_pid' => $state['supervisor_pid'] ?? null,
            'child_pid' => $state['child_pid'] ?? null,
            'started_at' => $state['started_at'] ?? null,
            'heartbeat' => $state['heartbeat'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $application @param array<string, mixed>|null $worker */
    private function deploymentStatus(array $application, ?array $worker): string
    {
        if ($worker === null) {
            return 'UNKNOWN';
        }
        if (($application['app_version'] ?? null) !== ($worker['app_version'] ?? null)) {
            return 'VERSION_MISMATCH';
        }
        if (! empty($application['build_id']) && ! empty($worker['build_id'])
            && $application['build_id'] !== $worker['build_id']) {
            return 'VERSION_MISMATCH';
        }
        if (! empty($application['worker_code_hash'])
            && $application['worker_code_hash'] !== ($worker['worker_code_hash'] ?? null)) {
            return 'VERSION_MISMATCH';
        }

        return 'UP_TO_DATE';
    }

    public function recoverStuck(int $minutes = 15, int $maxRetry = 3): array
    {
        $cutoff = now()->subMinutes($minutes);
        $result = ['redispatched' => 0, 'failed' => 0, 'checked' => 0];

        if (Schema::hasTable('ai_content_jobs')) {
            AiContentJob::query()
                ->whereIn('status', [AIContentJobStatus::Processing->value, AIContentJobStatus::Stuck->value])
                ->where('updated_at', '<', $cutoff)
                ->chunkById(50, function ($jobs) use ($maxRetry, &$result): void {
                    foreach ($jobs as $job) {
                        $result['checked']++;
                        $retryCount = (int) ($job->retry_count ?? 0);

                        if ($retryCount < $maxRetry) {
                            $job->update($this->existingColumns('ai_content_jobs', [
                                'status' => AIContentJobStatus::Queued,
                                'retry_count' => $retryCount + 1,
                                'failed_reason' => 'queue_job_stuck_timeout',
                                'last_error_code' => 'queue_job_stuck_timeout',
                                'last_error_message' => 'Processing too long; redispatched by recovery command.',
                            ]));
                            GenerateBlogDraftJob::dispatch($job->id)->onQueue(config('ai.governed_queue', 'ai_governed'));
                            $result['redispatched']++;
                        } else {
                            $job->update($this->existingColumns('ai_content_jobs', [
                                'status' => AIContentJobStatus::Failed,
                                'failed_reason' => 'queue_job_stuck_timeout',
                                'last_error_code' => 'queue_job_stuck_timeout',
                                'last_error_message' => 'Processing too long and max retry exceeded.',
                            ]));
                            $result['failed']++;
                        }
                    }
                });
        }

        if (Schema::hasTable('ai_product_job_items')) {
            AiProductJobItem::query()
                ->whereIn('status', ['processing', 'stuck'])
                ->where('updated_at', '<', $cutoff)
                ->chunkById(50, function ($items) use ($maxRetry, &$result): void {
                    foreach ($items as $item) {
                        $result['checked']++;
                        $retryCount = (int) ($item->retry_count ?? 0);

                        if ($retryCount < $maxRetry) {
                            $item->update($this->existingColumns('ai_product_job_items', [
                                'status' => 'queued',
                                'retry_count' => $retryCount + 1,
                                'failed_reason' => 'queue_job_stuck_timeout',
                                'last_error_code' => 'queue_job_stuck_timeout',
                                'last_error_message' => 'Processing too long; redispatched by recovery command.',
                            ]));
                            AiProductContentSingleJob::dispatch($item->product_id, $item->ai_product_job_id, $item->id)->onQueue(config('ai.governed_queue', 'ai_governed'));
                            $result['redispatched']++;
                        } else {
                            $item->update($this->existingColumns('ai_product_job_items', [
                                'status' => 'failed',
                                'failed_reason' => 'queue_job_stuck_timeout',
                                'last_error_code' => 'queue_job_stuck_timeout',
                                'last_error_message' => 'Processing too long and max retry exceeded.',
                                'finished_at' => now(),
                            ]));
                            $result['failed']++;
                        }
                    }
                });
        }

        return $result;
    }

    private function stuckCount(): int
    {
        $cutoff = now()->subMinutes(15);
        $count = 0;

        if (Schema::hasTable('ai_content_jobs')) {
            $count += AiContentJob::where('status', AIContentJobStatus::Processing->value)
                ->where('updated_at', '<', $cutoff)
                ->count();
        }

        if (Schema::hasTable('ai_product_job_items')) {
            $count += AiProductJobItem::where('status', 'processing')
                ->where('updated_at', '<', $cutoff)
                ->count();
        }

        return $count;
    }

    private function existingColumns(string $table, array $attributes): array
    {
        return collect($attributes)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn($table, $column))
            ->all();
    }
}
