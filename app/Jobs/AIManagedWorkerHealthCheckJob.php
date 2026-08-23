<?php

namespace App\Jobs;

use App\Services\AI\AIQueueMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

/**
 * Non-provider operational probe. It deliberately does not load or mutate Product.
 */
class AIManagedWorkerHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $probeId) {}

    public function handle(AIQueueMonitor $monitor): void
    {
        $path = storage_path('app/private/reports/phase2a3_harmless_job.json');
        $existing = File::exists($path) ? json_decode(File::get($path), true) : [];
        $existing[$this->probeId] = [
            'job_id' => $this->job?->getJobId(),
            'queue' => $this->job?->getQueue() ?: config('ai.governed_queue', 'ai_governed'),
            'worker_id' => gethostname().':'.getmypid(),
            'transitions' => [
                ['state' => 'QUEUED', 'at' => null],
                ['state' => 'RUNNING', 'at' => now()->toIso8601String()],
            ],
            'started_at' => now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'final_state' => 'DONE',
            'result' => 'DONE',
            'product_mutation' => false,
            'provider_call' => false,
        ];
        File::put($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $monitor->heartbeat('queue-worker', config('ai.governed_queue', 'ai_governed'), 'running');
    }
}
