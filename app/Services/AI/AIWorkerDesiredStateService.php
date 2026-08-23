<?php

namespace App\Services\AI;

use App\Models\User;
use InvalidArgumentException;

final class AIWorkerDesiredStateService
{
    public const ENABLED = 'ENABLED';

    public const DISABLED = 'DISABLED';

    /** @return array{desired_state:string,changed_at:?string,changed_by:mixed} */
    public function current(): array
    {
        $payload = is_file($this->statePath())
            ? (json_decode((string) file_get_contents($this->statePath()), true) ?: [])
            : [];

        return [
            'desired_state' => $this->normalize((string) ($payload['desired_state'] ?? self::DISABLED)),
            'changed_at' => $payload['changed_at'] ?? null,
            'changed_by' => $payload['changed_by'] ?? null,
        ];
    }

    /** @return array{before:string,after:string,changed:bool,changed_at:string} */
    public function set(string $state, User|string|null $actor = null): array
    {
        $state = $this->normalize($state);
        $before = $this->current()['desired_state'];
        $changedAt = now()->toIso8601String();
        $actorId = $actor instanceof User ? $actor->getKey() : null;
        $actorName = $actor instanceof User ? $actor->name : ($actor ?: 'system');
        $payload = [
            'desired_state' => $state,
            'changed_at' => $changedAt,
            'changed_by' => $actorId ?: $actorName,
        ];

        $directory = dirname($this->statePath());
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents(
            $this->statePath(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );

        app(AITechnicalLogger::class)->event(
            module: 'ai_worker_control',
            event: $state === self::ENABLED ? 'WORKER_ENABLED' : 'WORKER_DISABLED',
            message: $state === self::ENABLED ? 'AI worker processing enabled by operator.' : 'AI worker graceful disable requested by operator.',
            context: [
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'previous_desired_state' => $before,
                'new_desired_state' => $state,
                'changed_at' => $changedAt,
            ],
        );

        return ['before' => $before, 'after' => $state, 'changed' => $before !== $state, 'changed_at' => $changedAt];
    }

    public function acceptsNewJobs(): bool
    {
        return $this->current()['desired_state'] === self::ENABLED;
    }

    public function statePath(): string
    {
        return (string) config('ai.worker_desired_state_path', storage_path('framework/cache/ai-worker-desired-state.json'));
    }

    public function managedStatePath(?string $queue = null): string
    {
        $queue ??= (string) config('ai.governed_queue', 'ai_governed');
        $database = (string) config('database.connections.'.config('database.default').'.database', 'unknown');
        $scope = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $database.'_'.$queue) ?: 'unknown';

        $directory = rtrim((string) config('ai.managed_state_directory', storage_path('framework/cache')), '\\/');

        return $directory.DIRECTORY_SEPARATOR.'ai-managed-worker-'.$scope.'.json';
    }

    private function normalize(string $state): string
    {
        $state = strtoupper(trim($state));
        if (! in_array($state, [self::ENABLED, self::DISABLED], true)) {
            throw new InvalidArgumentException('Worker desired state must be ENABLED or DISABLED.');
        }

        return $state;
    }
}
