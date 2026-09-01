<?php

namespace App\Services\AI;

use App\Models\AiProductDraft;
use App\Models\AiProductJobItem;
use App\Models\Product;
use Illuminate\Support\Collection;

final class AiProductContentStateResolver
{
    public function __construct(private readonly AiProductStateCompatibility $compatibility) {}

    /** @return array<string,mixed> */
    public function resolve(Product $product, ?AiProductJobItem $item = null): array
    {
        $items = $item
            ? collect([$item->loadMissing('draft')])
            : ($product->relationLoaded('aiProductJobItems')
                ? $product->aiProductJobItems->sortByDesc('id')->values()
                : $product->aiProductJobItems()->with('draft')->latest('id')->get());
        $drafts = $product->relationLoaded('aiProductDrafts')
            ? $product->aiProductDrafts->sortByDesc('id')->values()
            : $product->aiProductDrafts()->latest('id')->get();
        $itemStates = $items->mapWithKeys(fn (AiProductJobItem $candidate): array => [
            $candidate->id => $this->compatibility->item($candidate),
        ]);
        $activeItems = $items->filter(fn (AiProductJobItem $candidate): bool =>
            $this->compatibility->isActive((string) $itemStates[$candidate->id]['status'])
        )->values();
        $reviewDrafts = $drafts->filter(fn (AiProductDraft $draft): bool => $this->isReviewable($draft))->values();
        $approvedDrafts = $drafts->filter(fn (AiProductDraft $draft): bool => $this->isApproved($draft))->values();
        $applyingDrafts = $drafts->filter(fn (AiProductDraft $draft): bool => $this->isApplying($draft))->values();
        $actionableDrafts = $reviewDrafts->concat($approvedDrafts)->unique('id')->values();
        $violations = $itemStates
            ->filter(fn (array $state): bool => filled($state['violation']))
            ->map(fn (array $state, int|string $id): array => [
                'code' => $state['violation'], 'entity' => 'ai_product_job_item', 'id' => (int) $id,
                'canonical' => $state['canonical'], 'legacy' => $state['legacy'],
            ])->values()->all();

        if ($activeItems->count() > 1) $violations[] = ['code' => 'MULTIPLE_ACTIVE_OPERATIONS', 'ids' => $activeItems->pluck('id')->all()];
        if ($actionableDrafts->count() > 1) $violations[] = ['code' => 'MULTIPLE_ACTIONABLE_DRAFTS', 'ids' => $actionableDrafts->pluck('id')->all()];
        if ($applyingDrafts->count() > 1) $violations[] = ['code' => 'MULTIPLE_APPLYING_DRAFTS', 'ids' => $applyingDrafts->pluck('id')->all()];

        $latestItem = $items->first();
        $latestDraft = $drafts->first();
        $currentItem = $activeItems->first();
        $currentDraft = $applyingDrafts->first() ?: $actionableDrafts->first();

        if ($activeItems->count() > 1 || $actionableDrafts->count() > 1 || $applyingDrafts->count() > 1) {
            $issue = (string) ($violations[array_key_last($violations)]['code'] ?? 'AI_PRODUCT_INVARIANT_VIOLATION');
            return $this->result(
                status: 'BLOCKED', productState: 'INVARIANT_BLOCKED', item: $currentItem ?: $latestItem,
                draft: $currentDraft ?: $latestDraft, activeOperation: $currentItem,
                actionableDraft: $reviewDrafts->first(), approvedDraft: $approvedDrafts->first(),
                latestItem: $latestItem, latestDraft: $latestDraft, stateIssue: $issue,
                violations: $violations, blockers: array_values(array_unique(array_column($violations, 'code'))),
            );
        }

        if ($currentItem) {
            $effective = (string) $itemStates[$currentItem->id]['status'];
            $status = app(AiContentStatusPresenter::class)->normalize($effective);
            return $this->result(
                status: $status, productState: 'PROCESSING', item: $currentItem, draft: $currentItem->draft,
                activeOperation: $currentItem, actionableDraft: null, approvedDraft: null,
                latestItem: $latestItem, latestDraft: $latestDraft,
                stateIssue: $itemStates[$currentItem->id]['violation'], violations: $violations,
            );
        }

        if ($applyingDrafts->isNotEmpty()) {
            $draft = $applyingDrafts->first();
            return $this->result(
                status: 'PROCESSING', productState: 'APPLYING', item: $this->itemForDraft($items, $draft),
                draft: $draft, activeOperation: null, actionableDraft: null, approvedDraft: $draft,
                latestItem: $latestItem, latestDraft: $latestDraft, stateIssue: null,
                violations: $violations, blockers: ['APPLY_IN_PROGRESS'],
            );
        }

        if ($reviewDrafts->isNotEmpty()) {
            $draft = $reviewDrafts->first();
            return $this->result(
                status: 'REVIEW_REQUIRED', productState: 'REVIEW_REQUIRED', item: $this->itemForDraft($items, $draft),
                draft: $draft, activeOperation: null, actionableDraft: $draft, approvedDraft: null,
                latestItem: $latestItem, latestDraft: $latestDraft, stateIssue: null, violations: $violations,
                nextActions: ['PREVIEW', 'APPROVE', 'REJECT', 'DISCARD', 'REGENERATE'],
            );
        }

        if ($approvedDrafts->isNotEmpty()) {
            $draft = $approvedDrafts->first();
            return $this->result(
                status: 'APPROVED', productState: 'APPROVED', item: $this->itemForDraft($items, $draft),
                draft: $draft, activeOperation: null, actionableDraft: null, approvedDraft: $draft,
                latestItem: $latestItem, latestDraft: $latestDraft, stateIssue: null, violations: $violations,
                nextActions: ['PREVIEW', 'APPLY'],
            );
        }

        if (! $latestItem && ! $latestDraft) {
            return $this->result(
                status: 'NOT_GENERATED', productState: 'AVAILABLE', item: null, draft: null,
                activeOperation: null, actionableDraft: null, approvedDraft: null,
                latestItem: null, latestDraft: null,
                stateIssue: filled($product->ai_status) && $product->ai_status !== 'not_generated'
                    ? 'STALE_DENORMALIZED_STATUS' : null,
                violations: $violations, nextActions: ['GENERATE'],
            );
        }

        if ($latestItem && ! $latestItem->draft
            && ($itemStates[$latestItem->id]['status'] ?? null) === AIJobStateMachine::REVIEW_REQUIRED) {
            return $this->result(
                status: 'BLOCKED', productState: 'INVARIANT_BLOCKED', item: $latestItem, draft: $latestDraft,
                activeOperation: null, actionableDraft: null, approvedDraft: null,
                latestItem: $latestItem, latestDraft: $latestDraft, stateIssue: 'REVIEWABLE_DRAFT_MISSING',
                violations: array_merge($violations, [[
                    'code' => 'REVIEWABLE_DRAFT_MISSING', 'entity' => 'ai_product_job_item', 'id' => $latestItem->id,
                ]]), blockers: ['REVIEWABLE_DRAFT_MISSING'],
            );
        }

        [$historyStatus, $historyApplied] = $this->historyStatus($latestItem, $latestDraft, $itemStates);
        return $this->result(
            status: $historyStatus, productState: 'AVAILABLE', item: $latestItem,
            draft: $latestItem?->draft ?: $latestDraft, activeOperation: null, actionableDraft: null,
            approvedDraft: null, latestItem: $latestItem, latestDraft: $latestDraft,
            stateIssue: null, violations: $violations, applied: $historyApplied, nextActions: ['GENERATE'],
        );
    }

