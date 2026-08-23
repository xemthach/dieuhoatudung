<?php

namespace App\Services\AI;

final class AIWorkerReadinessService
{
    public function __construct(private AIQueueMonitor $monitor) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $health = $this->monitor->liveStatusHealth();
        $desired = (string) data_get($health, 'worker_desired_state', AIWorkerDesiredStateService::DISABLED);
        $actual = (string) data_get($health, 'worker_heartbeat.health_status', 'OFFLINE');
        $accepting = (bool) data_get($health, 'worker_heartbeat.accepting_new_jobs', false);

        return [
            'desired' => $desired,
            'actual' => $actual,
            'accepting_new_jobs' => $accepting,
            'ready' => $desired === AIWorkerDesiredStateService::ENABLED && $actual === 'ONLINE' && $accepting,
            'message' => match (true) {
                $desired === AIWorkerDesiredStateService::DISABLED => 'AI Worker đang tắt. Yêu cầu đã lưu nhưng chưa thể xử lý.',
                in_array($actual, ['OFFLINE', 'STALE', 'UNKNOWN'], true) => 'AI Worker chưa hoạt động. Yêu cầu đã lưu nhưng chưa thể xử lý.',
                ! $accepting => 'Worker process đang online nhưng chưa nhận job mới.',
                default => 'AI Worker sẵn sàng xử lý.',
            },
            'health' => $health,
        ];
    }
}
