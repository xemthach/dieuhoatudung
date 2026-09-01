<?php

namespace App\Services\AI;

use App\Jobs\AiProductContentBatchJob;
use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\Product;
use App\Models\ProductBulkOperation;
use App\Models\User;
use App\Services\Product\AIBulkApplyExecutor;
use App\Services\Product\AIBulkApplyManifestService;
use App\Services\Product\AIProductDraftApplyService;
use App\Support\CanonicalJsonHasher;
use App\Support\SchemaColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Selection/preflight/result orchestration for Product AI bulk actions.
 * Domain mutations remain in the same single-Product services used by Product Edit.
 */
final class ProductAiBulkWorkflowService
{
    public const ACTION_APPROVE = 'APPROVE';
    public const ACTION_REJECT = 'REJECT';
    public const ACTION_DISCARD = 'DISCARD';
    public const ACTION_REGENERATE = 'REGENERATE';
    public const ACTION_APPLY = 'APPLY';

    public function __construct(
        private readonly AiProductContentStateResolver $states,
        private readonly AiProductWarningClassifier $warnings,
        private readonly ProductAiApplyReadiness $applyReadiness,
        private readonly ProductAiGenerationReadiness $generationReadiness,
        private readonly AIProductDraftApplyService $drafts,
    ) {}