    public function reviewableDraft(Product $product): ?AiProductDraft
    {
        return $this->resolve($product)['actionable_draft'];
    }

    public function approvedUnappliedDraft(Product $product): ?AiProductDraft
    {
        return $this->resolve($product)['approved_draft'];
    }

    private function isReviewable(AiProductDraft $draft): bool
    {
        return $draft->approval_status === 'REVIEW_REQUIRED'
            && in_array(strtolower((string) $draft->status), ['needs_review', 'review_required'], true)
            && ! $draft->applied_at;
    }

    private function isApproved(AiProductDraft $draft): bool
    {
        return $draft->approval_status === 'APPROVED_FOR_APPLY' && ! $draft->applied_at;
    }

    private function isApplying(AiProductDraft $draft): bool
    {
        return strtolower((string) $draft->status) === 'applying' && ! $draft->applied_at;
    }

    private function itemForDraft(Collection $items, AiProductDraft $draft): ?AiProductJobItem
    {
        return $items->first(fn (AiProductJobItem $item): bool => (int) $item->draft_id === (int) $draft->id);
    }

    /** @return array{string,bool} */
    private function historyStatus(?AiProductJobItem $item, ?AiProductDraft $draft, Collection $itemStates): array
    {
        $historyDraft = $item?->draft ?: $draft;
        if ($historyDraft?->applied_at || $historyDraft?->approval_status === 'APPLIED') return ['APPLIED', true];
        if ($historyDraft?->approval_status === 'REJECTED') return ['REJECTED', false];
        if ($historyDraft?->approval_status === 'DISCARDED' || strtolower((string) $historyDraft?->status) === 'discarded') return ['DISCARDED', false];
        if ($item) return [app(AiContentStatusPresenter::class)->normalize((string) $itemStates[$item->id]['status']), false];
        return ['NOT_GENERATED', false];
    }

    /** @return array<string,mixed> */
    private function result(
        string $status, string $productState, ?AiProductJobItem $item, ?AiProductDraft $draft,
        ?AiProductJobItem $activeOperation, ?AiProductDraft $actionableDraft, ?AiProductDraft $approvedDraft,
        ?AiProductJobItem $latestItem, ?AiProductDraft $latestDraft, ?string $stateIssue, array $violations,
        array $blockers = [], array $nextActions = [], bool $applied = false,
    ): array {
        return [
            'status' => $status, 'product_state' => $productState, 'item' => $item, 'draft' => $draft,
            'active_operation' => $activeOperation, 'actionable_draft' => $actionableDraft,
            'approved_draft' => $approvedDraft, 'latest_history' => ['item' => $latestItem, 'draft' => $latestDraft],
            'blockers' => $blockers, 'next_actions' => $nextActions, 'invariant_violations' => $violations,
            'reviewable' => $status === 'REVIEW_REQUIRED' && $actionableDraft !== null,
            'approved_unapplied' => $status === 'APPROVED' && $approvedDraft !== null,
            'applied' => $applied || $status === 'APPLIED', 'state_issue' => $stateIssue,
        ];
    }
}
