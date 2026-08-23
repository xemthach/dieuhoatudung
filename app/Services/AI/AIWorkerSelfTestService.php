<?php

namespace App\Services\AI;

use App\Jobs\AIManagedWorkerHealthCheckJob;
use App\Models\AiTechnicalLog;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

final class AIWorkerSelfTestService
{
    public function __construct(
        private AITechnicalLogger $logger,
        private AIWorkerRuntimeIdentityService $runtimeIdentity,
    ) {}

    /** @return array{probe_id:string,created:bool,status:string} */
    public function dispatch(User|string|null $actor = null, ?string $probeId = null): array
    {
        $latest = $this->latest();
        if (in_array($latest['status'] ?? null, ['QUEUED', 'CLAIMED'], true)
            && ! empty($latest['updated_at'])
            && now()->diffInMinutes((string) $latest['updated_at']) < 5) {
            return ['probe_id' => (string) $latest['probe_id'], 'created' => false, 'status' => (string) $latest['status']];
        }

        $probeId ??= (string) Str::uuid();
        $actorId = $actor instanceof User ? $actor->getKey() : null;
        $actorName = $actor instanceof User ? $actor->name : ($actor ?: 'system');
        $dispatcherPid = getmypid() ?: null;
        $context = [
            'probe_id' => $probeId,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'dispatcher_pid' => $dispatcherPid,
            'queue' => config('ai.governed_queue', 'ai_governed'),
            'runtime' => $this->runtimeIdentity->current(),
            'provider_call' => false,
            'product_mutation' => false,
        ];

        $this->logger->event('ai_worker_self_test', 'WORKER_SELF_TEST_QUEUED', 'Non-provider worker self-test queued.', $context);

        try {
            AIManagedWorkerHealthCheckJob::dispatch($probeId, $dispatcherPid)->onQueue(config('ai.governed_queue', 'ai_governed'));
        } catch (Throwable $exception) {
            $this->logger->exception('ai_worker_self_test', $exception, context: $context, event: 'WORKER_SELF_TEST_FAILED');
            throw $exception;
        }

        return ['probe_id' => $probeId, 'created' => true, 'status' => 'QUEUED'];
    }

    /** @return array<string, mixed>|null */
    public function latest(): ?array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ai_technical_logs')) {
            return null;
        }

        $logs = AiTechnicalLog::query()
            ->where('module', 'ai_worker_self_test')
            ->latest('id')
            ->limit(30)
            ->get();
        $latest = $logs->first();
        $probeId = data_get($latest?->context_json, 'probe_id');
        if (! $probeId) {
            return null;
        }

        $events = $logs->filter(fn (AiTechnicalLog $log): bool => data_get($log->context_json, 'probe_id') === $probeId);
        $terminal = $events->first(fn (AiTechnicalLog $log): bool => in_array($log->event, ['WORKER_SELF_TEST_COMPLETED', 'WORKER_SELF_TEST_FAILED'], true));
        $claimed = $events->firstWhere('event', 'WORKER_SELF_TEST_CLAIMED');
        $queued = $events->firstWhere('event', 'WORKER_SELF_TEST_QUEUED');
        $status = match ($terminal?->event) {
            'WORKER_SELF_TEST_COMPLETED' => 'COMPLETED',
            'WORKER_SELF_TEST_FAILED' => 'FAILED',
            default => $claimed ? 'CLAIMED' : 'QUEUED',
        };
        $context = $terminal?->context_json ?: $claimed?->context_json ?: $queued?->context_json ?: [];

        return [
            'probe_id' => $probeId,
            'status' => $status,
            'queue' => data_get($context, 'queue'),
            'dispatcher_pid' => data_get($queued?->context_json, 'dispatcher_pid'),
            'worker_pid' => data_get($context, 'worker_pid'),
            'worker_id' => data_get($context, 'worker_id'),
            'worker_runtime' => data_get($context, 'runtime'),
            'queued_at' => optional($queued?->created_at)->toIso8601String(),
            'claimed_at' => optional($claimed?->created_at)->toIso8601String(),
            'completed_at' => optional($terminal?->created_at)->toIso8601String(),
            'updated_at' => optional(($terminal ?: $claimed ?: $queued)?->created_at)->toIso8601String(),
            'cross_process' => data_get($queued?->context_json, 'dispatcher_pid') !== null
                && data_get($context, 'worker_pid') !== null
                && (int) data_get($queued?->context_json, 'dispatcher_pid') !== (int) data_get($context, 'worker_pid'),
            'provider_call' => false,
            'product_mutation' => false,
        ];
    }
}