    /** @return array{selected:int,counts:array<string,int>,classifications:array<string,int>,rows:array<int,array<string,mixed>>} */
    public function preflight(array $productIds, bool $includeGenerationReadiness = false): array
    {
        $ids = $this->normalizeIds($productIds);
        $products = Product::query()
            ->whereKey($ids)
            ->with([
                'brand:id,name',
                'tags:id,name,slug',
                'faqs:id,question,answer',
                'aiProductJobItems.draft',
                'aiProductDrafts',
            ])
            ->get()
            ->keyBy(fn (Product $product): int => (int) $product->id);

        $counts = array_fill_keys([
            'NOT_GENERATED', 'QUEUED', 'PROCESSING', 'VALIDATING', 'REVIEW_REQUIRED',
            'APPROVED', 'REJECTED', 'DISCARDED', 'APPLIED', 'BLOCKED', 'FAILED',
        ], 0);
        $classifications = array_fill_keys([
            'READY_TO_REVIEW', 'READY_TO_APPROVE', 'READY_TO_APPLY',
            'REGENERATE_AVAILABLE', 'NOT_ACTIONABLE',
        ], 0);
        $rows = [];
        // Runtime readiness is invariant for the whole selection. Resolve it once
        // so a 358-product preflight does not query worker/provider per Product.
        $generationRuntime = $includeGenerationReadiness ? $this->generationReadiness->runtimeSnapshot() : null;
        $excludedDraftIds = $includeGenerationReadiness
            ? $products->pluck('latestAiProductJobItem.draft.id')->filter()->map(fn ($id): int => (int) $id)->all()
            : [];
        $excludedItemIds = $includeGenerationReadiness
            ? $products->pluck('latestAiProductJobItem.id')->filter()->map(fn ($id): int => (int) $id)->all()
            : [];
        $activeConflicts = $includeGenerationReadiness
            ? $this->generationReadiness->activeConflictProductIds($ids, $excludedDraftIds, $excludedItemIds)
            : [];

        foreach ($ids as $id) {
            $product = $products->get($id);
            if (! $product) {
                $rows[] = $this->missingRow($id);
                $counts['BLOCKED']++;
                $classifications['NOT_ACTIONABLE']++;
                continue;
            }

            $resolved = $this->states->resolve($product);
            $state = $this->normalizeState((string) $resolved['status']);
            $draft = $resolved['draft'];
            $payload = (array) ($draft?->normalized_output_json ?? []);
            $warningSet = $draft
                ? $this->warnings->classify((array) ($draft->warnings_json ?? []), $payload, $product)
                : ['soft_warnings' => [], 'optional_data' => [], 'hard_blockers' => [], 'technical_processed' => [], 'informational' => [], 'counts' => ['soft' => 0, 'optional' => 0, 'hard' => 0, 'technical_processed' => 0, 'informational' => 0]];
            $readiness = $state === 'APPROVED'
                ? $this->applyReadiness->resolve($draft)
                : null;
            $item = $resolved['item'];
            $generationScope = [
                'LONG_DESCRIPTION',
                '_current_job_item_id' => $item?->id,
                '_superseded_draft_id' => $draft?->id,
                '_active_conflict' => isset($activeConflicts[$id]),
            ];
            $generation = $includeGenerationReadiness
                ? $this->generationReadiness->resolve($product, $generationScope, $generationRuntime)
                : null;
            $generatedFields = collect((array) ($draft?->field_status_json ?? $item?->field_status_json ?? []))
                ->filter(fn (mixed $status): bool => in_array(strtoupper((string) $status), ['GENERATED', 'VALID', 'APPLIED'], true))
                ->count();
            $hardBlockers = $warningSet['hard_blockers'];
            if ($state === 'BLOCKED' && $hardBlockers === []) {
                $hardBlockers[] = [
                    'code' => (string) ($item?->status_reason ?: $item?->failed_reason ?: 'BLOCKED'),
                    'type' => 'CONCURRENCY_BLOCK',
                    'label' => app(AiContentStatusPresenter::class)->safeReason($item?->status_reason ?: $item?->failed_reason),
                ];
            }

            $row = [
                'product_id' => $id,
                'product_name' => (string) $product->name,
                'draft_id' => $draft?->id,
                'item_id' => $item?->id,
                'state' => $state,
                'generated_fields' => $generatedFields,
                'soft_warning_count' => count($warningSet['soft_warnings']),
                'optional_data_count' => count($warningSet['optional_data']),
                'hard_blocker_count' => count($hardBlockers),
                'soft_warnings' => $warningSet['soft_warnings'],
                'optional_data' => $warningSet['optional_data'],
                'hard_blockers' => $hardBlockers,
                'score' => $item?->seo_score_after ?? $product->ai_score,
                'provider_called' => (bool) data_get($draft?->token_usage_json, 'provider_called', ((int) ($item?->tokens_used ?? 0)) > 0),
                'updated_at' => $item?->state_changed_at ?: $item?->updated_at,
                'apply_readiness' => $readiness,
                'generation_readiness' => $generation,
                'ready_to_review' => $state === 'REVIEW_REQUIRED' && $draft !== null,
                'ready_to_approve' => $state === 'REVIEW_REQUIRED' && $draft !== null && $hardBlockers === [],
                'ready_to_apply' => $state === 'APPROVED' && (bool) ($readiness['can_apply'] ?? false),
                'regenerate_available' => in_array($state, ['REVIEW_REQUIRED', 'REJECTED', 'DISCARDED', 'FAILED'], true)
                    && $hardBlockers === []
                    && (! $includeGenerationReadiness || (bool) $generation['can_generate']),
            ];
            $rows[] = $row;
            $counts[$state] = ($counts[$state] ?? 0) + 1;

            $classification = match (true) {
                $row['ready_to_apply'] => 'READY_TO_APPLY',
                $row['ready_to_approve'] => 'READY_TO_APPROVE',
                $row['ready_to_review'] => 'READY_TO_REVIEW',
                $row['regenerate_available'] => 'REGENERATE_AVAILABLE',
                default => 'NOT_ACTIONABLE',
            };
            $classifications[$classification]++;
        }

        return compact('counts', 'classifications', 'rows') + ['selected' => count($ids)];
    }

