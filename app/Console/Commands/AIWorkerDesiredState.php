<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AIWorkerDesiredState extends Command
{
    protected $signature = 'ai:worker-state {state : ENABLED or DISABLED}';

    protected $description = 'Set the explicit desired state for the governed AI worker.';

    public function handle(): int
    {
        $state = strtoupper((string) $this->argument('state'));
        if (! in_array($state, ['ENABLED', 'DISABLED'], true)) {
            $this->error('State must be ENABLED or DISABLED.');
            return self::FAILURE;
        }

        $path = storage_path('framework/cache/ai-worker-desired-state.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'desired_state' => $state,
            'changed_at' => now()->toIso8601String(),
            'changed_by' => get_current_user(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info($state);
        return self::SUCCESS;
    }
}
