<?php

namespace App\Services\AI;

final class AIWorkerRuntimeIdentityService
{
    /** @return array<string, mixed> */
    public function current(): array
    {
        $connection = (string) config('queue.default', 'database');
        $databaseConnection = (string) (config("queue.connections.{$connection}.connection") ?: config('database.default'));

        return [
            'app_version' => $this->version(),
            'build_id' => $this->buildId(),
            'worker_code_hash' => $this->workerCodeHash(),
            'environment' => (string) app()->environment(),
            'php_version' => PHP_VERSION,
            'queue_connection' => $connection,
            'queue' => (string) config('ai.governed_queue', 'ai_governed'),
            'database_connection' => $databaseConnection,
            'database_host' => (string) config("database.connections.{$databaseConnection}.host", ''),
            'database_port' => (string) config("database.connections.{$databaseConnection}.port", ''),
            'database_name' => (string) config("database.connections.{$databaseConnection}.database", ''),
        ];
    }

    public function version(): string
    {
        $path = base_path('VERSION');

        return is_file($path) ? trim((string) file_get_contents($path)) : 'unknown';
    }

    public function buildId(): ?string
    {
        $configured = trim((string) config('app.build_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $git = base_path('.git');
        $headPath = $git.DIRECTORY_SEPARATOR.'HEAD';
        if (! is_file($headPath)) {
            return null;
        }

        $head = trim((string) file_get_contents($headPath));
        if (! str_starts_with($head, 'ref: ')) {
            return preg_match('/^[a-f0-9]{40}$/i', $head) ? $head : null;
        }

        $ref = substr($head, 5);
        $refPath = $git.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $ref);
        if (is_file($refPath)) {
            $hash = trim((string) file_get_contents($refPath));

            return preg_match('/^[a-f0-9]{40}$/i', $hash) ? $hash : null;
        }

        $packed = $git.DIRECTORY_SEPARATOR.'packed-refs';
        if (is_file($packed) && preg_match('/^([a-f0-9]{40})\s+'.preg_quote($ref, '/').'$/mi', (string) file_get_contents($packed), $match)) {
            return $match[1];
        }

        return null;
    }

    public function workerCodeHash(): string
    {
        $files = [
            base_path('VERSION'),
            base_path('composer.lock'),
            config_path('ai.php'),
            config_path('queue.php'),
            app_path('Console/Commands/AIManagedWorker.php'),
            app_path('Console/Commands/AIManagedChildWorker.php'),
            app_path('Console/Commands/AIWorkerWatchdog.php'),
            app_path('Jobs/AIManagedWorkerHealthCheckJob.php'),
            app_path('Services/AI/AIWorkerDesiredStateService.php'),
            app_path('Services/AI/AIWorkerRuntimeIdentityService.php'),
            app_path('Services/AI/AIWorkerSelfTestService.php'),
        ];
        $hash = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($hash, str_replace('\\', '/', $file)."\0");
            hash_update($hash, is_file($file) ? (string) file_get_contents($file) : 'MISSING');
            hash_update($hash, "\0");
        }

        return hash_final($hash);
    }
}
