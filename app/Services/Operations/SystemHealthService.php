<?php

namespace App\Services\Operations;

use App\Services\AI\AIQueueMonitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Bounded, read-only operational snapshot for the admin panel.
 * Business data and queue state are never changed by this service.
 */
final class SystemHealthService
{
    public function __construct(private AIQueueMonitor $queueMonitor) {}

    public function snapshot(): array
    {
        $database = $this->database();
        $queueHealth = $this->queueHealth();
        $queue = $this->queue($queueHealth);
        $storage = $this->storage();
        $scheduler = $this->scheduler($queueHealth);
        $worker = $this->worker($queueHealth);

        $components = [
            'database' => $database,
            'cache' => $this->cache(),
            'queue' => $queue,
            'storage' => $storage,
            'scheduler' => $scheduler,
            'worker' => $worker,
        ];

        return [
            'state' => $this->overall($components),
            'generated_at' => now()->toIso8601String(),
            'components' => $components,
            'maintenance' => [
                'missing_media' => $this->countIfColumn('products', 'main_image', null),
                'known_backlogs' => [
                    'mojibake' => 'tracked_separately',
                    'category_schema_mismatch' => 'tracked_separately',
                ],
            ],
        ];
    }

    private function database(): array
    {
        try {
            DB::connection()->getPdo();
            $migrationCount = Schema::hasTable('migrations') ? DB::table('migrations')->count() : null;
            return ['state' => 'HEALTHY', 'driver' => DB::connection()->getDriverName(), 'migration_count' => $migrationCount];
        } catch (Throwable $e) {
            return ['state' => 'CRITICAL', 'reason' => 'database_unreachable'];
        }
    }

    private function cache(): array
    {
        $store = (string) config('cache.default', 'unknown');
        return ['state' => $store === '' ? 'UNKNOWN' : 'HEALTHY', 'store' => $store];
    }

    private function queueHealth(): ?array
    {
        try {
            return $this->queueMonitor->health();
        } catch (Throwable) {
            return null;
        }
    }

    private function queue(?array $health): array
    {
        if ($health === null) {
            return ['state' => 'UNKNOWN', 'reason' => 'queue_status_unavailable'];
        }

        try {
            $pending = $health['pending_jobs_count'];
            $failed = $health['failed_jobs_count'];
            return [
                'state' => ($failed !== null && $failed > 0) || ($health['ai_jobs_stuck_count'] ?? 0) > 0 ? 'WARNING' : 'HEALTHY',
                'connection' => $health['queue_connection'],
                'queue' => config('ai.governed_queue', 'ai_governed'),
                'pending' => $pending,
                'failed' => $failed,
                'stuck' => $health['ai_jobs_stuck_count'],
                'legacy_processing' => $health['legacy_ai_processing_count'],
            ];
        } catch (Throwable $e) {
            return ['state' => 'UNKNOWN', 'reason' => 'queue_status_unavailable'];
        }
    }

    private function storage(): array
    {
        $path = storage_path('app');
        return ['state' => is_dir($path) && is_writable($path) ? 'HEALTHY' : 'WARNING', 'disk' => config('filesystems.default'), 'path_writable' => is_writable($path)];
    }

    private function scheduler(?array $health): array
    {
        if ($health === null) {
            return ['state' => 'UNKNOWN', 'heartbeat_present' => false];
        }

        $running = (bool) data_get($health, 'scheduler_is_running', false);
        return ['state' => $running ? 'HEALTHY' : 'UNKNOWN', 'heartbeat_present' => $running];
    }

    private function worker(?array $health): array
    {
        $desired = (string) data_get($health, 'worker_desired_state', 'DISABLED');
        $actual = data_get($health, 'worker_heartbeat.health_status', 'OFFLINE');
        $accepting = (bool) data_get($health, 'worker_heartbeat.accepting_new_jobs', false);
        $deployment = (string) data_get($health, 'worker_deployment_status', 'UNKNOWN');
        $details = [
            'desired' => $desired,
            'actual' => $actual,
            'accepting_new_jobs' => $accepting,
            'deployment_status' => $deployment,
            'application_version' => data_get($health, 'application_runtime.app_version'),
            'worker_version' => data_get($health, 'worker_runtime.app_version'),
        ];
        if ($desired === 'DISABLED') {
            return array_merge($details, ['state' => $deployment === 'VERSION_MISMATCH' ? 'WARNING' : 'DISABLED', 'accepting_new_jobs' => false]);
        }
        return array_merge($details, ['state' => $deployment === 'VERSION_MISMATCH'
            ? 'CRITICAL'
            : ($actual === 'ONLINE' && $accepting ? 'HEALTHY' : ($actual === 'STALE' ? 'WARNING' : 'CRITICAL'))]);
    }

    private function overall(array $components): string
    {
        $states = array_column($components, 'state');
        if (in_array('CRITICAL', $states, true)) return 'CRITICAL';
        if (in_array('WARNING', $states, true) || in_array('UNKNOWN', $states, true)) return 'WARNING';
        if (in_array('DISABLED', $states, true)) return 'HEALTHY';
        return 'HEALTHY';
    }

    private function countIfColumn(string $table, string $column, mixed $equals): ?int
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) return null;
            return DB::table($table)->whereNull($column)->count();
        } catch (Throwable) {
            return null;
        }
    }
}
