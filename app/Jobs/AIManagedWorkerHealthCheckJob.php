<?php

namespace App\Jobs;

use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AITechnicalLogger;
use App\Services\AI\AIWorkerRuntimeIdentityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Non-provider operational probe. It deliberately does not load or mutate Product.
 */
class AIManagedWorkerHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public readonly string $probeId,
        public readonly ?int $dispatcherPid = null,
    ) {}

    public function handle(
        AIQueueMonitor $monitor,
        AITechnicalLogger $logger,
        AIWorkerRuntimeIdentityService $runtimeIdentity,
    ): void
    {
        $context = [
            'probe_id' => $this->probeId,
            'queue_job_id' => $this->job?->getJobId(),
            'queue' => $this->job?->getQueue() ?: config('ai.governed_queue', 'ai_governed'),
            'worker_id' => gethostname().':'.getmypid(),
            'worker_pid' => getmypid() ?: null,
            'dispatcher_pid' => $this->dispatcherPid,
            'runtime' => $runtimeIdentity->current(),
            'product_mutation' => false,
            'provider_call' => false,
        ];
        $logger->event('ai_worker_self_test', 'WORKER_SELF_TEST_CLAIMED', 'Worker claimed non-provider self-test.', $context);
        $monitor->heartbeat('queue-worker', config('ai.governed_queue', 'ai_governed'), 'running');
        $logger->event('ai_worker_self_test', 'WORKER_SELF_TEST_COMPLETED', 'Worker completed non-provider self-test.', $context);
    }

    public function failed(?Throwable $exception): void
    {
        app(AITechnicalLogger::class)->event(
            'ai_worker_self_test',
            'WORKER_SELF_TEST_FAILED',
            'Non-provider worker self-test failed.',
            [
                'probe_id' => $this->probeId,
                'dispatcher_pid' => $this->dispatcherPid,
                'worker_pid' => getmypid() ?: null,
                'error_class' => $exception ? class_basename($exception) : null,
                'provider_call' => false,
                'product_mutation' => false,
            ],
            level: 'error',
        );
    }
}
