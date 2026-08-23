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

    private const HISTORICAL_EXCLUSIONS = [1237, 1241, 1242, 1261];

    public function evaluate(Product $product, string|array $scope = self::LONG_DESCRIPTION): array
    {
        $currentJobItemId = $this->currentJobItemId($scope);
        if (is_array($scope)) {
            $scope = array_values(array_diff_key($scope, ['_current_job_item_id' => true]));
        }
        $scopes = is_array($scope) ? array_values(array_unique($scope)) : [$scope];
        $reasons = [];
        $identity = [
            'name' => filled($product->name),
            'model_code' => filled($product->model_code),
            'brand_or_context' => filled($product->brand_id) || filled($product->product_type) || filled($product->category?->name),
        ];

        if (in_array((int) $product->id, self::HISTORICAL_EXCLUSIONS, true)) {
            $reasons[] = 'HISTORICAL_ROLLOUT_DISPOSITION_PRESERVED';
        }
        if ((! $identity['name'] && ! $identity['model_code']) || ! $identity['brand_or_context']) {
            $reasons[] = 'MINIMUM_PRODUCT_IDENTITY_INSUFFICIENT';
        }
        if ($this->hasActiveConflict($product, $currentJobItemId)) {
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
            'historical_exclusion' => in_array((int) $product->id, self::HISTORICAL_EXCLUSIONS, true),
        ];
    }

    private function currentJobItemId(string|array $scope): ?int
    {
        return is_array($scope) && ! empty($scope['_current_job_item_id'])
            ? (int) $scope['_current_job_item_id']
            : null;
    }

    private function hasActiveConflict(Product $product, ?int $currentJobItemId = null): bool
    {
        $draftStatuses = ['draft', 'needs_review', 'approved', 'approved_for_apply', 'applying', 'applied'];
        if (Schema::hasTable('ai_product_drafts') && AiProductDraft::query()->where('product_id', $product->id)->whereIn('status', $draftStatuses)->exists()) {
            return true;
        }

        if (Schema::hasTable('ai_bulk_apply_items') && DB::table('ai_bulk_apply_items')->where('product_id', $product->id)->whereIn('status', ['pending', 'authorized', 'applying', 'applied'])->exists()) {
            return true;
        }

        if (! Schema::hasTable('ai_product_job_items')) {
            return false;
        }

        return DB::table('ai_product_job_items')
            ->where('product_id', $product->id)
            ->when($currentJobItemId, fn ($query) => $query->where('id', '!=', $currentJobItemId))
            ->whereIn('status', ['queued', 'processing', 'needs_review'])
            ->exists();
    }
}
