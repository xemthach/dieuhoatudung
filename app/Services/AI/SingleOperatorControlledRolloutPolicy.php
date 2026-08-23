<?php

namespace App\Services\AI;

use App\Models\User;
use RuntimeException;

/**
 * Explicit exception for a real single-person operation.  It separates the
 * transitions and never grants permission, creates a manifest, or dispatches
 * work by itself.
 */
final class SingleOperatorControlledRolloutPolicy
{
    public const NAME = 'SINGLE_OPERATOR_CONTROLLED_ROLLOUT';

    private const ACTIONS = ['GENERATE', 'REVIEW', 'APPROVE', 'APPLY', 'ROLLBACK'];

    public function snapshot(): array
    {
        $policy = (array) config('ai.single_operator', []);

        return [
            'enabled' => (bool) ($policy['enabled'] ?? false),
            'policy' => (string) ($policy['policy'] ?? self::NAME),
            'operator_user_id' => (int) ($policy['operator_user_id'] ?? 0),
            'super_admin_exception' => (bool) ($policy['super_admin_exception'] ?? false),
            'auto_approve' => (bool) ($policy['auto_approve'] ?? false),
            'auto_apply' => (bool) ($policy['auto_apply'] ?? false),
            'stage_limits' => [
                1 => (int) ($policy['max_stage1_targets'] ?? 1),
                2 => (int) ($policy['max_stage2_targets'] ?? 2),
                3 => (int) ($policy['max_stage3_targets'] ?? 5),
            ],
            'transitions' => ['DRAFT_GENERATED', 'REVIEW_COMPLETED', 'APPROVED', 'APPLY_AUTHORIZED', 'APPLIED'],
            'worker_enablement' => 'OUT_OF_SCOPE',
        ];
    }

    public function active(): bool
    {
        if (app()->environment('testing') && ! (bool) config('ai.single_operator.enforce_in_testing', false)) {
            return false;
        }

        $snapshot = $this->snapshot();
        return $snapshot['enabled']
            && $snapshot['operator_user_id'] > 0
            && $snapshot['policy'] === self::NAME
            && $snapshot['auto_approve'] === false
            && $snapshot['auto_apply'] === false;
    }

    public function assertOperator(User $actor): void
    {
        if (! $this->active()) {
            throw new RuntimeException('SINGLE_OPERATOR_POLICY_INACTIVE');
        }
        $snapshot = $this->snapshot();
        if (! $actor->is_active || (int) $actor->getKey() !== $snapshot['operator_user_id']) {
            throw new RuntimeException('SINGLE_OPERATOR_OPERATOR_MISMATCH');
        }
        if ($actor->isSuperAdmin() && ! $snapshot['super_admin_exception']) {
            throw new RuntimeException('SUPER_ADMIN_BREAK_GLASS_ONLY');
        }
    }

    public function assertAction(User $actor, string $action): void
    {
        $action = strtoupper($action);
        if (! in_array($action, self::ACTIONS, true)) {
            throw new RuntimeException('SINGLE_OPERATOR_UNKNOWN_ACTION');
        }
        $this->assertOperator($actor);
    }

    public function assertDraftOnly(array $config): void
    {
        if ($this->active() && (($config['apply_mode'] ?? 'draft_only') !== 'draft_only' || ($config['auto_approve'] ?? false) || ($config['auto_apply'] ?? false))) {
            throw new RuntimeException('SINGLE_OPERATOR_DRAFT_ONLY_REQUIRED');
        }
    }

    public function assertExplicitApplyConfirmation(?string $confirmation, string $productLabel): void
    {
        $expected = 'APPLY '.$productLabel;
        if (! is_string($confirmation) || trim($confirmation) !== $expected) {
            throw new RuntimeException('APPLY_CONFIRMATION_REQUIRED');
        }
    }

    public function assertExplicitRollbackConfirmation(?string $reason, ?string $confirmation, string $productLabel): void
    {
        if (! is_string($reason) || trim($reason) === '') {
            throw new RuntimeException('ROLLBACK_REASON_REQUIRED');
        }
        if (! is_string($confirmation) || trim($confirmation) !== 'ROLLBACK '.$productLabel) {
            throw new RuntimeException('ROLLBACK_CONFIRMATION_REQUIRED');
        }
    }

    public function assertTransition(string $from, string $to): void
    {
        $allowed = [
            'DRAFT_GENERATED' => 'REVIEW_COMPLETED',
            'REVIEW_COMPLETED' => 'APPROVED',
            'APPROVED' => 'APPLY_AUTHORIZED',
            'APPLY_AUTHORIZED' => 'APPLIED',
        ];
        if (($allowed[$from] ?? null) !== $to) {
            throw new RuntimeException('INVALID_SINGLE_OPERATOR_TRANSITION');
        }
    }
}