    /** @return array<string,mixed> */
    public function execute(
        string $action,
        array $productIds,
        User $actor,
        array $options = [],
        string $selectionMode = ProductBulkTargetResolver::SELECTED,
        array $filters = [],
    ): array {
        $action = strtoupper($action);
        $this->authorize($action, $actor);
        $ids = $this->normalizeIds($productIds);
        $preflight = $this->preflight($ids, $action === self::ACTION_REGENERATE);
        $operation = $this->createOperation($action, $ids, $actor, $selectionMode, $filters, $preflight);

        if ($action === self::ACTION_REGENERATE) {
            return $this->executeRegenerate($operation, $preflight, $actor, $options);
        }
        if ($action === self::ACTION_APPLY) {
            return $this->executeApply($operation, $preflight, $actor, $options);
        }

        foreach ($preflight['rows'] as $row) {
            $draft = $row['draft_id'] ? AiProductDraft::find($row['draft_id']) : null;
            $eligibility = $this->eligibility($action, $row, $options);
            if (! $eligibility['eligible']) {
                $this->recordResult($operation, $row, $eligibility['result'], $eligibility['reason']);
                continue;
            }

            try {
                DB::transaction(function () use ($action, $draft, $actor, $options, $operation): void {
                    if (! $draft) throw new RuntimeException('DRAFT_MISSING');
                    $note = trim((string) ($options['reason'] ?? ''));
                    $auditNote = trim("[Bulk {$operation->operation_uuid}] {$note}");
                    match ($action) {
                        self::ACTION_APPROVE => $this->drafts->approve(
                            $draft,
                            (int) $actor->id,
                            $actor,
                            $auditNote,
                            null,
                            (bool) ($options['warning_override'] ?? false),
                        ),
                        self::ACTION_REJECT => $this->drafts->reject($draft, (int) $actor->id, $auditNote, $actor),
                        self::ACTION_DISCARD => $this->drafts->discard($draft, (int) $actor->id, $auditNote, $actor),
                        default => throw new RuntimeException('UNSUPPORTED_BULK_ACTION'),
                    };
                });
                $after = $this->preflight([$row['product_id']])['rows'][0]['state'];
                $this->recordResult($operation, $row, 'SUCCESS', $action.'_COMPLETED', $after);
            } catch (Throwable $exception) {
                $result = $this->isBlockedReason($exception->getMessage()) ? 'BLOCKED' : 'FAILED';
                $this->recordResult($operation, $row, $result, $exception->getMessage());
            }
        }

        return $this->finish($operation);
    }

    public function expectedApplyConfirmation(int $readyCount): string
    {
        return 'APPLY '.$readyCount.' PRODUCTS';
    }

    private function executeApply(ProductBulkOperation $operation, array $preflight, User $actor, array $options): array
    {
        $readyRows = collect($preflight['rows'])->where('ready_to_apply', true)->values();
        $expected = $this->expectedApplyConfirmation($readyRows->count());
        if (trim((string) ($options['confirmation'] ?? '')) !== $expected) {
            foreach ($preflight['rows'] as $row) {
                $this->recordResult($operation, $row, 'BLOCKED', 'BULK_APPLY_CONFIRMATION_REQUIRED');
            }
            return $this->finish($operation);
        }

        foreach ($preflight['rows'] as $row) {
            if (! $row['ready_to_apply']) {
                $reason = $row['state'] === 'APPROVED'
                    ? ((bool) data_get($row, 'apply_readiness.stale_target') ? 'STALE_TARGET' : 'HARD_BLOCKED')
                    : ($row['state'] === 'APPLIED' ? 'ALREADY_APPLIED' : 'DRAFT_NOT_APPROVED');
                $result = in_array($reason, ['STALE_TARGET', 'HARD_BLOCKED'], true) ? 'BLOCKED' : 'SKIPPED';
                $this->recordResult($operation, $row, $result, $reason);
            }
        }

        if ($readyRows->isEmpty()) return $this->finish($operation);

        try {
            $drafts = AiProductDraft::query()->whereKey($readyRows->pluck('draft_id'))->get()->all();
            $batch = app(AIBulkApplyManifestService::class)->create($operation->operation_uuid, $drafts, (int) $actor->id);
            $result = app(AIBulkApplyExecutor::class)->applyAsActor(
                $batch->apply_batch_uuid,
                $actor,
                1,
                false,
                $expected,
            );
            foreach ((array) ($result['items'] ?? []) as $itemResult) {
                $row = $readyRows->firstWhere('product_id', (int) $itemResult['product_id']);
                if ($row) {
                    $this->recordResult(
                        $operation,
                        $row,
                        (string) $itemResult['result'],
                        (string) ($itemResult['reason'] ?? 'APPLIED'),
                        (string) ($itemResult['after_state'] ?? 'APPLIED'),
                    );
                }
            }
        } catch (Throwable $exception) {
            foreach ($readyRows as $row) {
                if (! $operation->items()->where('product_id', $row['product_id'])->where('result', '!=', 'PENDING')->exists()) {
                    $this->recordResult($operation, $row, 'FAILED', $exception->getMessage());
                }
            }
        }

        return $this->finish($operation);
    }

