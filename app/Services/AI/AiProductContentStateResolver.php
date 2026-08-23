<?php

namespace App\Services\AI;

use App\Models\AiProductDraft;
use App\Models\AiProductJobItem;
use App\Models\Product;

final class AiProductContentStateResolver
{
    /** @return array{status:string,item:?AiProductJobItem,draft:?AiProductDraft,reviewable:bool,approved_unapplied:bool,applied:bool,state_issue:?string} */
    public function resolve(Product $product, ?AiProductJobItem $item = null): array
    {
        $item ??= $this->latestItem($product);
        $draft = $item?->draft;

        if (! $item) {
            return $this->result(
                'NOT_GENERATED', null, null, false, false, false,
                filled($product->ai_status) && $product->ai_status !== 'not_generated'
                    ? 'STALE_DENORMALIZED_STATUS'
                    : null,
            );
        }

        if ($draft?->applied_at) {
            return $this->result('APPLIED', $item, $draft, false, false, true, null);
        }

        if ($draft?->approval_status === 'APPROVED_FOR_APPLY') {
            return $this->result('APPROVED', $item, $draft, false, true, false, null);
        }

        if ($draft?->approval_status === 'REJECTED') {
            return $this->result('REJECTED', $item, $draft, false, false, false, null);
        }

        $raw = (string) ($item->canonical_status ?: $item->status ?: 'NOT_GENERATED');
        $normalized = app(AiContentStatusPresenter::class)->normalize($raw);
        $reviewable = $draft !== null
            && in_array((string) $draft->status, ['needs_review', 'REVIEW_REQUIRED'], true)
            && $normalized === 'REVIEW_REQUIRED';

        if ($normalized === 'REVIEW_REQUIRED' && ! $reviewable) {
            return $this->result('BLOCKED', $item, $draft, false, false, false, 'REVIEWABLE_DRAFT_MISSING');
        }

        return $this->result($normalized, $item, $draft, $reviewable, false, false, null);
    }

    public function reviewableDraft(Product $product): ?AiProductDraft
    {
        $state = $this->resolve($product);

        return $state['reviewable'] ? $state['draft'] : null;
    }

    public function approvedUnappliedDraft(Product $product): ?AiProductDraft
    {
        $state = $this->resolve($product);

        return $state['approved_unapplied'] ? $state['draft'] : null;
    }

    private function latestItem(Product $product): ?AiProductJobItem
    {
        if ($product->relationLoaded('latestAiProductJobItem')) {
            return $product->latestAiProductJobItem;
        }

        return $product->aiProductJobItems()->with('draft')->latest('id')->first();
    }

    private function result(
        string $status,
        ?AiProductJobItem $item,
        ?AiProductDraft $draft,
        bool $reviewable,
        bool $approvedUnapplied,
        bool $applied,
        ?string $stateIssue,
    ): array {
        return [
            'status' => $status,
            'item' => $item,
            'draft' => $draft,
            'reviewable' => $reviewable,
            'approved_unapplied' => $approvedUnapplied,
            'applied' => $applied,
            'state_issue' => $stateIssue,
        ];
    }
}
