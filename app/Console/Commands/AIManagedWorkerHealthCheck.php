<?php

namespace App\Console\Commands;

use App\Services\AI\AIWorkerSelfTestService;
use Illuminate\Console\Command;

class AIManagedWorkerHealthCheck extends Command
{
    protected $signature = 'ai:managed-health-check {--probe= : Explicit probe identifier}';

    protected $description = 'Dispatch one non-provider governed worker health probe.';

    public function handle(AIWorkerSelfTestService $selfTest): int
    {
        $result = $selfTest->dispatch(get_current_user(), $this->option('probe') ?: null);
        $this->line(json_encode(array_merge($result, [
            'queue' => config('ai.governed_queue', 'ai_governed'),
            'provider_call' => false,
            'product_mutation' => false,
        ]), JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
