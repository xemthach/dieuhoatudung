<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\AIWorkerDesiredStateService;

class AIWorkerDesiredState extends Command
{
    protected $signature = 'ai:worker-state {state : ENABLED or DISABLED}';

    protected $description = 'Set the explicit desired state for the governed AI worker.';

    public function handle(AIWorkerDesiredStateService $desiredState): int
    {
        $state = strtoupper((string) $this->argument('state'));
        if (! in_array($state, ['ENABLED', 'DISABLED'], true)) {
            $this->error('State must be ENABLED or DISABLED.');
            return self::FAILURE;
        }

        $desiredState->set($state, get_current_user());
        $this->info($state);
        return self::SUCCESS;
    }
}
