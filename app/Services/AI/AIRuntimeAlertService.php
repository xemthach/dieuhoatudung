<?php

namespace App\Services\AI;

use App\Models\AiTechnicalLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class AIRuntimeAlertService
{
    private const SEVERITY = [
        'WORKER_OFFLINE' => 'CRITICAL',
        'QUEUE_STUCK' => 'WARNING',
        'TOKEN_BUDGET_INSUFFICIENT' => 'WARNING',
        'BUDGET_CONTRACT_VIOLATION' => 'CRITICAL',
        'REPEATED_RATE_LIMIT' => 'WARNING',
        'HIGH_BLOCKED_RATIO' => 'WARNING',
        'STALE_JOB' => 'WARNING',
        'TEST_ALERT' => 'INFO',
    ];

    public function emit(string $event, string $resource, array $context = [], ?string $desiredWorkerState = null): array
    {
        $severity = self::SEVERITY[$event] ?? 'WARNING';
        if ($event === 'WORKER_OFFLINE' && strtoupper((string) $desiredWorkerState) === 'DISABLED') {
            return ['status' => 'SUPPRESSED_INTENTIONAL_DISABLED', 'event' => $event];
        }

        $safe = app(AITechnicalLogger::class)->publicContext($context);
        $resourceId = (string) ($safe['resource_id'] ?? $safe['batch_id'] ?? $safe['job_id'] ?? 'global');
        $dedupKey = hash('sha256', implode('|', [$event, $resource, $resourceId, $severity]));
        $cooldown = (int) config('ai.alerts.cooldown_seconds', 300);

        if (Schema::hasTable('ai_technical_logs')) {
            $duplicate = AiTechnicalLog::query()
                ->where('module', 'ai_runtime_alert')
                ->where('event', 'alert_opened')
                ->where('message', $dedupKey)
                ->where('created_at', '>=', now()->subSeconds($cooldown))
                ->exists();
            if ($duplicate) return ['status' => 'DEDUP_SUPPRESSED', 'event' => $event, 'dedup_key' => $dedupKey];

            AiTechnicalLog::create([
                'module' => 'ai_runtime_alert',
                'ai_job_type' => $resource,
                'ai_job_id' => is_numeric($resourceId) ? (int) $resourceId : null,
                'level' => strtolower($severity),
                'event' => 'alert_opened',
                'message' => $dedupKey,
                'context_json' => [
                    'event_type' => $event,
                    'severity' => $severity,
                    'status' => 'OPEN',
                    'resource' => $resource,
                    'resource_id' => $resourceId,
                    'dedup_key' => $dedupKey,
                    'context' => $safe,
                    'occurred_at' => now()->toIso8601String(),
                ],
            ]);
        }

        Log::channel('ai-jobs')->log(strtolower($severity), 'AI runtime alert: '.$event, [
            'event_type' => $event,
            'severity' => $severity,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'dedup_key' => $dedupKey,
            'context' => $safe,
        ]);

        return ['status' => 'DELIVERED', 'event' => $event, 'severity' => $severity, 'dedup_key' => $dedupKey];
    }
}
