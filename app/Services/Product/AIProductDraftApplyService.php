<?php

namespace App\Services\Product;

use App\Models\AiProductContentVersion;
use App\Models\AiProductDraft;
use App\Models\AiProductDraftApplyAudit;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use App\Models\User;
use App\Services\AI\SingleOperatorControlledRolloutPolicy;

/**
 * Explicit human-approved, content-layer-only draft application boundary.
 * This service is intentionally separate from generation and from the legacy
 * applyLatestDraft path so approval cannot be inferred from job status.
 */
class AIProductDraftApplyService
{
    private const FIELD_MAP = [
        'excerpt' => 'short_description',
        'content_html' => 'long_description',
        'seo_title' => 'seo_title',
        'meta_description' => 'seo_description',
        'og_title' => 'og_title',
        'og_description' => 'og_description',
        'merchant_title' => 'merchant_title',
        'merchant_description' => 'merchant_description',
    ];

    private const PATCH_FIELDS = ['tags', 'faq'];

    private const FORBIDDEN = [
        'model', 'model_code', 'sku', 'brand', 'brand_id', 'category', 'category_id',
        'price', 'regular_price', 'sale_price', 'btu', 'marketing_capacity_btu',
        'technical_capacity_btu', 'technical_capacity_status', 'specs_json',
        'technical_specs', 'technical_specs_json', 'catalog_provenance',
        'catalog_source_id', 'catalog_model_id', 'capacity_kw', 'power_input_kw',
        'ai_status', 'ai_last_run_at', 'updated_at',
    ];

    public function __construct(private readonly AIProductContentSystem $contentSystem) {}

