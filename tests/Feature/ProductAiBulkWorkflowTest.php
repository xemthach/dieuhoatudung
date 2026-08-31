<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Jobs\AiProductContentBatchJob;
use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Models\ProductBulkOperation;
use App\Models\User;
use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AIWorkerReadinessService;
use App\Services\AI\ProductAiBulkWorkflowService;
use App\Services\Product\AIProductContentSystem;
use App\Services\Product\AIProductDraftApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductAiBulkWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['is_active' => true]);
        foreach (['product.view', 'product.edit', 'product.ai_generate', 'bulk_ai_view', 'bulk_ai_approve', 'bulk_ai_apply'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $this->actor->givePermissionTo(['product.view', 'product.edit', 'product.ai_generate', 'bulk_ai_view', 'bulk_ai_approve', 'bulk_ai_apply']);
    }

    public function test_mixed_state_preflight_is_deterministic_and_query_count_is_bounded(): void
    {
        $fixture = $this->mixedFixture();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $preflight = app(ProductAiBulkWorkflowService::class)->preflight(collect($fixture)->pluck('id')->all());
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(10, $preflight['selected']);
        $this->assertSame(1, $preflight['counts']['NOT_GENERATED']);
        $this->assertSame(1, $preflight['counts']['PROCESSING']);
        $this->assertSame(2, $preflight['counts']['REVIEW_REQUIRED']);
        $this->assertSame(1, $preflight['counts']['APPROVED']);
        $this->assertSame(1, $preflight['counts']['APPLIED']);
        $this->assertSame(1, $preflight['counts']['FAILED']);
        $this->assertSame(1, $preflight['counts']['BLOCKED']);
        $this->assertSame(1, $preflight['counts']['REJECTED']);
        $this->assertSame(1, $preflight['counts']['DISCARDED']);
        $this->assertSame(2, $preflight['classifications']['READY_TO_APPROVE']);
        $this->assertSame(1, $preflight['classifications']['READY_TO_APPLY']);
        $this->assertLessThanOrEqual(12, $queryCount, 'Preflight must eager-load canonical state instead of issuing one query per Product.');
    }

    public function test_bulk_approve_partial_policy_and_warning_override_are_truthful(): void
    {
        $fixture = $this->mixedFixture();
        $ids = [$fixture['review_clean']->id, $fixture['review_warning']->id, $fixture['blocked']->id, $fixture['approved']->id];

        $first = app(ProductAiBulkWorkflowService::class)->execute('APPROVE', $ids, $this->actor, ['warning_override' => false]);
        $this->assertSame(['selected' => 4, 'success' => 1, 'skipped' => 2, 'blocked' => 1, 'failed' => 0], $first['summary']);
        $this->assertSame('APPROVED_FOR_APPLY', $this->draftFor($fixture['review_clean'])->approval_status);
        $this->assertSame('REVIEW_REQUIRED', $this->draftFor($fixture['review_warning'])->approval_status);

        $second = app(ProductAiBulkWorkflowService::class)->execute(
            'APPROVE',
            [$fixture['review_warning']->id],
            $this->actor,
            ['warning_override' => true, 'reason' => 'Editorial review completed'],
        );
        $this->assertSame(1, $second['summary']['success']);
        $warningDraft = $this->draftFor($fixture['review_warning']);
        $this->assertSame('APPROVED_FOR_APPLY', $warningDraft->approval_status);
        $this->assertTrue($warningDraft->warning_override);
        $this->assertSame(['content_too_short:459/800'], $warningDraft->warnings_at_approval);
        $this->assertStringContainsString($second['operation']->operation_uuid, $warningDraft->review_note);
    }

    public function test_filament_bulk_approve_uses_selected_records_and_domain_service(): void
    {
        $review = $this->reviewProduct();
        $other = $this->reviewProduct();
        $this->actingAs($this->actor);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('ai_bulk_approve', collect([$review]), [
                'product_ids' => [(string) $review->id],
                'warning_override' => false,
                'reason' => 'Reviewed in Product list',
            ]);

        $this->assertSame('APPROVED_FOR_APPLY', $this->draftFor($review)->approval_status);
        $this->assertSame('REVIEW_REQUIRED', $this->draftFor($other)->approval_status);
        $operation = ProductBulkOperation::latest('id')->firstOrFail();
        $this->assertSame([$review->id], array_map('intval', $operation->product_ids_json));
        $this->assertSame('COMPLETED', $operation->status);
    }

    public function test_bulk_apply_is_per_item_idempotent_and_stale_target_is_blocked(): void
    {
        $ready = $this->approvedProduct();
        $alreadyApplied = $this->approvedProduct(true);
        $stale = $this->approvedProduct();
        $stale->forceFill(['seo_title' => 'Human edit after approval'])->save();
        $service = app(AIProductDraftApplyService::class);
        $before = $service->contentHash($ready->fresh());

        $result = app(ProductAiBulkWorkflowService::class)->execute(
            'APPLY',
            [$ready->id, $alreadyApplied->id, $stale->id],
            $this->actor,
            ['confirmation' => 'APPLY 1 PRODUCTS'],
        );

        $this->assertSame(1, $result['summary']['success']);
        $this->assertSame(1, $result['summary']['skipped']);
        $this->assertSame(1, $result['summary']['blocked']);
        $this->assertNotSame($before, $service->contentHash($ready->fresh()));
        $this->assertNotNull($this->draftFor($ready)->applied_at);

        $versions = $ready->aiContentVersions()->count();
        $again = app(ProductAiBulkWorkflowService::class)->execute(
            'APPLY',
            [$ready->id],
            $this->actor,
            ['confirmation' => 'APPLY 0 PRODUCTS'],
        );
        $this->assertSame(0, $again['summary']['success']);
        $this->assertSame(1, $again['summary']['skipped']);
        $this->assertSame($versions, $ready->aiContentVersions()->count());
    }

    public function test_bulk_reject_and_discard_persist_actor_reason_without_deleting_evidence(): void
    {
        $reject = $this->reviewProduct();
        $discard = $this->reviewProduct();
        $jobCount = AiProductJob::count();

        $rejected = app(ProductAiBulkWorkflowService::class)->execute('REJECT', [$reject->id], $this->actor, ['reason' => 'Incorrect editorial direction']);
        $discarded = app(ProductAiBulkWorkflowService::class)->execute('DISCARD', [$discard->id], $this->actor, ['reason' => 'Obsolete campaign draft']);

        $this->assertSame(1, $rejected['summary']['success']);
        $this->assertSame('REJECTED', $this->draftFor($reject)->approval_status);
        $this->assertSame($this->actor->id, $this->draftFor($reject)->rejected_by);
        $this->assertSame(1, $discarded['summary']['success']);
        $this->assertSame('DISCARDED', $this->draftFor($discard)->approval_status);
        $this->assertSame($this->actor->id, $this->draftFor($discard)->discarded_by);
        $this->assertSame($jobCount, AiProductJob::count());
        $this->assertSame(2, AiProductDraft::whereIn('product_id', [$reject->id, $discard->id])->count());
    }

    public function test_bulk_regenerate_snapshots_only_eligible_targets_and_uses_governed_queue(): void
    {
        Bus::fake();
        $fixture = $this->mixedFixture();
        $monitor = $this->mock(AIQueueMonitor::class);
        $monitor->shouldReceive('liveStatusHealth')->andReturn([
            'worker_desired_state' => 'ENABLED',
            'worker_heartbeat' => ['health_status' => 'ONLINE', 'accepting_new_jobs' => true],
        ]);
        $this->app->forgetInstance(AIWorkerReadinessService::class);

        $ids = collect($fixture)->pluck('id')->all();
        $result = app(ProductAiBulkWorkflowService::class)->execute('REGENERATE', $ids, $this->actor);

        $this->assertSame(5, $result['summary']['success']);
        $job = AiProductJob::where('type', 'regenerate_ai_content')->latest('id')->firstOrFail();
        $this->assertSame(5, $job->total);
        $this->assertSame('ai_governed', $job->queue_name);
        $this->assertTrue(app(\App\Services\AI\ProductBulkTargetResolver::class)->verify($job->target_manifest_json));
        $this->assertSame('DONE', $fixture['review_clean']->latestAiProductJobItem->fresh()->canonical_status);
        Bus::assertDispatched(AiProductContentBatchJob::class);
    }

    public function test_server_side_rbac_blocks_each_bulk_mutation_without_permission(): void
    {
        $product = $this->reviewProduct();
        $unauthorized = User::factory()->create(['is_active' => true]);

        foreach (['APPROVE', 'REJECT', 'DISCARD', 'REGENERATE', 'APPLY'] as $action) {
            try {
                app(ProductAiBulkWorkflowService::class)->execute($action, [$product->id], $unauthorized);
                $this->fail("{$action} should be denied.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('FORBIDDEN', $exception->getMessage());
            }
        }
        $this->assertSame(0, ProductBulkOperation::count());
    }

    /** @return array<string,Product> */
    private function mixedFixture(): array
    {
        $noDraft = Product::factory()->create();
        $processing = Product::factory()->create();
        $this->item($processing, 'processing', 'RUNNING');
        $reviewClean = $this->reviewProduct();
        $reviewWarning = $this->reviewProduct(['content_too_short:459/800']);
        $approved = $this->approvedProduct();
        $applied = $this->approvedProduct(true);
        $failed = Product::factory()->create();
        $this->item($failed, 'failed', 'FAILED');
        $blocked = Product::factory()->create();
        $this->item($blocked, 'blocked', 'BLOCKED');
        $rejected = $this->reviewProduct();
        app(AIProductDraftApplyService::class)->reject($this->draftFor($rejected), $this->actor->id, 'Rejected fixture', $this->actor);
        $discarded = $this->reviewProduct();
        app(AIProductDraftApplyService::class)->discard($this->draftFor($discarded), $this->actor->id, 'Discarded fixture', $this->actor);

        return [
            'no_draft' => $noDraft,
            'processing' => $processing,
            'review_clean' => $reviewClean,
            'review_warning' => $reviewWarning,
            'approved' => $approved,
            'applied' => $applied,
            'failed' => $failed,
            'blocked' => $blocked,
            'rejected' => $rejected,
            'discarded' => $discarded,
        ];
    }

    private function reviewProduct(array $warnings = []): Product
    {
        $product = Product::factory()->create([
            'model_code' => 'BULK-'.uniqid(),
            'short_description' => 'Old excerpt',
            'long_description' => '<p>Old content</p>',
            'seo_title' => 'Old SEO',
            'seo_description' => 'Old meta',
        ]);
        $payload = [
            'excerpt' => 'New excerpt',
            'content_html' => '<h2>Ná»™i dung Ä‘Ã£ kiá»ƒm tra</h2><h3>á»¨ng dá»¥ng</h3><p>Draft an toÃ n cho bulk workflow.</p>',
            'seo_title' => 'New SEO title',
            'meta_description' => 'New meta description',
            'blocked_claims' => [],
            'fact_check' => ['blocked_claims' => [], 'status' => 'passed'],
        ];
        $job = AiProductJob::create(['type' => 'product_content', 'scope' => 'selected', 'status' => 'needs_review', 'total' => 1, 'needs_review' => 1, 'created_by' => $this->actor->id]);
        $item = $this->item($product, 'needs_review', 'REVIEW_REQUIRED', $job);
        $draft = AiProductDraft::create([
            'job_id' => $job->id,
            'product_id' => $product->id,
            'normalized_output_json' => $payload,
            'raw_output_json' => $payload,
            'field_status_json' => ['excerpt' => 'GENERATED', 'content_html' => 'GENERATED', 'seo_title' => 'GENERATED', 'meta_description' => 'GENERATED'],
            'warnings_json' => $warnings,
            'validation_errors_json' => [],
            'used_verified_facts_json' => [],
            'token_usage_json' => ['technical_context_hash' => app(AIProductContentSystem::class)->technicalContextHash($product), 'provider_called' => true],
            'status' => 'needs_review',
        ]);
        $item->update(['draft_id' => $draft->id, 'warnings_json' => $warnings, 'seo_score_after' => 75]);
        return $product->fresh();
    }

    private function approvedProduct(bool $apply = false): Product
    {
        $product = $this->reviewProduct();
        $draft = $this->draftFor($product);
        app(AIProductDraftApplyService::class)->approve($draft, $this->actor->id, $this->actor);
        if ($apply) app(AIProductDraftApplyService::class)->apply($draft->fresh(), $this->actor->id);
        return $product->fresh();
    }

    private function item(Product $product, string $status, string $canonical, ?AiProductJob $job = null): AiProductJobItem
    {
        $job ??= AiProductJob::create(['type' => 'product_content', 'scope' => 'selected', 'status' => $status, 'total' => 1, 'created_by' => $this->actor->id]);
        return AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => $status,
            'canonical_status' => $canonical,
            'status_reason' => $canonical === 'BLOCKED' ? 'CONCURRENCY_BLOCK' : null,
        ]);
    }

    private function draftFor(Product $product): AiProductDraft
    {
        return AiProductDraft::where('product_id', $product->id)->latest('id')->firstOrFail()->refresh();
    }
}
