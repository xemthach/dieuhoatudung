<?php

namespace App\Console\Commands;

use App\Jobs\AIManagedWorkerHealthCheckJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AIManagedWorkerHealthCheck extends Command
{
    protected $signature = 'ai:managed-health-check {--probe= : Explicit probe identifier}';

    protected $description = 'Dispatch one non-provider governed worker health probe.';

    public function handle(): int
    {
        $probe = (string) ($this->option('probe') ?: Str::uuid());
        AIManagedWorkerHealthCheckJob::dispatch($probe)->onQueue(config('ai.governed_queue', 'ai_governed'));
        $this->line(json_encode([
            'probe_id' => $probe,
            'queue' => config('ai.governed_queue', 'ai_governed'),
            'status' => 'QUEUED',
            'provider_call' => false,
            'product_mutation' => false,
        ], JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
