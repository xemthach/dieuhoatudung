<?php

namespace App\Console\Commands;

use App\Services\AI\AIQueueMonitor;
use Illuminate\Console\Command;

class AIJobsRecoverStuck extends Command
{
    protected $signature = 'ai:jobs-recover-stuck {--minutes=15 : Processing age before recovery} {--max-retry=3 : Maximum recovery retries}';

    protected $description = 'Recover AI jobs stuck in processing status.';

    public function handle(AIQueueMonitor $monitor): int
    {
        $result = $monitor->recoverStuck(
            minutes: max(1, (int) $this->option('minutes')),
            maxRetry: max(1, (int) $this->option('max-retry')),
        );

        $this->table(['checked', 'redispatched', 'failed'], [[
            $result['checked'],
            $result['redispatched'],
            $result['failed'],
        ]]);

        return self::SUCCESS;
    }
}