    private function executeRegenerate(ProductBulkOperation $operation, array $preflight, User $actor, array $options): array
    {
        $worker = app(AIWorkerReadinessService::class)->snapshot();
        $eligible = collect($preflight['rows'])->where('regenerate_available', true)->values();

        foreach ($preflight['rows'] as $row) {
            if (! $row['regenerate_available']) {
                $generationBlocker = data_get($row, 'generation_readiness.mandatory_blockers.0.code');
                $reason = $generationBlocker ?: 'REGENERATE_NOT_ELIGIBLE';
                $this->recordResult(
                    $operation,
                    $row,
                    ($row['hard_blocker_count'] > 0 || $generationBlocker) ? 'BLOCKED' : 'SKIPPED',
                    $reason,
                );
            }
        }
        if (! $worker['ready']) {
            foreach ($eligible as $row) $this->recordResult($operation, $row, 'BLOCKED', 'AI_WORKER_NOT_READY');
            return $this->finish($operation);
        }
        if ($eligible->isEmpty()) return $this->finish($operation);

        try {
            $job = DB::transaction(function () use ($eligible, $actor, $options): AiProductJob {
                $ids = $eligible->pluck('product_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
                Product::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
                $active = array_keys($this->generationReadiness->activeConflictProductIds(
                    $ids,
                    $eligible->pluck('draft_id')->filter()->map(fn ($id): int => (int) $id)->all(),
                    $eligible->pluck('item_id')->filter()->map(fn ($id): int => (int) $id)->all(),
                ));
                if ($active !== []) throw new RuntimeException('DUPLICATE_IN_PROGRESS:'.implode(',', $active));

                foreach ($eligible as $row) {
                    if ($row['state'] === 'REVIEW_REQUIRED' && $row['draft_id']) {
                        $this->drafts->supersedeForRegeneration(AiProductDraft::findOrFail($row['draft_id']), $actor);
                    }
                }

                $outputs = array_values($options['outputs'] ?? ['content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og']);
                $selectedOutputs = array_fill_keys($outputs, true);
                $config = [
                    'action' => 'regenerate_ai_content',
                    'mode' => $options['mode'] ?? 'rewrite_all',
                    'depth' => $options['depth'] ?? 'seo',
                    'tone' => $options['tone'] ?? 'hvac_expert',
                    'apply_mode' => 'draft_only',
                    'batch_size' => max(1, min((int) ($options['batch_size'] ?? 10), 50)),
                    'outputs' => collect(['content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og'])
                        ->mapWithKeys(fn (string $field): array => [$field => isset($selectedOutputs[$field])])->all(),
                    'operation_generation' => (string) Str::uuid(),
                    'guard_policy_version' => app(AiGuardPolicy::class)->version(),
                    'guard_policy_snapshot' => app(AiGuardPolicy::class)->snapshot(),
                ];
                $job = AiProductJob::create(array_merge([
                    'type' => 'regenerate_ai_content',
                    'scope' => 'selected',
                    'status' => 'queued',
                    'canonical_status' => 'QUEUED',
                    'total' => count($ids),
                    'config_json' => $config,
                    'created_by' => $actor->id,
                ], SchemaColumns::existing('ai_product_jobs', [
                    'module' => 'ai_product_bulk',
                    'queue_name' => config('ai.governed_queue', 'ai_governed'),
                    'selected_product_ids_json' => $ids,
                ])));
                app(ProductBulkGenerationManifest::class)->freeze(
                    $job,
                    ProductBulkTargetResolver::SELECTED,
                    $ids,
                    (int) $actor->id,
                    [],
                    ['operation' => 'regenerate_ai_content', 'requested_fields' => $outputs, 'apply_mode' => 'draft_only'],
                    $actor,
                );

                return $job;
            });

            AiProductContentBatchJob::dispatch($job->id)->onQueue(config('ai.governed_queue', 'ai_governed'));
            foreach ($eligible as $row) $this->recordResult($operation, $row, 'SUCCESS', 'QUEUED_JOB_'.$job->id, 'QUEUED');
        } catch (Throwable $exception) {
            foreach ($eligible as $row) {
                $result = $this->isBlockedReason($exception->getMessage()) ? 'BLOCKED' : 'FAILED';
                $this->recordResult($operation, $row, $result, $exception->getMessage());
            }
        }

        return $this->finish($operation);
    }

    private function eligibility(string $action, array $row, array $options): array
    {
        if ($action === self::ACTION_APPROVE) {
            if (! $row['ready_to_approve']) return ['eligible' => false, 'result' => $row['hard_blocker_count'] ? 'BLOCKED' : 'SKIPPED', 'reason' => 'DRAFT_NOT_APPROVABLE'];
            if ($row['soft_warning_count'] > 0 && ! (bool) ($options['warning_override'] ?? false)) {
                return ['eligible' => false, 'result' => 'SKIPPED', 'reason' => 'WARNING_OVERRIDE_CONFIRMATION_REQUIRED'];
            }
        }
        if (in_array($action, [self::ACTION_REJECT, self::ACTION_DISCARD], true) && $row['state'] !== 'REVIEW_REQUIRED') {
            return ['eligible' => false, 'result' => 'SKIPPED', 'reason' => 'DRAFT_NOT_REVIEWABLE'];
        }
        return ['eligible' => true, 'result' => 'SUCCESS', 'reason' => null];
    }

    private function authorize(string $action, User $actor): void
    {
        $authorization = app(BulkRuntimeAuthorizationService::class);
        match ($action) {
            self::ACTION_APPROVE, self::ACTION_REJECT, self::ACTION_DISCARD => $authorization->requireApprove($actor),
            self::ACTION_REGENERATE => $authorization->requireGenerate($actor),
            self::ACTION_APPLY => $authorization->requireApply($actor),
            default => throw new RuntimeException('UNSUPPORTED_BULK_ACTION'),
        };
    }

    private function createOperation(string $action, array $ids, User $actor, string $selectionMode, array $filters, array $preflight): ProductBulkOperation
    {
        $operation = ProductBulkOperation::create([
            'operation_uuid' => (string) Str::uuid(),
            'actor_id' => $actor->id,
            'action' => $action,
            'selection_mode' => $selectionMode,
            'selected_count' => count($ids),
            'product_ids_hash' => app(CanonicalJsonHasher::class)->hash($ids),
            'product_ids_json' => $ids,
            'filters_json' => $filters,
            'status' => 'RUNNING',
            'summary_json' => ['preflight' => $preflight['counts'], 'classifications' => $preflight['classifications']],
            'started_at' => now(),
        ]);
        foreach ($preflight['rows'] as $row) {
            $operation->items()->create([
                'product_id' => $row['product_id'],
                'draft_id' => $row['draft_id'],
                'before_state' => $row['state'],
                'result' => 'PENDING',
                'metadata_json' => [
                    'soft_warning_count' => $row['soft_warning_count'],
                    'hard_blocker_count' => $row['hard_blocker_count'],
                    'generated_fields' => $row['generated_fields'],
                ],
            ]);
        }
        return $operation;
    }

    private function recordResult(ProductBulkOperation $operation, array $row, string $result, ?string $reason, ?string $afterState = null): void
    {
        $operation->items()->where('product_id', $row['product_id'])->update([
            'after_state' => $afterState ?: $row['state'],
            'result' => $result,
            'reason' => Str::limit((string) $reason, 160, ''),
            'updated_at' => now(),
        ]);
    }

    private function finish(ProductBulkOperation $operation): array
    {
        $counts = $operation->items()->selectRaw('result, COUNT(*) AS aggregate')->groupBy('result')->pluck('aggregate', 'result');
        $summary = [
            'selected' => (int) $operation->selected_count,
            'success' => (int) ($counts['SUCCESS'] ?? 0),
            'skipped' => (int) ($counts['SKIPPED'] ?? 0),
            'blocked' => (int) ($counts['BLOCKED'] ?? 0),
            'failed' => (int) ($counts['FAILED'] ?? 0),
        ];
        $operation->update([
            'status' => $summary['failed'] > 0 || $summary['blocked'] > 0 || $summary['skipped'] > 0 ? 'COMPLETED_PARTIAL' : 'COMPLETED',
            'success_count' => $summary['success'],
            'skipped_count' => $summary['skipped'],
            'blocked_count' => $summary['blocked'],
            'failed_count' => $summary['failed'],
            'summary_json' => array_merge((array) $operation->summary_json, ['result' => $summary]),
            'finished_at' => now(),
        ]);
        return ['operation' => $operation->refresh(), 'summary' => $summary, 'items' => $operation->items()->orderBy('id')->get()->toArray()];
    }

    private function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
        sort($ids);
        if ($ids === []) throw new RuntimeException('EMPTY_SELECTED_SCOPE');
        return $ids;
    }