    public function payloadHash(array $payload): string
    {
        return hash('sha256', (string) json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ));
    }

    public function approve(AiProductDraft $draft, int $approvedBy, User $actor, string $reviewNote = '', ?array $approvedFields = null): AiProductDraft
    {
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireApprove($actor);
        if (app(SingleOperatorControlledRolloutPolicy::class)->active()) {
            app(SingleOperatorControlledRolloutPolicy::class)->assertAction($actor, 'APPROVE');
        }
        if (! in_array((string) $draft->status, ['needs_review', 'REVIEW_REQUIRED'], true)) {
            throw new RuntimeException('DRAFT_NOT_REVIEWABLE');
        }

        $payload = $this->payload($draft);
        $this->assertSafePayload($payload);
        $this->assertFactCheck($payload);

        $product = $draft->product()->with('brand')->firstOrFail();
        $fields = $approvedFields === null
            ? $this->approvedFieldsForDraft($draft, $payload)
            : array_values(array_intersect($this->approvedFields($payload), array_values(array_unique($approvedFields))));
        if ($fields === []) throw new RuntimeException('NO_APPROVED_FIELDS');
        $identity = $this->identity($product);

        $draft->forceFill([
            'approval_status' => 'APPROVED_FOR_APPLY',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'review_note' => $reviewNote,
            'approved_payload_hash' => $this->payloadHash($payload),
            'approved_technical_context_hash' => $this->contentSystem->technicalContextHash($product),
            'approved_identity_json' => $identity,
            'approved_fields_json' => $fields,
        ])->save();

        return $draft->refresh();
    }

    public function reject(AiProductDraft $draft, int $reviewer, string $note = ''): AiProductDraft
    {
        if ($draft->approval_status === 'APPROVED_FOR_APPLY' || $draft->applied_at) {
            throw new RuntimeException('APPROVAL_ALREADY_COMMITTED');
        }

        $draft->forceFill([
            'approval_status' => 'REJECTED',
            'approved_by' => null,
            'approved_at' => null,
            'review_note' => $note,
        ])->save();

        return $draft->refresh();
    }

    /**
     * Read-only gate used before human approval. It never changes draft or Product state.
     */
    public function eligibility(AiProductDraft $draft): array
    {
        $payload = $this->payload($draft);
        $reasons = [];
        try {
            $this->assertSafePayload($payload);
            $this->assertFactCheck($payload);
        } catch (RuntimeException $e) {
            $reasons[] = $e->getMessage();
        }
        $product = $draft->product()->with('brand')->first();
        if (! $product) $reasons[] = 'PRODUCT_NOT_FOUND';
        if ($product && ! hash_equals($this->contentSystem->technicalContextHash($product), (string) data_get($draft->token_usage_json, 'technical_context_hash'))) {
            $reasons[] = 'STALE_TECHNICAL_CONTEXT';
        }
        $hash = $this->payloadHash($payload);
        return [
            'eligible_for_approval' => $reasons === [] && $draft->status === 'needs_review',
            'approved' => false,
            'payload_hash_valid' => true,
            'payload_hash' => $hash,
            'technical_context_fresh' => ! in_array('STALE_TECHNICAL_CONTEXT', $reasons, true),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /** @return array{result:string,fields_applied:array,before_hash:string,after_hash:?string,version_id:?int} */
    public function apply(AiProductDraft $draft, int $actorId, bool $injectFailure = false, ?string $confirmation = null): array
    {
        $actor = User::findOrFail($actorId);
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireApply($actor);
        if (app(SingleOperatorControlledRolloutPolicy::class)->active()) {
            app(SingleOperatorControlledRolloutPolicy::class)->assertAction($actor, 'APPLY');
        }
        if ($draft->applied_at) {
            return ['result' => 'NOOP_ALREADY_APPLIED', 'fields_applied' => $draft->approved_fields_json ?? [], 'before_hash' => '', 'after_hash' => null, 'version_id' => null];
        }
        if ($draft->approval_status !== 'APPROVED_FOR_APPLY') {
            throw new RuntimeException('DRAFT_NOT_APPROVED');
        }
        if (app(SingleOperatorControlledRolloutPolicy::class)->active()) {
            app(SingleOperatorControlledRolloutPolicy::class)->assertExplicitApplyConfirmation($confirmation, $this->productLabel($draft));
        }

        $payload = $this->payload($draft);
        if (! hash_equals((string) $draft->approved_payload_hash, $this->payloadHash($payload))) {
            throw new RuntimeException('APPROVED_PAYLOAD_HASH_MISMATCH');
        }

        $product = $draft->product()->with(['brand', 'tags', 'faqs'])->firstOrFail();
        $currentContext = $this->contentSystem->technicalContextHash($product);
        if (! hash_equals((string) $draft->approved_technical_context_hash, $currentContext)) {
            throw new RuntimeException('STALE_TECHNICAL_CONTEXT');
        }
        if (! $this->sameIdentity($this->identity($product), (array) ($draft->approved_identity_json ?? []))) {
            throw new RuntimeException('PRODUCT_IDENTITY_MISMATCH');
        }
        $this->assertSafePayload($payload);
        $this->assertFactCheck($payload);

        $fields = array_values(array_intersect($draft->approved_fields_json ?? [], array_merge(array_keys(self::FIELD_MAP), self::PATCH_FIELDS)));
        $beforeHash = $this->contentHash($product);
        $versionId = null;
        $afterHash = null;

        DB::transaction(function () use ($draft, $actorId, $product, $payload, $fields, $beforeHash, $currentContext, $injectFailure, &$versionId, &$afterHash): void {
            $version = AiProductContentVersion::create([
                'product_id' => $product->id,
                'old_excerpt' => $product->short_description,
                'old_content' => $product->long_description,
                'old_seo_json' => [
                    'seo_title' => $product->seo_title,
                    'seo_description' => $product->seo_description,
                    'og_title' => $product->og_title,
                    'og_description' => $product->og_description,
                ],
                'old_merchant_json' => [
                    'merchant_title' => $product->merchant_title,
                    'merchant_description' => $product->merchant_description,
                    'google_product_category' => $product->google_product_category,
                    'product_type' => $product->product_type,
                ],
                'old_tags_json' => $product->tags->map->only(['id', 'name', 'slug'])->values()->all(),
                'old_faq_json' => $product->faqs->map->only(['question', 'answer'])->values()->all(),
                'created_by' => $actorId,
            ]);
            $versionId = $version->id;

            $updates = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, self::FIELD_MAP) && array_key_exists($field, $payload) && $payload[$field] !== null) {
                    $updates[self::FIELD_MAP[$field]] = $payload[$field];
                }
            }
            if ($updates !== []) {
                // Content approval must not rewrite Product freshness metadata.
                // The content version/audit rows carry the apply history; Product
                // technical and identity state, including updated_at, stays stable.
                $timestamps = $product->timestamps;
                $product->timestamps = false;
                try {
                    $product->update($updates);
                } finally {
                    $product->timestamps = $timestamps;
                }
            }
            if (in_array('tags', $fields, true) && array_key_exists('tags', $payload)) {
                $this->syncTags($product, (array) $payload['tags']);
            }
            if (in_array('faq', $fields, true) && array_key_exists('faq', $payload)) {
                $this->syncFaq($product, (array) ($payload['faq'] ?? []));
            }

            if ($injectFailure) {
                throw new RuntimeException('CONTROLLED_APPLY_FAILURE');
            }

            $afterHash = $this->contentHash($product->refresh());
            $draft->forceFill([
                'approval_status' => 'APPLIED',
                'applied_by' => $actorId,
                'applied_at' => now(),
            ])->save();

            if (DB::getSchemaBuilder()->hasTable('ai_product_draft_apply_audits')) {
                AiProductDraftApplyAudit::create([
                    'draft_id' => $draft->id,
                    'product_id' => $product->id,
                    'approved_by' => $draft->approved_by,
                    'approved_at' => $draft->approved_at,
                    'payload_hash' => $draft->approved_payload_hash,
                    'technical_context_hash' => $currentContext,
                    'fields_applied' => $fields,
                    'before_hash' => $beforeHash,
                    'after_hash' => $afterHash,
                    'result' => 'APPLIED',
                ]);
            }
        });

        return ['result' => 'APPLIED', 'fields_applied' => $fields, 'before_hash' => $beforeHash, 'after_hash' => $afterHash, 'version_id' => $versionId];
    }

    /**
     * Applies a pre-resolved, pre-approved draft list inside one outer transaction.
     * The caller owns the immutable allowlist; this method never discovers scope.
     */
    public function applyBatch(array $drafts, int $actorId, bool $injectFailure = false): array
    {
        return DB::transaction(function () use ($drafts, $actorId, $injectFailure): array {
            $results = [];
            foreach (array_values($drafts) as $index => $draft) {
                if (! $draft instanceof AiProductDraft) throw new RuntimeException('INVALID_BATCH_DRAFT');
                $results[] = $this->apply($draft->refresh(), $actorId, false);
                if ($injectFailure && $index === 1) throw new RuntimeException('CONTROLLED_BATCH_FAILURE');
            }
            return $results;
        });
    }

    public function contentHash(Product $product): string
    {
        $product->loadMissing(['tags', 'faqs']);
        return $this->payloadHash([
            'excerpt' => $product->short_description,
            'content' => $product->long_description,
            'seo' => [$product->seo_title, $product->seo_description, $product->og_title, $product->og_description],
            'merchant' => [$product->merchant_title, $product->merchant_description],
            'tags' => $product->tags->map->only(['id', 'name', 'slug'])->values()->all(),
            'faq' => $product->faqs->map->only(['question', 'answer'])->values()->all(),
        ]);
    }

    public function rollback(AiProductDraft $draft, User $actor, string $reason = '', ?string $confirmation = null): bool
    {
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireRollback($actor);
        if (app(SingleOperatorControlledRolloutPolicy::class)->active()) {
            app(SingleOperatorControlledRolloutPolicy::class)->assertAction($actor, 'ROLLBACK');
            app(SingleOperatorControlledRolloutPolicy::class)->assertExplicitRollbackConfirmation($reason, $confirmation, $this->productLabel($draft));
        }
        $versionId = AiProductContentVersion::where('product_id', $draft->product_id)->latest('id')->value('id');
        if (! $versionId) return false;
        app(AIProductContentSystem::class)->rollback($draft->product, $actor, $versionId);
        return true;
    }

    private function payload(AiProductDraft $draft): array
    {
        return is_array($draft->normalized_output_json) ? $draft->normalized_output_json : [];
    }

    private function approvedFields(array $payload): array
    {
        return array_values(array_filter(array_merge(array_keys(self::FIELD_MAP), self::PATCH_FIELDS), fn (string $field): bool => array_key_exists($field, $payload) && $payload[$field] !== null));
    }

    private function productLabel(AiProductDraft $draft): string
    {
        $product = $draft->product_id ? Product::query()->select(['id', 'model_code'])->find((int) $draft->product_id) : null;
        return ($product?->model_code ?: 'UNKNOWN').'#'.(int) ($draft->product_id ?: 0);
    }

    private function approvedFieldsForDraft(AiProductDraft $draft, array $payload): array
    {
        $scope = $draft->job?->config_json['retry_scope'] ?? data_get($draft->token_usage_json, 'patched_fields');
        if (is_array($scope) && $scope !== []) {
            return array_values(array_intersect($this->approvedFields($payload), $scope));
        }
        return $this->approvedFields($payload);
    }

    private function identity(Product $product): array
    {
        return ['product_id' => $product->id, 'model_code' => $product->model_code, 'sku' => $product->sku, 'brand_id' => $product->brand_id, 'brand' => $product->brand?->name];
    }

    private function sameIdentity(array $left, array $right): bool
    {
        ksort($left); ksort($right);
        return $left === $right;
    }

    private function assertSafePayload(array $payload): void
    {
        $found = [];
        $walk = function (mixed $value, ?string $parent = null) use (&$walk, &$found): void {
            if (! is_array($value)) return;
            foreach ($value as $key => $child) {
                $key = is_string($key) ? Str::snake($key) : '';
                if (in_array($parent, ['governance_context', 'used_facts', 'fact_check', 'warnings', 'blocked_claims'], true)) continue;
                if (in_array($key, self::FORBIDDEN, true)) $found[] = $key;
                $walk($child, $key);
            }
        };
        $walk($payload);
        if ($found !== []) throw new RuntimeException('FORBIDDEN_PRODUCT_FIELD: '.implode(', ', array_unique($found)));
    }

    private function assertFactCheck(array $payload): void
    {
        $blocked = $payload['blocked_claims'] ?? data_get($payload, 'fact_check.blocked_claims', []);
        if (! empty($blocked)) throw new RuntimeException('FACT_CHECK_BLOCKED');
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        foreach (['CONTRADICTED', 'UNVERIFIED_PRODUCT_TECHNICAL_CLAIM', 'AMBIGUOUS_CAPACITY_CLAIM'] as $needle) {
            if (is_string($json) && str_contains($json, $needle)) throw new RuntimeException('FACT_CHECK_BLOCKED: '.$needle);
        }
    }

    private function syncTags(Product $product, array $tags): void
    {
        $ids = [];
        foreach ($tags as $tag) {
            $name = is_array($tag) ? ($tag['name'] ?? '') : (string) $tag;
            if ($name !== '') $ids[] = \App\Models\Tag::firstOrCreate(['name' => trim($name)], ['slug' => Str::slug($name)])->id;
        }
        if ($ids !== []) $product->tags()->sync($ids);
    }

    private function syncFaq(Product $product, array $faq): void
    {
        $product->faqs()->detach();
        foreach ($faq as $i => $item) {
            if (! is_array($item) || empty($item['question']) || empty($item['answer'])) continue;
            $faqModel = \App\Models\Faq::create(['question' => $item['question'], 'answer' => $item['answer'], 'group' => 'product', 'sort_order' => $i + 1, 'is_active' => true]);
            $product->faqs()->attach($faqModel->id, ['sort_order' => $i + 1]);
        }
    }
}
