<?php

namespace App\Console\Commands;

use App\Services\AI\AIQueueMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class AIManagedChildWorker extends Command
{
    protected $signature = 'ai:managed-child-worker
        {--parent-pid= : Supervisor PID; child exits when it disappears}
        {--queue=ai_governed}
        {--sleep=3}
        {--tries=3}
        {--timeout=900}';

    protected $description = 'Run governed queue work while supervising the managed worker parent.';

    public function handle(AIQueueMonitor $monitor): int
    {
        $parentPid = (int) $this->option('parent-pid');
        $queue = (string) $this->option('queue');

        while ($parentPid > 0 && $this->processExists($parentPid)) {
            $monitor->heartbeat('queue-worker', $queue, 'running');
            Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => $queue,
                '--sleep' => (int) $this->option('sleep'),
                '--tries' => (int) $this->option('tries'),
                '--timeout' => (int) $this->option('timeout'),
                '--once' => true,
            ]);
        }

        $monitor->heartbeat('queue-worker', $queue, 'stopped');
        $statePath = $this->statePath($queue);
        if (File::exists($statePath)) {
            $state = json_decode(File::get($statePath), true) ?: [];
            if ((int) ($state['supervisor_pid'] ?? 0) === $parentPid) {
                $state['child_pid'] = null;
                $state['shutdown_requested_at'] = now()->toIso8601String();
                $state['shutdown_result'] = 'parent_missing_child_exited';
                File::put($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
        return self::SUCCESS;
    }

    private function processExists(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec('tasklist /FI "PID eq '.((int) $pid).'" /FO CSV /NH', $output, $exitCode);
            return $exitCode === 0 && (bool) preg_grep('/,"'.preg_quote((string) $pid, '/').'",/', $output ?: []);
        }

        return function_exists('posix_kill') && @posix_kill($pid, 0);
    }

    private function statePath(string $queue): string
    {
        $database = (string) config('database.connections.'.config('database.default').'.database', 'unknown');
        $scope = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $database.'_'.$queue) ?: 'unknown';

        return storage_path('framework/cache/ai-managed-worker-'.$scope.'.json');
    }
}
