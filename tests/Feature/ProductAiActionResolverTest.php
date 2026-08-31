<?php

namespace Tests\Feature;

use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\AI\ProductAiActionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAiActionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_draft_exposes_generate_only(): void
    {
        $policy = $this->resolve(Product::factory()->create());

        $this->assertSame('NOT_GENERATED', $policy['current_state']);
        $this->assertSame(['generate'], $policy['direct_actions']);
        $this->assertSame([], $policy['menu_actions']);
    }

    public function test_processing_exposes_status_and_operational_menu_only(): void
    {
        [$product] = $this->state('PROCESSING', 'processing');
        $policy = $this->resolve($product);

        $this->assertSame(['processing_status'], $policy['direct_actions']);
        $this->assertSame(['view_job', 'recover'], $policy['menu_actions']);
        $this->assertFalse($policy['can_generate_primary']);
    }

    public function test_review_with_warning_exposes_preview_approve_and_secondary_menu(): void
    {
        [$product] = $this->state('REVIEW_REQUIRED', 'needs_review', 'REVIEW_REQUIRED', ['content_too_short:459/800']);
        $policy = $this->resolve($product);

        $this->assertSame(['preview', 'approve'], $policy['direct_actions']);
        $this->assertSame(['regenerate', 'reject', 'discard', 'view_job'], $policy['menu_actions']);
        $this->assertTrue($policy['approve_has_warning']);
        $this->assertFalse($policy['can_generate_primary']);
        $this->assertFalse($policy['can_apply']);
    }

    public function test_approved_exposes_preview_and_apply_only(): void
    {
        [$product] = $this->state('REVIEW_REQUIRED', 'needs_review', 'APPROVED_FOR_APPLY');
        $policy = $this->resolve($product);

        $this->assertSame('APPROVED', $policy['current_state']);
        $this->assertSame(['preview', 'apply'], $policy['direct_actions']);
        $this->assertSame(['view_job'], $policy['menu_actions']);
    }

    public function test_applied_exposes_content_and_new_generation_in_more(): void
    {
        [$product, $draft] = $this->state('REVIEW_REQUIRED', 'needs_review', 'APPROVED_FOR_APPLY');
        $draft->update(['approval_status' => 'APPLIED', 'applied_at' => now()]);

        $policy = $this->resolve($product);
        $this->assertSame('APPLIED', $policy['current_state']);
        $this->assertSame(['preview'], $policy['direct_actions']);
        $this->assertSame(['generate_new', 'view_job'], $policy['menu_actions']);
    }

    public function test_approved_draft_with_unverified_claim_still_present_becomes_hard_blocked(): void
    {
        [$product] = $this->state(
            'REVIEW_REQUIRED',
            'needs_review',
            'APPROVED_FOR_APPLY',
            ['unverified_technical_claim:Draft'],
        );
        $policy = $this->resolve($product);

        $this->assertSame('HARD_BLOCKED', $policy['current_state']);
        $this->assertSame(['block_reason'], $policy['direct_actions']);
        $this->assertFalse($policy['can_apply']);
        $this->assertSame(1, $policy['apply_readiness']['warning_counts']['hard']);
    }

    public function test_rejected_and_discarded_expose_new_generation_and_history(): void
    {
        foreach (['REJECTED', 'DISCARDED'] as $approval) {
            [$product] = $this->state('REVIEW_REQUIRED', 'needs_review', $approval);
            $policy = $this->resolve($product);

            $this->assertSame($approval, $policy['current_state']);
            $this->assertSame(['generate'], $policy['direct_actions']);
            $this->assertSame(['view_job'], $policy['menu_actions']);
        }
    }

    public function test_hard_block_exposes_reason_and_job_history_without_review_actions(): void
    {
        [$product] = $this->state('BLOCKED', 'blocked');
        $policy = $this->resolve($product);

        $this->assertSame(['block_reason'], $policy['direct_actions']);
        $this->assertSame(['view_job'], $policy['menu_actions']);
        $this->assertFalse($policy['can_approve']);
        $this->assertFalse($policy['can_apply']);
        $this->assertFalse($policy['can_regenerate']);
    }

    private function resolve(Product $product): array
    {
        $product->unsetRelation('latestAiProductJobItem');

        return app(ProductAiActionResolver::class)->resolve($product);
    }

    /** @return array{Product,AiProductDraft,AiProductJobItem} */
    private function state(
        string $canonical,
        string $itemStatus,
        string $approval = 'REVIEW_REQUIRED',
        array $warnings = [],
    ): array {
        $product = Product::factory()->create();
        $job = AiProductJob::create([
            'type' => 'single_product_preview',
            'scope' => 'selected',
            'status' => $itemStatus,
            'total' => 1,
            'config_json' => [],
        ]);
        $payload = ['content_html' => '<h2>Draft</h2>'];
        $draft = AiProductDraft::create([
            'job_id' => $job->id,
            'product_id' => $product->id,
            'status' => $itemStatus === 'needs_review' ? 'needs_review' : $itemStatus,
            'approval_status' => $approval,
            'normalized_output_json' => $payload,
            'warnings_json' => $warnings,
        ]);
        $item = AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => $itemStatus,
            'canonical_status' => $canonical,
            'draft_id' => $draft->id,
            'generated_payload_json' => $payload,
        ]);
        if ($approval === 'APPROVED_FOR_APPLY') {
            $service = app(\App\Services\Product\AIProductDraftApplyService::class);
            $product->load('brand');
            $draft->update([
                'approved_fields_json' => ['content_html'],
                'approved_payload_hash' => $service->payloadHash($payload),
                'approved_content_hash' => $service->contentHash($product),
                'approved_technical_context_hash' => app(\App\Services\Product\AIProductContentSystem::class)->technicalContextHash($product),
                'approved_identity_json' => [
                    'product_id' => $product->id,
                    'model_code' => $product->model_code,
                    'sku' => $product->sku,
                    'brand_id' => $product->brand_id,
                    'brand' => $product->brand?->name,
                ],
            ]);
        }

        return [$product, $draft, $item];
    }
}
