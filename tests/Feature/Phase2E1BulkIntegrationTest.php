<?php

namespace Tests\Feature;

use App\Jobs\AiProductContentBatchJob;
use App\Models\AiProductJob;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\ProductBulkGenerationManifest;
use App\Services\AI\ProductBulkTargetResolver;
use App\Services\Product\AIBulkApplyManifestService;
use App\Services\Product\AIProductDraftApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2E1BulkIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_manifest_is_persisted_and_batch_job_reloads_it(): void
    {
        $products = Product::factory()->count(2)->create();
        $job = AiProductJob::create([
            'type' => 'generate_ai_content', 'scope' => 'SELECTED', 'status' => 'queued',
            'total' => 2, 'config_json' => ['outputs' => ['content_html']],
        ]);

        $manifest = app(ProductBulkGenerationManifest::class)->freeze(
            $job, ProductBulkTargetResolver::SELECTED, $products->pluck('id')->all(), 1,
            [], ['requested_fields' => ['content_html']]
        );

        $job->refresh();
        $this->assertSame(2, $job->target_manifest_json['target_count']);
        $this->assertSame($manifest['target_manifest_hash'], $job->target_manifest_hash);
        $this->assertTrue(app(ProductBulkTargetResolver::class)->verify($job->target_manifest_json));
        $this->assertSame($products->pluck('id')->map(fn ($id) => (int) $id)->all(), (new AiProductContentBatchJob($job->id))->productIds);
    }

    public function test_manifest_tamper_is_blocked_before_batch_processing(): void
    {
        $product = Product::factory()->create();
        $job = AiProductJob::create([
            'type' => 'generate_ai_content', 'scope' => 'SELECTED', 'status' => 'queued',
            'total' => 1, 'config_json' => [],
        ]);
        app(ProductBulkGenerationManifest::class)->freeze($job, ProductBulkTargetResolver::SELECTED, [$product->id], 1);
        $job->refresh();
        $tampered = $job->target_manifest_json;
        $tampered['resolved_product_ids'][] = 999999;
        $job->update(['target_manifest_json' => $tampered]);

        $this->expectExceptionMessage('MANIFEST_TAMPERED');
        app(ProductBulkGenerationManifest::class)->loadVerified($job->refresh());
    }

    public function test_empty_canonical_scope_cannot_create_manifest(): void
    {
        $this->expectExceptionMessage('EMPTY_SELECTED_SCOPE');
        app(ProductBulkTargetResolver::class)->resolve(ProductBulkTargetResolver::SELECTED);
    }

    public function test_apply_manifest_is_separate_and_tamper_evident(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $draft = \App\Models\AiProductDraft::create([
            'product_id' => $product->id, 'status' => 'needs_review', 'approval_status' => 'APPROVED_FOR_APPLY',
            'normalized_output_json' => ['content_html' => '<p>approved</p>'],
            'approved_fields_json' => ['content_html'], 'approved_payload_hash' => str_repeat('a', 64),
            'approved_technical_context_hash' => str_repeat('b', 64), 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        $apply = $this->mock(AIProductDraftApplyService::class);
        $apply->shouldReceive('eligibility')->once()->andReturn(['eligible_for_approval' => true]);
        $apply->shouldReceive('contentHash')->once()->andReturn(str_repeat('c', 64));

        $batch = app(AIBulkApplyManifestService::class)->create('generation-batch-1', [$draft], $user->id);
        $this->assertCount(1, $batch->items);
        $this->assertTrue(app(AIBulkApplyManifestService::class)->verify($batch));
        $batch->update(['manifest_json' => array_merge($batch->manifest_json, ['tampered' => true])]);
        $this->assertFalse(app(AIBulkApplyManifestService::class)->verify($batch->refresh()));
    }
}
