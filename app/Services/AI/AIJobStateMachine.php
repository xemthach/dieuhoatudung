<?php

namespace App\Services\AI;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use App\Support\SchemaColumns;

final class AIJobStateMachine
{
    public const QUEUED = 'QUEUED';
    public const RUNNING = 'RUNNING';
    public const VALIDATING = 'VALIDATING';
    public const FACT_CHECKING = 'FACT_CHECKING';
    public const REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    public const DONE = 'DONE';
    public const FAILED = 'FAILED';
    public const BLOCKED = 'BLOCKED';
    public const CANCELLED = 'CANCELLED';

    public static function fromLegacy(?string $status): string
    {
        return match ($status) {
            'queued', 'pending', 'draft' => self::QUEUED,
            'processing' => self::RUNNING,
            'validating' => self::VALIDATING,
            'fact_checking' => self::FACT_CHECKING,
            'needs_review', 'completed_with_warnings', 'reviewed' => self::REVIEW_REQUIRED,
            'completed', 'completed_verified' => self::DONE,
            'cancelled' => self::CANCELLED,
            'blocked', 'stuck' => self::BLOCKED,
            'failed', 'completed_with_errors' => self::FAILED,
            default => self::QUEUED,
        };
    }

    public static function transition(Model $model, string $to, ?string $reason = null): void
    {
        $from = (string) ($model->canonical_status ?: self::fromLegacy($model->status ?? null));
        $allowed = [
            self::QUEUED => [self::RUNNING, self::VALIDATING, self::FAILED, self::CANCELLED, self::BLOCKED],
            self::RUNNING => [self::QUEUED, self::VALIDATING, self::FAILED, self::BLOCKED, self::CANCELLED],
            self::VALIDATING => [self::FACT_CHECKING, self::FAILED, self::BLOCKED],
            self::FACT_CHECKING => [self::REVIEW_REQUIRED, self::DONE, self::FAILED, self::BLOCKED],
            self::REVIEW_REQUIRED => [self::DONE, self::BLOCKED, self::CANCELLED],
            self::DONE => [],
            self::FAILED => [self::QUEUED, self::CANCELLED, self::BLOCKED],
            self::BLOCKED => [self::REVIEW_REQUIRED, self::CANCELLED],
            self::CANCELLED => [],
        ];
        if ($from !== $to && ! in_array($to, $allowed[$from] ?? [], true)) {
            throw new InvalidArgumentException("Invalid AI state transition: {$from} -> {$to}");
        }
        $model->forceFill(SchemaColumns::existing($model->getTable(), [
            'canonical_status' => $to,
            'status_reason' => $reason,
            'state_changed_at' => now(),
        ]))->saveQuietly();

        if ($from !== $to) {
            app(AITechnicalLogger::class)->event('ai_state_machine', 'state_transition', 'AI state transition.', [
                'from_state' => $from,
                'to_state' => $to,
                'reason' => $reason,
                'model' => $model::class,
                'model_id' => $model->getKey(),
            ], $model);
        }
    }
}
