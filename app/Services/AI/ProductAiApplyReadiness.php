<?php

namespace App\Services\AI;

use App\Models\AiProductDraft;
use App\Services\Product\AIProductContentSystem;
use App\Services\Product\AIProductDraftApplyService;

final class ProductAiApplyReadiness
{
    private const FIELD_LABELS = [
        'excerpt' => 'Mô tả ngắn',
        'content_html' => 'Nội dung dài',
        'seo_title' => 'SEO title',
        'meta_description' => 'Meta description',
        'og_title' => 'OG title',
        'og_description' => 'OG description',
        'merchant_title' => 'Merchant title',
        'merchant_description' => 'Merchant description',
        'faq' => 'FAQ',
        'tags' => 'Tags',
    ];

    public function __construct(
        private readonly AiProductWarningClassifier $warnings,
        private readonly AIProductContentSystem $contentSystem,
        private readonly SingleOperatorControlledRolloutPolicy $rollout,
    ) {}

    /** @return array<string,mixed> */
    public function resolve(?AiProductDraft $draft): array
    {
        if (! $draft) {
            return $this->empty('DRAFT_MISSING');
        }

        $payload = (array) ($draft->normalized_output_json ?? []);
        $product = $draft->relationLoaded('product')
            ? $draft->product
            : $draft->product()->with(['brand', 'tags', 'faqs'])->first();
        $classification = $this->warnings->classify((array) ($draft->warnings_json ?? []), $payload, $product);
        $approved = $draft->approval_status === 'APPROVED_FOR_APPLY';
        $applied = $draft->applied_at !== null || $draft->approval_status === 'APPLIED';
        $fields = array_values(array_intersect(
            (array) ($draft->approved_fields_json ?? []),
            array_keys(self::FIELD_LABELS),
        ));
        $hard = $classification['hard_blockers'];
        $stale = [];

        if ($approved && $draft->approved_payload_hash && ! hash_equals(
            (string) $draft->approved_payload_hash,
            app(AIProductDraftApplyService::class)->payloadHash($payload),
        )) {
            $stale[] = 'APPROVED_PAYLOAD_HASH_MISMATCH';
        }
        if ($approved && $product && $draft->approved_content_hash && ! hash_equals(
            (string) $draft->approved_content_hash,
            app(AIProductDraftApplyService::class)->contentHash($product),
        )) {
            $stale[] = 'STALE_PRODUCT_CONTENT';
        }
        if ($approved && $product && $draft->approved_technical_context_hash && ! hash_equals(
            (string) $draft->approved_technical_context_hash,
            $this->contentSystem->technicalContextHash($product),
        )) {
            $stale[] = 'STALE_TECHNICAL_CONTEXT';
        }

        $confirmation = $product
            ? $this->rollout->expectedApplyConfirmation(($product->model_code ?: 'UNKNOWN').'#'.$product->id)
            : null;
        $canApply = $approved && ! $applied && $product !== null && $fields !== [] && $hard === [] && $stale === [];

        return [
            'can_apply' => $canApply,
            'requires_confirmation' => $approved && ! $applied,
            'requires_warning_override' => $classification['soft_warnings'] !== [] && ! (bool) $draft->warning_override,
            'hard_blockers' => $hard,
            'soft_warnings' => $classification['soft_warnings'],
            'optional_data' => $classification['optional_data'],
            'technical_processed' => $classification['technical_processed'],
            'informational' => $classification['informational'],
            'warning_counts' => $classification['counts'],
            'fields_to_apply' => $fields,
            'field_labels' => array_map(fn (string $field): string => self::FIELD_LABELS[$field], $fields),
            'protected_fields' => ['Tên', 'Slug', 'SKU/model', 'Thương hiệu', 'Danh mục', 'Giá', 'Tồn kho', 'Thông số kỹ thuật'],
            'stale_target' => $stale !== [],
            'stale_reasons' => array_values(array_unique($stale)),
            'approved_state' => $approved,
            'applied_state' => $applied,
            'confirmation' => $confirmation,
            'draft_id' => $draft->id,
            'product_id' => $draft->product_id,
        ];
    }

    private function empty(string $reason): array
    {
        return [
            'can_apply' => false,
            'requires_confirmation' => false,
            'requires_warning_override' => false,
            'hard_blockers' => [],
            'soft_warnings' => [],
            'optional_data' => [],
            'technical_processed' => [],
            'informational' => [],
            'warning_counts' => ['soft' => 0, 'optional' => 0, 'technical_processed' => 0, 'hard' => 0, 'informational' => 0],
            'fields_to_apply' => [],
            'field_labels' => [],
            'protected_fields' => [],
            'stale_target' => false,
            'stale_reasons' => [$reason],
            'approved_state' => false,
            'applied_state' => false,
            'confirmation' => null,
            'draft_id' => null,
            'product_id' => null,
        ];
    }
}
