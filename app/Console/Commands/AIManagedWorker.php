<?php

namespace App\Console\Commands;

use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AIWorkerDesiredStateService;
use App\Services\AI\AIWorkerRuntimeIdentityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AIManagedWorker extends Command
{
    protected $signature = 'ai:managed-worker
        {--queue= : Governed queue name}
        {--sleep=3}
        {--tries=3}
        {--timeout=900}
        {--once : Process one governed queue pass and exit}';

    protected $description = 'Run the governed AI worker with truthful heartbeat supervision.';

    public function handle(
        AIQueueMonitor $monitor,
        AIWorkerDesiredStateService $desiredState,
        AIWorkerRuntimeIdentityService $runtimeIdentity,
    ): int
    {
        $supervisorPid = getmypid();
        $queue = (string) ($this->option('queue') ?: config('ai.governed_queue', 'ai_governed'));
        $statePath = $desiredState->managedStatePath($queue);
        File::ensureDirectoryExists(dirname($statePath));
        $existing = File::exists($statePath) ? json_decode(File::get($statePath), true) : null;
        if (is_array($existing) && (int) ($existing['supervisor_pid'] ?? 0) !== $supervisorPid
            && $this->processExists((int) ($existing['supervisor_pid'] ?? 0))) {
            $this->warn('ALREADY_RUNNING');
            return self::SUCCESS;
        }
        $connection = (string) config('queue.default', 'database');
        $this->writeState($statePath, [
            'supervisor_pid' => $supervisorPid,
            'child_pid' => null,
            'queue' => $queue,
            'connection' => $connection,
            'runtime' => $runtimeIdentity->current(),
            'started_at' => now()->toIso8601String(),
        ]);
        $command = [
            PHP_BINARY,
            base_path('artisan'),
            'ai:managed-child-worker',
            '--parent-pid='.$supervisorPid,
            '--connection='.$connection,
            '--queue='.$queue,
            '--sleep='.$this->option('sleep'),
            '--tries='.$this->option('tries'),
            '--timeout='.$this->option('timeout'),
        ];

        do {
            $monitor->heartbeat('queue-worker-supervisor', $queue, 'running');
            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['file', storage_path('logs/ai-worker.log'), 'ab'],
                2 => ['file', storage_path('logs/ai-worker.log'), 'ab'],
            ], $pipes, base_path(), null, ['bypass_shell' => true]);

            if (! is_resource($process)) {
                $monitor->heartbeat('queue-worker-supervisor', $queue, 'offline');
                return self::FAILURE;
            }

            $childPid = (int) (proc_get_status($process)['pid'] ?? 0);
            $this->writeState($statePath, ['supervisor_pid' => $supervisorPid, 'child_pid' => $childPid, 'queue' => $queue]);

            while (true) {
                $status = proc_get_status($process);
                $monitor->heartbeat('queue-worker-supervisor', $queue, 'running');
                $this->writeState($statePath, ['supervisor_pid' => $supervisorPid, 'child_pid' => $childPid, 'queue' => $queue, 'heartbeat' => now()->toIso8601String()]);
                if (! $status['running']) {
                    $exitCode = (int) $status['exitcode'];
                    proc_close($process);
                    $monitor->heartbeat('queue-worker-supervisor', $queue, $exitCode === 0 ? 'stopped' : 'offline');
                    $this->writeState($statePath, ['supervisor_pid' => $supervisorPid, 'child_pid' => null, 'queue' => $queue, 'shutdown_requested_at' => now()->toIso8601String(), 'shutdown_result' => $exitCode === 0 ? 'child_exited' : 'child_failed']);
                    if ($this->option('once')) {
                        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
                    }
                    // Unexpected child exit: the supervisor owns recovery and starts one replacement.
                    break;
                }
                sleep(10);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function writeState(string $path, array $values): void
    {
        $state = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        if (array_key_exists('started_at', $values)) {
            unset($state['shutdown_requested_at'], $state['shutdown_result']);
        }
        file_put_contents($path, json_encode(array_merge($state, $values), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function processExists(int $pid): bool
    {
        if ($pid <= 0) return false;
        if (PHP_OS_FAMILY === 'Windows') {
            exec('tasklist /FI "PID eq '.((int) $pid).'" /FO CSV /NH', $output, $exitCode);
            return $exitCode === 0 && (bool) preg_grep('/,"'.preg_quote((string) $pid, '/').'",/', $output ?: []);
        }
        return function_exists('posix_kill') && @posix_kill($pid, 0);
    }
}