    private function normalizeState(string $state): string
    {
        return match ($state) {
            'NOT_GENERATED' => 'NOT_GENERATED',
            'QUEUED' => 'QUEUED',
            'PROCESSING', 'RUNNING' => 'PROCESSING',
            'VALIDATING', 'FACT_CHECKING' => 'VALIDATING',
            'REVIEW_REQUIRED' => 'REVIEW_REQUIRED',
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'REJECTED',
            'DISCARDED' => 'DISCARDED',
            'APPLIED' => 'APPLIED',
            'BLOCKED' => 'BLOCKED',
            'FAILED', 'VALIDATION_FAILED', 'PARSE_FAILED' => 'FAILED',
            default => $state,
        };
    }

    private function missingRow(int $id): array
    {
        return [
            'product_id' => $id, 'product_name' => "Product #{$id}", 'draft_id' => null, 'item_id' => null,
            'state' => 'BLOCKED', 'generated_fields' => 0, 'soft_warning_count' => 0, 'hard_blocker_count' => 1,
            'soft_warnings' => [], 'hard_blockers' => [['code' => 'INVALID_TARGET', 'type' => 'INVALID_TARGET', 'label' => 'Sản phẩm không tồn tại.']],
            'score' => null, 'provider_called' => false, 'updated_at' => null, 'apply_readiness' => null,
            'ready_to_review' => false, 'ready_to_approve' => false, 'ready_to_apply' => false, 'regenerate_available' => false,
        ];
    }

    private function isBlockedReason(string $reason): bool
    {
        return Str::contains($reason, ['BLOCK', 'STALE', 'FORBIDDEN', 'CONFLICT', 'CONFIRMATION', 'DUPLICATE']);
    }
}
