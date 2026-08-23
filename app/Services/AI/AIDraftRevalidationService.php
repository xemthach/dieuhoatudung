<?php

namespace App\Services\AI;

use App\Models\AiProductDraft;
use App\Models\AiProductJobItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AIDraftRevalidationService
{
    public function revalidate(AiProductDraft $draft, Product $product, string $reason): array
    {
        $governance = app(AIContentGovernance::class);
        $payload = is_array($draft->normalized_output_json) && $draft->normalized_output_json !== []
            ? $draft->normalized_output_json
            : ($draft->raw_output_json ?? []);
        $context = $governance->buildProductContext($product, ['action' => 'revalidate_existing_draft', 'outputs' => ['content' => true]]);
        $audit = $governance->validatePayload($payload, $context, ['content_html', 'excerpt', 'seo_title', 'meta_description', 'og_title', 'og_description', 'merchant_title', 'merchant_description']);
        if (($audit['blocked_claims'] ?? []) !== []) {
            return ['status' => 'BLOCKED', 'audit' => $audit, 'provider_calls' => 0];
        }

        return DB::transaction(function () use ($draft, $audit, $reason): array {
            $draft->update([
                'status' => 'needs_review',
                'approval_status' => 'REVIEW_REQUIRED',
                'validation_errors_json' => $audit['blocked_claims'] ?? [],
                'warnings_json' => $audit['warnings'] ?? [],
                'field_status_json' => array_merge((array) ($draft->field_status_json ?? []), ['content_html' => 'REVIEW_REQUIRED']),
            ]);
            $item = AiProductJobItem::where('draft_id', $draft->id)->first();
            if ($item) {
                AIJobStateMachine::transition($item, AIJobStateMachine::REVIEW_REQUIRED, $reason);
                $item->update(['status' => 'needs_review', 'last_error_code' => null, 'last_error_message' => null, 'error_message' => null, 'finished_at' => now()]);
            }
            return ['status' => 'REVIEW_REQUIRED', 'audit' => $audit, 'provider_calls' => 0, 'draft_id' => $draft->id, 'item_id' => $item?->id];
        });
    }
}
