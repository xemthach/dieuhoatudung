<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AIWorkerWatchdog extends Command
{
    protected $signature = 'ai:worker-watchdog';

    protected $description = 'Recover the governed worker when enabled and absent; never consumes queue work.';

    public function handle(): int
    {
        $statePath = storage_path('framework/cache/ai-worker-desired-state.json');
        $recoveryPath = storage_path('framework/cache/ai-worker-watchdog.json');
        $desired = File::exists($statePath) ? json_decode(File::get($statePath), true) : ['desired_state' => 'DISABLED'];
        $state = File::exists($recoveryPath) ? (json_decode(File::get($recoveryPath), true) ?: []) : [];
        $state += ['recoveries' => [], 'status' => 'IDLE'];

        if (($desired['desired_state'] ?? 'DISABLED') !== 'ENABLED') {
            $state['status'] = 'DISABLED_BY_OPERATOR';
            File::put($recoveryPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $ownership = storage_path('framework/cache/ai-managed-worker.json');
        $worker = File::exists($ownership) ? (json_decode(File::get($ownership), true) ?: []) : [];
        $supervisorPid = (int) ($worker['supervisor_pid'] ?? 0);
        if ($this->processExists($supervisorPid)) {
            $state['status'] = 'ONLINE';
            File::put($recoveryPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $now = now();
        $recent = collect($state['recoveries'])->filter(fn ($at) => $now->diffInMinutes((string) $at) < 10)->values();
        if ($recent->count() >= 3) {
            $state['status'] = 'WORKER_RECOVERY_BLOCKED';
            $state['blocked_at'] = $now->toIso8601String();
            File::put($recoveryPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::FAILURE;
        }

        exec('schtasks.exe /Run /TN "DieuHoaTuDung-AIGovernedWorker"', $output, $exitCode);
        $state['recoveries'] = $recent->push($now->toIso8601String())->all();
        $state['last_action'] = 'START_MAIN_TASK';
        $state['last_exit_code'] = $exitCode;
        $state['status'] = $exitCode === 0 ? 'RECOVERY_REQUESTED' : 'RECOVERY_FAILED';
        File::put($recoveryPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function processExists(int $pid): bool
    {
        if ($pid <= 0) return false;
        exec('tasklist /FI "PID eq '.((int) $pid).'" /FO CSV /NH', $output, $exitCode);
        return $exitCode === 0 && (bool) preg_grep('/,"'.preg_quote((string) $pid, '/').'",/', $output ?: []);
    }
}
