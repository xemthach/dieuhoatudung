<?php

namespace App\Console\Commands;

use App\Services\AI\AIQueueMonitor;
use Illuminate\Console\Command;

class AIQueueHealth extends Command
{
    protected $signature = 'ai:queue-health {--json : Output JSON} {--record : Record scheduler heartbeat}';

    protected $description = 'Check AI queue, worker heartbeat, scheduler heartbeat, and stuck job counts.';

    public function handle(AIQueueMonitor $monitor): int
    {
        if ($this->option('record')) {
            $monitor->heartbeat('scheduler', 'schedule', 'running');
        }

        $health = $monitor->health();

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Check', 'Value'], collect($health)->map(fn ($value, $key) => [
            $key,
            is_scalar($value) || $value === null
                ? var_export($value, true)
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ])->values()->all());

        if (empty($health['worker_heartbeat']['is_running'])) {
            $this->warn('AI queue worker is not running or no recent heartbeat was recorded.');
        }

        return self::SUCCESS;
    }
}
