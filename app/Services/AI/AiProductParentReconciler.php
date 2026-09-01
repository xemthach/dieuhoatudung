<?php

namespace App\Services\AI;

use App\Models\AiProductJob;
use App\Models\AiProductJobItem;

final class AiProductParentReconciler
{
    public function __construct(private readonly AiProductStateCompatibility $compatibility) {}

    public function reconcile(AiProductJob $job): AiProductJob
    {
        $items = $job->items()->get();
        $states = $items->map(fn (AiProductJobItem $item): string => $this->compatibility->item($item)['status']);
        $active = $states->filter(fn (string $state): bool => $this->compatibility->isActive($state));
        $review = $states->filter(fn (string $state): bool => $state === AIJobStateMachine::REVIEW_REQUIRED)->count();
        $done = $states->filter(fn (string $state): bool => $state === AIJobStateMachine::DONE)->count();
        $failed = $states->filter(fn (string $state): bool => $state === AIJobStateMachine::FAILED)->count();
        $blocked = $states->filter(fn (string $state): bool => $state === AIJobStateMachine::BLOCKED)->count();
        $cancelled = $states->filter(fn (string $state): bool => $state === AIJobStateMachine::CANCELLED)->count();
        $processed = $states->count() - $active->count();

        $canonical = $active->isNotEmpty()
            ? $this->highestActivePhase($active->all())
            : $this->terminalParentState($states->count(), $done, $review, $failed, $blocked, $cancelled);
        $legacy = match ($canonical) {
            AIJobStateMachine::QUEUED => 'queued',
            AIJobStateMachine::RUNNING, AIJobStateMachine::VALIDATING, AIJobStateMachine::FACT_CHECKING => 'processing',
            AIJobStateMachine::REVIEW_REQUIRED => 'needs_review',
            AIJobStateMachine::DONE => 'completed',
            AIJobStateMachine::BLOCKED => 'blocked',
            AIJobStateMachine::CANCELLED => 'cancelled',
            default => 'completed_with_errors',
        };

        $job->forceFill([
            'status' => $legacy,
            'canonical_status' => $canonical,
            'processed' => $processed,
            'success' => $done,
            'needs_review' => $review,
            'failed' => $failed + $blocked + $cancelled,
            'finished_at' => $active->isEmpty() ? ($job->finished_at ?: now()) : null,
            'state_changed_at' => now(),
        ])->save();

        return $job->refresh();
    }

    private function highestActivePhase(array $states): string
    {
        foreach ([AIJobStateMachine::FACT_CHECKING, AIJobStateMachine::VALIDATING, AIJobStateMachine::RUNNING, AIJobStateMachine::QUEUED] as $phase) {
            if (in_array($phase, $states, true)) return $phase;
        }
        return AIJobStateMachine::QUEUED;
    }

    private function terminalParentState(int $total, int $done, int $review, int $failed, int $blocked, int $cancelled): string
    {
        if ($total === 0) return AIJobStateMachine::DONE;
        if ($done === $total) return AIJobStateMachine::DONE;
        if ($review > 0 && $failed === 0 && $blocked === 0 && $cancelled === 0) return AIJobStateMachine::REVIEW_REQUIRED;
        if ($blocked === $total) return AIJobStateMachine::BLOCKED;
        if ($cancelled === $total) return AIJobStateMachine::CANCELLED;
        if ($failed > 0 || $blocked > 0 || $cancelled > 0) return AIJobStateMachine::FAILED;
        return $review > 0 ? AIJobStateMachine::REVIEW_REQUIRED : AIJobStateMachine::DONE;
    }
}
