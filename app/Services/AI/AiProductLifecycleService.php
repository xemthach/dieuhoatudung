<?php

namespace App\Services\AI;

use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\AiProductDraft;
use App\Models\Product;
use App\Models\User;
use App\Support\SchemaColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiProductLifecycleService
{
    public function __construct(
        private readonly AiProductStateCompatibility $compatibility,
        private readonly AiProductParentReconciler $parents,
    ) {}

    /**
     * Gives each authorized generation operation an immutable identity.
     *
     * The identity is intentionally shared by every item in a bulk operation,
     * while remaining distinct from all historical operations for the same
     * Product/configuration. It is therefore part of the idempotency contract,
     * not a display-only correlation value.
     */
    public function prepareGenerationConfig(array $config): array
    {
        $config['operation_generation'] ??= (string) Str::uuid();
        $config['guard_policy_version'] ??= app(AiGuardPolicy::class)->version();
        $config['guard_policy_snapshot'] ??= app(AiGuardPolicy::class)->snapshot();

        return $config;
    }

    /**
     * Compatibility boundary for queued jobs created before operation identity
     * was frozen at submission time. It never rotates an existing identity.
     */
    public function ensureJobGenerationIdentity(AiProductJob $job): array
    {
        return DB::transaction(function () use ($job): array {
            $locked = AiProductJob::query()->lockForUpdate()->findOrFail($job->id);
            $current = is_array($locked->config_json) ? $locked->config_json : [];
            $prepared = $this->prepareGenerationConfig($current);

            if ($prepared !== $current) {
                $locked->forceFill(['config_json' => $prepared])->save();
            }

            return $prepared;
        });
    }

    public function requestCancel(AiProductJob $job, ?int $actorId, string $reason): AiProductJob
    {
        return DB::transaction(function () use ($job, $actorId, $reason): AiProductJob {
            $locked = AiProductJob::query()->lockForUpdate()->findOrFail($job->id);
            $now = now();
            $locked->forceFill([
                'cancel_requested_at' => $now,
                'cancel_requested_by' => $actorId,
                'cancel_reason' => $reason,
            ])->save();

            $items = AiProductJobItem::query()
                ->where('ai_product_job_id', $locked->id)
                ->lockForUpdate()->get();
            foreach ($items as $item) {
                $state = $this->compatibility->item($item)['status'];
                if (! $this->compatibility->isActive($state)) continue;

                $item->forceFill([
                    'cancel_requested_at' => $now,
                    'cancel_requested_by' => $actorId,
                    'cancel_reason' => $reason,
                ])->save();
                if ($state === AIJobStateMachine::QUEUED) $this->cancelItem($item, 'CANCELLED_BEFORE_WORKER');
            }

            return $this->parents->reconcile($locked);
        });
    }

    /** @return array{AiProductJob,AiProductJobItem,bool} */
    public function createGenerationOperation(
        Product $product,
        array $config,
        User $actor,
        ?AiProductDraft $supersededDraft = null,
        string $type = 'single_product_preview',
    ): array {
        app(BulkRuntimeAuthorizationService::class)->requireGenerate($actor);
        if (app(SingleOperatorControlledRolloutPolicy::class)->active()) {
            app(SingleOperatorControlledRolloutPolicy::class)->assertAction($actor, 'GENERATE');
        }

        return DB::transaction(function () use ($product, $config, $actor, $supersededDraft, $type): array {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $state = app(AiProductContentStateResolver::class)->resolve($locked);
            if ($state['active_operation']) {
                $existing = $state['active_operation'];
                return [$existing->job()->firstOrFail(), $existing, false];
            }
            if ($state['product_state'] === 'INVARIANT_BLOCKED') {
                throw new \RuntimeException('AI_PRODUCT_LINEAGE_INVARIANT: '.implode(',', $state['blockers']));
            }

            $currentDraft = $state['actionable_draft'] ?: $state['approved_draft'];
            if ($currentDraft && (! $supersededDraft || (int) $currentDraft->id !== (int) $supersededDraft->id)) {
                $existing = $state['item'];
                if ($existing?->job) return [$existing->job, $existing, false];
                throw new \RuntimeException('ACTIVE_DRAFT_OR_APPLY_CONFLICT');
            }
            if ($supersededDraft) {
                app(\App\Services\Product\AIProductDraftApplyService::class)
                    ->supersedeForRegeneration($supersededDraft->fresh(), $actor);
            }

            $config = $this->prepareGenerationConfig($config);
            $job = AiProductJob::create(array_merge([
                'type' => $type, 'scope' => 'selected', 'status' => 'queued',
                'canonical_status' => AIJobStateMachine::QUEUED, 'total' => 1,
                'config_json' => $config, 'created_by' => $actor->id,
            ], SchemaColumns::existing('ai_product_jobs', [
                'module' => 'ai_product_content', 'queue_name' => config('ai.governed_queue', 'ai_governed'),
            ])));
            $item = $job->items()->create(array_merge([
                'product_id' => $locked->id, 'status' => 'queued',
                'canonical_status' => AIJobStateMachine::QUEUED,
            ], SchemaColumns::existing('ai_product_job_items', [
                'module' => 'ai_product_content', 'queue_name' => config('ai.governed_queue', 'ai_governed'),
                'dispatch_uuid' => (string) Str::uuid(),
            ])));

            return [$job, $item, true];
        });
    }

    /** @return array{AiProductJob,AiProductJobItem} */
    public function retryAsNewOperation(AiProductJobItem $historicalItem, User $actor): array
    {
        $product = $historicalItem->product()->firstOrFail();
        $config = (array) $historicalItem->job?->config_json;
        $config['retry_of_job_id'] = $historicalItem->ai_product_job_id;
        $config['retry_of_item_id'] = $historicalItem->id;
        [$job, $item, $created] = $this->createGenerationOperation($product, $config, $actor, null, 'manual_retry');
        if (! $created) throw new \RuntimeException('DUPLICATE_IN_PROGRESS');
        return [$job, $item];
    }

    public function checkpointCancellation(AiProductJobItem $item, string $checkpoint): bool
    {
        $item->refresh();
        $state = $this->compatibility->item($item)['status'];
        if ($state === AIJobStateMachine::CANCELLED) return true;
        if (! $item->cancel_requested_at) return false;

        if ($this->compatibility->isActive($state) || $state === AIJobStateMachine::REVIEW_REQUIRED) {
            $this->cancelItem($item, 'CANCELLED_AT_'.$checkpoint);
            $this->parents->reconcile($item->job()->firstOrFail());
        }

        return true;
    }

    public function recoverStaleItem(AiProductJobItem $item, int $maxRetry): bool
    {
        return DB::transaction(function () use ($item, $maxRetry): bool {
            $locked = AiProductJobItem::query()->lockForUpdate()->findOrFail($item->id);
            $state = $this->compatibility->item($locked)['status'];
            if (! $this->compatibility->isActive($state) || $locked->cancel_requested_at) return false;
            if ((int) $locked->retry_count >= $maxRetry) {
                AIJobStateMachine::transition($locked, AIJobStateMachine::FAILED, 'queue_job_stuck_timeout');
                $locked->forceFill([
                    'status' => 'failed', 'failed_reason' => 'queue_job_stuck_timeout',
                    'last_error_code' => 'queue_job_stuck_timeout',
                    'last_error_message' => 'Processing too long and max retry exceeded.', 'finished_at' => now(),
                ])->save();
                $this->parents->reconcile($locked->job()->firstOrFail());
                return false;
            }

            AIJobStateMachine::transition($locked, AIJobStateMachine::QUEUED, 'stale_operation_recovery');
            $locked->forceFill([
                'status' => 'queued', 'retry_count' => (int) $locked->retry_count + 1,
                'dispatch_uuid' => (string) Str::uuid(), 'failed_reason' => 'queue_job_stuck_timeout',
                'last_error_code' => 'queue_job_stuck_timeout',
                'last_error_message' => 'Stale active operation was correlated and redispatched.',
                'finished_at' => null,
            ])->save();
            $this->parents->reconcile($locked->job()->firstOrFail());
            return true;
        });
    }

    public function isRecoverable(AiProductJobItem $item, int $staleMinutes = 15): bool
    {
        $state = $this->compatibility->item($item)['status'];
        if (! $this->compatibility->isActive($state) || $item->cancel_requested_at) return false;
        if (! $item->updated_at || $item->updated_at->gt(now()->subMinutes($staleMinutes))) return false;

        if ($item->dispatch_uuid && DB::table('jobs')
            ->where('queue', config('ai.governed_queue', 'ai_governed'))
            ->where('payload', 'like', '%'.$item->dispatch_uuid.'%')->exists()) {
            return false;
        }

        if (DB::getSchemaBuilder()->hasTable('ai_bulk_runtime_leases')
            && DB::table('ai_bulk_runtime_leases')->where('item_id', $item->id)->where('status', 'CLAIMED')->exists()) {
            return false;
        }

        return true;
    }

    public function reconcile(AiProductJob $job): AiProductJob
    {
        return $this->parents->reconcile($job);
    }

    private function cancelItem(AiProductJobItem $item, string $reason): void
    {
        $state = $this->compatibility->item($item)['status'];
        if ($state !== AIJobStateMachine::CANCELLED) {
            AIJobStateMachine::transition($item, AIJobStateMachine::CANCELLED, $reason);
        }
        $item->forceFill([
            'status' => 'cancelled', 'status_reason' => $reason,
            'failed_reason' => 'job_cancelled', 'last_error_code' => 'job_cancelled',
            'last_error_message' => 'Cancelled by operator.', 'error_message' => 'Cancelled by operator.',
            'cancelled_at' => now(), 'finished_at' => now(),
        ])->save();

        if ($item->draft_id) {
            $item->draft()->whereNull('applied_at')->update([
                'status' => 'cancelled', 'approval_status' => 'CANCELLED', 'updated_at' => now(),
            ]);
        }
    }
}
