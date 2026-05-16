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
use Illuminate\Support\Facades\Schema;

class AIQueueMonitor
{
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

    public function health(): array
    {
        $hasJobs = Schema::hasTable('jobs');
        $hasFailedJobs = Schema::hasTable('failed_jobs');
        $lastWorker = Schema::hasTable('queue_worker_heartbeats')
            ? QueueWorkerHeartbeat::query()->latest('last_seen_at')->first()
            : null;
        $lastScheduler = Schema::hasTable('queue_worker_heartbeats')
            ? QueueWorkerHeartbeat::where('worker_name', 'scheduler')->latest('last_seen_at')->first()
            : null;
        $lastProcessed = Schema::hasTable('ai_technical_logs')
            ? DB::table('ai_technical_logs')->whereIn('event', ['job_completed', 'job_failed'])->latest('id')->first()
            : null;

        return [
            'queue_connection' => config('queue.default'),
            'jobs_table_exists' => $hasJobs,
            'failed_jobs_table_exists' => $hasFailedJobs,
            'pending_jobs_count' => $hasJobs ? DB::table('jobs')->count() : null,
            'failed_jobs_count' => $hasFailedJobs ? DB::table('failed_jobs')->count() : null,
            'worker_command' => 'php artisan queue:work --queue=ai,default --sleep=3 --tries=3 --timeout=900',
            'scheduler_command' => 'php artisan schedule:run',
            'ai_content_processing_count' => Schema::hasTable('ai_content_jobs')
                ? AiContentJob::where('status', AIContentJobStatus::Processing->value)->count()
                : null,
            'ai_product_processing_count' => Schema::hasTable('ai_product_jobs')
                ? AiProductJob::where('status', 'processing')->count()
                : null,
            'ai_jobs_stuck_count' => $this->stuckCount(),
            'last_processed_job' => $lastProcessed ? [
                'module' => $lastProcessed->module,
                'event' => $lastProcessed->event,
                'created_at' => $lastProcessed->created_at,
            ] : null,
            'worker_heartbeat' => $lastWorker ? [
                'worker_name' => $lastWorker->worker_name,
                'queue' => $lastWorker->queue,
                'last_seen_at' => optional($lastWorker->last_seen_at)->toDateTimeString(),
                'is_running' => optional($lastWorker->last_seen_at)->gt(now()->subMinutes(5)),
            ] : null,
            'scheduler_heartbeat' => optional($lastScheduler?->last_seen_at)->toDateTimeString(),
            'scheduler_is_running' => optional($lastScheduler?->last_seen_at)->gt(now()->subMinutes(10)) ?: false,
        ];
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
                            GenerateBlogDraftJob::dispatch($job->id)->onQueue('ai');
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
                            AiProductContentSingleJob::dispatch($item->product_id, $item->ai_product_job_id, $item->id)->onQueue('ai');
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
