<?php

namespace App\Services\AI;

use App\Models\AiProductJob;
use RuntimeException;

class BulkRuntimeAuthorizationService
{
    public function require(object $actor, string $permission): void
    {
        if (! method_exists($actor, 'can') || ! $actor->can($permission)) throw new RuntimeException(strtoupper($permission).'_FORBIDDEN');
    }

    public function requireGenerate(object $actor): void
    {
        if (method_exists($actor, 'can') && ($actor->can('bulk_ai_generate') || $actor->can('product.ai_generate'))) return;
        throw new RuntimeException('BULK_AI_GENERATE_FORBIDDEN');
    }
    public function requireRetry(object $actor): void
    {
        if (method_exists($actor, 'can') && ($actor->can('bulk_ai_retry') || $actor->can('bulk_ai.retry'))) return;
        throw new RuntimeException('BULK_AI_RETRY_FORBIDDEN');
    }
    public function requireApprove(object $actor): void
    {
        if (method_exists($actor, 'can') && ($actor->can('bulk_ai_approve') || $actor->can('bulk_ai.approve'))) return;
        throw new RuntimeException('BULK_AI_APPROVE_FORBIDDEN');
    }
    public function requireApply(object $actor): void
    {
        if (method_exists($actor, 'can') && ($actor->can('bulk_ai_apply') || $actor->can('bulk_ai.apply'))) return;
        throw new RuntimeException('BULK_AI_APPLY_FORBIDDEN');
    }
    public function requireRollback(object $actor): void
    {
        if (method_exists($actor, 'can') && ($actor->can('bulk_ai_rollback') || $actor->can('bulk_ai.rollback'))) return;
        throw new RuntimeException('BULK_AI_ROLLBACK_FORBIDDEN');
    }
    public function requireView(object $actor): void
    {
        if (method_exists($actor, 'can') && ($actor->can('bulk_ai_view') || $actor->can('bulk_ai.view'))) return;
        throw new RuntimeException('BULK_AI_VIEW_FORBIDDEN');
    }

    /**
     * null means full visibility; an empty array means no Product visibility.
     * Scoped viewers receive explicit per-Product permissions only.
     */
    public function viewableProductIds(object $actor): ?array
    {
        $this->requireView($actor);
        if (method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin()) return null;
        if (method_exists($actor, 'can') && $actor->can('bulk_ai_view_all')) return null;

        $permissions = method_exists($actor, 'getAllPermissions')
            ? $actor->getAllPermissions()->pluck('name')->all()
            : [];

        return collect($permissions)
            ->filter(fn (string $name): bool => preg_match('/^bulk_ai_view_product_(\d+)$/', $name) === 1)
            ->map(fn (string $name): int => (int) preg_replace('/^bulk_ai_view_product_/', '', $name))
            ->unique()->values()->all();
    }

    /** Option A: a mixed-scope batch is hidden rather than partially aggregated. */
    public function visibleJobIds(object $actor): ?array
    {
        $allowed = $this->viewableProductIds($actor);
        if ($allowed === null) return null;
        if ($allowed === []) return [];

        $visible = [];
        foreach (AiProductJob::query()->select(['id', 'target_manifest_json'])->cursor() as $job) {
            $ids = array_values(array_unique(array_map('intval', (array) data_get($job->target_manifest_json, 'resolved_product_ids', []))));
            if ($ids !== [] && array_diff($ids, $allowed) === []) $visible[] = (int) $job->id;
        }

        return $visible;
    }

    public function canViewJob(object $actor, AiProductJob $job): bool
    {
        $ids = $this->visibleJobIds($actor);
        return $ids === null || in_array((int) $job->id, $ids, true);
    }
}
