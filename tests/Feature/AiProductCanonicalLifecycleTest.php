<?php

namespace Tests\Feature;

use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\AI\AIJobStateMachine;
use App\Services\AI\AiProductContentStateResolver;
use App\Services\AI\AiProductIntegrityAuditor;
use App\Services\AI\AiProductLifecycleService;
use App\Services\AI\AiProductParentReconciler;
use App\Services\Product\AIProductContentSystem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiProductCanonicalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_lifecycle_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumns('ai_product_jobs', [
            'cancel_requested_at', 'cancel_requested_by', 'cancel_reason', 'cancelled_at',
        ]));
        $this->assertTrue(Schema::hasColumns('ai_product_job_items', [
            'dispatch_uuid', 'cancel_requested_at', 'cancel_requested_by', 'cancel_reason', 'cancelled_at',
        ]));
    }

    public function test_latest_applied_history_does_not_hide_older_actionable_draft(): void
    {
        $product = Product::factory()->create();
        [$reviewDraft] = $this->lineage($product, 'needs_review', 'REVIEW_REQUIRED', 'needs_review', 'REVIEW_REQUIRED');
        [$appliedDraft] = $this->lineage($product, 'completed', 'DONE', 'needs_review', 'APPLIED', now());

        $state = app(AiProductContentStateResolver::class)->resolve($product);

        $this->assertSame('REVIEW_REQUIRED', $state['status']);
        $this->assertSame($reviewDraft->id, $state['actionable_draft']->id);
        $this->assertSame($appliedDraft->id, $state['latest_history']['draft']->id);
        $this->assertNull($state['active_operation']);
    }

    public function test_multiple_actionable_drafts_return_explicit_invariant_blocker(): void
    {
        $product = Product::factory()->create();
        $this->lineage($product, 'needs_review', 'REVIEW_REQUIRED', 'needs_review', 'REVIEW_REQUIRED');
        $this->lineage($product, 'needs_review', 'REVIEW_REQUIRED', 'needs_review', 'REVIEW_REQUIRED');

        $state = app(AiProductContentStateResolver::class)->resolve($product);

        $this->assertSame('BLOCKED', $state['status']);
        $this->assertSame('INVARIANT_BLOCKED', $state['product_state']);
        $this->assertContains('MULTIPLE_ACTIONABLE_DRAFTS', $state['blockers']);
    }

    public function test_terminal_history_leaves_product_available_for_new_generation(): void
    {
        $product = Product::factory()->create();
        $this->lineage($product, 'failed', 'FAILED', 'failed', 'REVIEW_REQUIRED');

        $state = app(AiProductContentStateResolver::class)->resolve($product);

        $this->assertSame('AVAILABLE', $state['status']);
        $this->assertSame('AVAILABLE', $state['product_state']);
        $this->assertContains('GENERATE', $state['next_actions']);
        $this->assertNull($state['active_operation']);
        $this->assertNull($state['item']);
        $this->assertNull($state['draft']);
        $this->assertSame('FAILED', $state['latest_history']['status']);
    }

    public function test_terminal_item_cannot_be_reopened(): void
    {
        $product = Product::factory()->create();
        [, $item] = $this->lineage($product, 'failed', 'FAILED', 'failed', 'REVIEW_REQUIRED');

        $this->expectException(InvalidArgumentException::class);
        AIJobStateMachine::transition($item, AIJobStateMachine::QUEUED, 'manual_retry');
    }

    public function test_parent_reconciler_makes_all_terminal_children_terminal(): void
    {
        $job = $this->job('processing', 'RUNNING', 3);
        foreach (['DONE' => 'completed', 'REVIEW_REQUIRED' => 'needs_review', 'FAILED' => 'failed'] as $canonical => $legacy) {
            $job->items()->create([
                'product_id' => Product::factory()->create()->id,
                'status' => $legacy,
                'canonical_status' => $canonical,
            ]);
        }

        $parent = app(AiProductParentReconciler::class)->reconcile($job);

        $this->assertSame('FAILED', $parent->canonical_status);
        $this->assertSame('completed_with_errors', $parent->status);
        $this->assertSame(3, $parent->processed);
        $this->assertNotNull($parent->finished_at);
    }

    public function test_integrity_auditor_reports_known_mismatch_without_mutation(): void
    {
        $product = Product::factory()->create();
        $job = $this->job('completed', 'RUNNING');
        $item = $job->items()->create([
            'product_id' => $product->id,
            'status' => 'failed',
            'canonical_status' => 'RUNNING',
        ]);
        $before = [$item->status, $item->canonical_status, $item->updated_at?->toISOString()];

        $audit = app(AiProductIntegrityAuditor::class)->audit();

        $this->assertSame(0, $audit['summary']['unknown']);
        $this->assertContains('LEGACY_CANONICAL_ITEM_MISMATCH', array_column($audit['violations'], 'code'));
        $fresh = $item->fresh();
        $this->assertSame($before, [$fresh->status, $fresh->canonical_status, $fresh->updated_at?->toISOString()]);
        $this->artisan('ai:product-integrity-audit --json')->assertSuccessful();
    }

    public function test_integrity_auditor_correlates_active_items_with_dispatch_identity(): void
    {
        $product = Product::factory()->create();
        $job = $this->job('queued', 'QUEUED');
        $item = $job->items()->create([
            'product_id' => $product->id,
            'status' => 'queued',
            'canonical_status' => 'QUEUED',
            'dispatch_uuid' => null,
        ]);

        $audit = app(AiProductIntegrityAuditor::class)->audit();
        $row = collect($audit['violations'])->firstWhere('code', 'ACTIVE_ITEM_MISSING_DISPATCH_UUID');

        $this->assertNotNull($row);
        $this->assertSame($item->id, $row['id']);
        $this->assertSame('KNOWN', $row['classification']);
        $this->assertNull($item->fresh()->dispatch_uuid);
    }

    public function test_queued_cancel_is_terminal_and_releases_parent_immediately(): void
    {
        $product = Product::factory()->create();
        $job = $this->job('queued', 'QUEUED');
        $item = $job->items()->create([
            'product_id' => $product->id, 'status' => 'queued', 'canonical_status' => 'QUEUED',
        ]);

        app(AiProductLifecycleService::class)->requestCancel($job, null, 'fixture cancel');

        $this->assertSame('CANCELLED', $item->refresh()->canonical_status);
        $this->assertNotNull($item->cancelled_at);
        $this->assertSame('CANCELLED', $job->refresh()->canonical_status);
        $this->assertNotNull($job->finished_at);
    }

    public function test_running_cancel_records_intent_then_worker_checkpoint_cancels(): void
    {
        $product = Product::factory()->create();
        $job = $this->job('processing', 'RUNNING');
        $item = $job->items()->create([
            'product_id' => $product->id, 'status' => 'processing', 'canonical_status' => 'RUNNING',
        ]);
        $lifecycle = app(AiProductLifecycleService::class);

        $lifecycle->requestCancel($job, null, 'running fixture cancel');
        $this->assertSame('RUNNING', $item->refresh()->canonical_status);
        $this->assertNotNull($item->cancel_requested_at);
        $this->assertTrue($lifecycle->checkpointCancellation($item, 'TEST_CHECKPOINT'));

        $this->assertSame('CANCELLED', $item->refresh()->canonical_status);
        $this->assertSame('CANCELLED', $job->refresh()->canonical_status);
    }

    public function test_stale_active_recovery_keeps_item_nonterminal_and_rotates_dispatch_identity(): void
    {
        $product = Product::factory()->create();
        $job = $this->job('processing', 'RUNNING');
        $item = $job->items()->create([
            'product_id' => $product->id, 'status' => 'processing', 'canonical_status' => 'RUNNING',
            'dispatch_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $oldDispatch = $item->dispatch_uuid;

        $this->assertTrue(app(AiProductLifecycleService::class)->recoverStaleItem($item, 3));

        $item->refresh();
        $this->assertSame('QUEUED', $item->canonical_status);
        $this->assertSame('queued', $item->status);
        $this->assertNotSame($oldDispatch, $item->dispatch_uuid);
        $this->assertSame(1, $item->retry_count);
    }

    public function test_double_generation_creation_has_one_active_operation(): void
    {
        $actor = $this->generationActor();
        $product = Product::factory()->create();
        $lifecycle = app(AiProductLifecycleService::class);

        [$firstJob, $firstItem, $firstCreated] = $lifecycle->createGenerationOperation($product, [], $actor);
        [$secondJob, $secondItem, $secondCreated] = $lifecycle->createGenerationOperation($product, [], $actor);

        $this->assertTrue($firstCreated);
        $this->assertFalse($secondCreated);
        $this->assertSame($firstJob->id, $secondJob->id);
        $this->assertSame($firstItem->id, $secondItem->id);
        $this->assertSame(1, AiProductJobItem::where('product_id', $product->id)->count());
        $this->assertNotNull($firstItem->dispatch_uuid);
    }

    public function test_cancelled_before_worker_and_wrong_dispatch_never_call_content_system(): void
    {
        $actor = $this->generationActor();
        $product = Product::factory()->create();
        [$job, $item] = app(AiProductLifecycleService::class)->createGenerationOperation($product, [], $actor);
        app(AiProductLifecycleService::class)->requestCancel($job, $actor->id, 'cancel before worker');
        $system = Mockery::mock(AIProductContentSystem::class);
        $system->shouldNotReceive('generate');

        (new \App\Jobs\AiProductContentSingleJob($product->id, $job->id, $item->id, $item->dispatch_uuid))
            ->handle($system);

        $this->assertSame('CANCELLED', $item->refresh()->canonical_status);

        $other = Product::factory()->create();
        [$otherJob, $otherItem] = app(AiProductLifecycleService::class)->createGenerationOperation($other, [], $actor);
        (new \App\Jobs\AiProductContentSingleJob($other->id, $otherJob->id, $otherItem->id, (string) \Illuminate\Support\Str::uuid()))
            ->handle($system);
        $this->assertSame('QUEUED', $otherItem->refresh()->canonical_status);
    }

    public function test_parent_aggregation_matrix_is_canonical(): void
    {
        $cases = [
            [['DONE', 'DONE'], 'DONE'],
            [['DONE', 'REVIEW_REQUIRED'], 'REVIEW_REQUIRED'],
            [['BLOCKED', 'BLOCKED'], 'BLOCKED'],
            [['CANCELLED', 'CANCELLED'], 'CANCELLED'],
            [['DONE', 'BLOCKED'], 'FAILED'],
        ];
        $legacy = [
            'DONE' => 'completed', 'REVIEW_REQUIRED' => 'needs_review', 'BLOCKED' => 'blocked',
            'CANCELLED' => 'cancelled', 'FAILED' => 'failed',
        ];
        foreach ($cases as [$children, $expected]) {
            $job = $this->job('processing', 'RUNNING', count($children));
            foreach ($children as $state) {
                $job->items()->create([
                    'product_id' => Product::factory()->create()->id,
                    'status' => $legacy[$state], 'canonical_status' => $state,
                ]);
            }
            $this->assertSame($expected, app(AiProductParentReconciler::class)->reconcile($job)->canonical_status);
        }
    }

    /** @return array{AiProductDraft,AiProductJobItem} */
    private function lineage(
        Product $product,
        string $itemStatus,
        string $canonical,
        string $draftStatus,
        string $approvalStatus,
        $appliedAt = null,
    ): array {
        $job = $this->job($itemStatus, $canonical);
        $draft = AiProductDraft::create([
            'job_id' => $job->id,
            'product_id' => $product->id,
            'status' => $draftStatus,
            'approval_status' => $approvalStatus,
            'applied_at' => $appliedAt,
            'normalized_output_json' => ['content_html' => '<h2>Fixture</h2>'],
        ]);
        $item = $job->items()->create([
            'product_id' => $product->id,
            'status' => $itemStatus,
            'canonical_status' => $canonical,
            'draft_id' => $draft->id,
        ]);

        return [$draft, $item];
    }

    private function job(string $status = 'queued', string $canonical = 'QUEUED', int $total = 1): AiProductJob
    {
        return AiProductJob::create([
            'type' => 'canonical_fixture', 'scope' => 'selected', 'status' => $status,
            'canonical_status' => $canonical, 'total' => $total, 'config_json' => [],
        ]);
    }

    private function generationActor(): \App\Models\User
    {
        Permission::firstOrCreate(['name' => 'product.ai_generate', 'guard_name' => 'web']);
        $actor = UserFactory::new()->create(['is_active' => true]);
        $actor->givePermissionTo('product.ai_generate');

        return $actor;
    }
}
