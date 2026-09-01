<?php

namespace App\Services\Product;

use App\Models\AiProductDraft;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Content/SEO eligibility is intentionally narrower than Product Data authority.
 * Missing manufacturer facts affect the claim context, not basic copy eligibility.
 */
final class ProductContentEligibilityPolicy
{
    public const SEO_META = 'SEO_META';
    public const LONG_DESCRIPTION = 'LONG_DESCRIPTION';
    public const FAQ = 'FAQ';
    public const ARTICLE = 'ARTICLE';

    public function evaluate(Product $product, string|array $scope = self::LONG_DESCRIPTION): array
    {
        $currentJobItemId = $this->currentJobItemId($scope);
        $supersededDraftId = $this->supersededDraftId($scope);
        $knownActiveConflict = $this->knownActiveConflict($scope);
        if (is_array($scope)) {
            $scope = array_values(array_diff_key($scope, [
                '_current_job_item_id' => true,
                '_superseded_draft_id' => true,
                '_active_conflict' => true,
            ]));
        }
        $scopes = is_array($scope) ? array_values(array_unique($scope)) : [$scope];
        $reasons = [];
        $identity = [
            'name' => filled($product->name),
            'model_code' => filled($product->model_code),
            'brand_or_context' => filled($product->brand_id) || filled($product->product_type) || filled($product->category?->name),
        ];

        if ((! $identity['name'] && ! $identity['model_code']) || ! $identity['brand_or_context']) {
            $reasons[] = 'MINIMUM_PRODUCT_IDENTITY_INSUFFICIENT';
        }
        if (($knownActiveConflict ?? $this->hasActiveConflict($product, $currentJobItemId, $supersededDraftId))) {
            $reasons[] = 'ACTIVE_DRAFT_OR_APPLY_CONFLICT';
        }

        $required = [];
        foreach ($scopes as $requested) {
            if (! in_array($requested, [self::SEO_META, self::LONG_DESCRIPTION, self::FAQ, self::ARTICLE], true)) {
                $reasons[] = 'UNSUPPORTED_CONTENT_SCOPE:'.$requested;
                continue;
            }
            $required[$requested] = ['identity', 'brand_or_context'];
        }

        return [
            'eligible' => $reasons === [],
            'scope' => $scopes,
            'reasons' => array_values(array_unique($reasons)),
            'required_facts' => $required,
            'identity' => $identity,
            'technical_authority_required' => false,
            'manufacturer_source_required' => false,
            'historical_exclusion' => false,
        ];
    }

    private function currentJobItemId(string|array $scope): ?int
    {
        return is_array($scope) && ! empty($scope['_current_job_item_id'])
            ? (int) $scope['_current_job_item_id']
            : null;
    }

    private function supersededDraftId(string|array $scope): ?int
    {
        return is_array($scope) && ! empty($scope['_superseded_draft_id'])
            ? (int) $scope['_superseded_draft_id']
            : null;
    }

    private function knownActiveConflict(string|array $scope): ?bool
    {
        return is_array($scope) && array_key_exists('_active_conflict', $scope)
            ? (bool) $scope['_active_conflict']
            : null;
    }

    /**
     * Resolve active conflicts for a whole bulk selection with bounded queries.
     * The excluded IDs are the current evidence being intentionally superseded.
     *
     * @return array<int,true>
     */
    public function activeConflictProductIds(array $productIds, array $excludedDraftIds = [], array $excludedItemIds = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        if ($ids === []) return [];

        $conflicts = [];
        if (Schema::hasTable('ai_product_drafts')) {
            foreach (AiProductDraft::query()
                ->whereIn('product_id', $ids)
                ->when($excludedDraftIds !== [], fn ($query) => $query->whereNotIn('id', array_map('intval', $excludedDraftIds)))
                ->whereNull('applied_at')
                ->where(function ($query): void {
                    $query->where(function ($review): void {
                        $review->where('approval_status', 'REVIEW_REQUIRED')
                            ->whereIn('status', ['needs_review', 'REVIEW_REQUIRED']);
                    })->orWhere('approval_status', 'APPROVED_FOR_APPLY')
                        ->orWhere('status', 'applying');
                })
                ->pluck('product_id') as $id) $conflicts[(int) $id] = true;
        }
        if (Schema::hasTable('ai_bulk_apply_items')) {
            foreach (DB::table('ai_bulk_apply_items')->whereIn('product_id', $ids)->whereIn('status', ['pending', 'authorized', 'applying'])->pluck('product_id') as $id) {
                $conflicts[(int) $id] = true;
            }
        }
        if (Schema::hasTable('ai_product_job_items')) {
            foreach (DB::table('ai_product_job_items')
                ->whereIn('product_id', $ids)
                ->when($excludedItemIds !== [], fn ($query) => $query->whereNotIn('id', array_map('intval', $excludedItemIds)))
                ->whereIn('canonical_status', ['QUEUED', 'RUNNING', 'VALIDATING', 'FACT_CHECKING'])
                ->whereNotIn('status', ['needs_review', 'completed', 'completed_verified', 'failed', 'blocked', 'cancelled', 'completed_with_errors'])
                ->pluck('product_id') as $id) $conflicts[(int) $id] = true;
        }

        return $conflicts;
    }

    private function hasActiveConflict(Product $product, ?int $currentJobItemId = null, ?int $supersededDraftId = null): bool
    {
        return isset($this->activeConflictProductIds(
            [(int) $product->id],
            $supersededDraftId ? [$supersededDraftId] : [],
            $currentJobItemId ? [$currentJobItemId] : [],
        )[(int) $product->id]);
    }
}
