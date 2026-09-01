<?php

namespace Tests\Feature;

use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\AI\AiProductContentStateResolver;
use App\Services\AI\AiProductLiveStatusService;
use App\Services\AI\ProductAiActionResolver;
use App\Services\AI\ProductAiBulkWorkflowService;
use App\Services\AI\ProductAiGenerationReadiness;
use App\Services\Product\ProductContentEligibilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProductCurrentHistoryContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_duplicate_block_is_history_while_current_product_is_available(): void
    {
        $product = Product::factory()->create(['ai_status' => 'blocked']);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'filter',
            'status' => 'cancelled',
            'canonical_status' => 'QUEUED',
            'total' => 272,
            'processed' => 272,
            'finished_at' => now(),
            'config_json' => [],
        ]);
        $item = $job->items()->create([
            'product_id' => $product->id,
            'status' => 'blocked',
            'canonical_status' => 'BLOCKED',
            'status_reason' => 'DUPLICATE_IN_PROGRESS',
            'draft_id' => null,
            'started_at' => null,
            'finished_at' => now(),
        ]);

        $state = app(AiProductContentStateResolver::class)->resolve($product);
        $actions = app(ProductAiActionResolver::class)->resolve($product);
        $readiness = app(ProductAiGenerationReadiness::class)->resolve(
            $product->fresh(['brand', 'category']),
            ProductContentEligibilityPolicy::LONG_DESCRIPTION,
            ['worker' => ['ready' => true], 'provider' => ['ready' => true]],
        );

        $this->assertSame('AVAILABLE', $state['product_state']);
        $this->assertSame('AVAILABLE', $state['status']);
        $this->assertNull($state['item']);
        $this->assertNull($state['draft']);
        $this->assertNull($state['active_operation']);
        $this->assertNull($state['actionable_draft']);
        $this->assertNull($state['approved_draft']);
        $this->assertNull($state['state_issue']);
        $this->assertSame([], $state['blockers']);
        $this->assertSame(['GENERATE'], $state['next_actions']);
        $this->assertSame($item->id, $state['latest_history']['item']->id);
        $this->assertSame('BLOCKED', $state['latest_history']['status']);
        $this->assertSame('DUPLICATE_IN_PROGRESS', $state['latest_history']['reason']);

        $this->assertSame('AVAILABLE', $actions['current_state']);
        $this->assertTrue($actions['can_generate_primary']);
        $this->assertFalse($actions['can_view_block_reason']);
        $this->assertTrue($actions['can_view_job']);
        $this->assertTrue($readiness['can_generate']);

        $live = app(AiProductLiveStatusService::class)->forProduct($product->id);
        $bulk = app(ProductAiBulkWorkflowService::class)->preflight([$product->id]);
        $this->assertSame('AVAILABLE', $live['status']['key']);
        $this->assertSame($job->id, $live['history_job_id']);
        $this->assertSame('BLOCKED', $live['history_status']);
        $this->assertSame('AVAILABLE', $bulk['rows'][0]['state']);
        $this->assertTrue($bulk['rows'][0]['ready_to_generate']);
        $this->assertSame(1, $bulk['classifications']['READY_TO_GENERATE']);

        $this->assertDatabaseHas('ai_product_job_items', [
            'id' => $item->id,
            'status' => 'blocked',
            'canonical_status' => 'BLOCKED',
            'status_reason' => 'DUPLICATE_IN_PROGRESS',
        ]);
    }

    public function test_terminal_history_states_do_not_leak_into_current_state(): void
    {
        foreach ([
            ['FAILED', 'failed'],
            ['CANCELLED', 'cancelled'],
            ['BLOCKED', 'blocked'],
            ['DONE', 'completed'],
        ] as [$canonical, $legacy]) {
            $product = Product::factory()->create();
            $item = $this->historicalItem($product, $canonical, $legacy);
            $this->assertAvailableWithHistory($product, $item, $canonical);
        }

        foreach ([
            ['REJECTED', 'rejected', null],
            ['DISCARDED', 'discarded', null],
            ['APPLIED', 'completed', now()],
        ] as [$approval, $status, $appliedAt]) {
            $product = Product::factory()->create();
            $job = $this->job();
            $draft = AiProductDraft::create([
                'job_id' => $job->id,
                'product_id' => $product->id,
                'status' => $status,
                'approval_status' => $approval,
                'applied_at' => $appliedAt,
                'normalized_output_json' => ['content_html' => '<h2>Historical draft</h2>'],
            ]);
            $item = $job->items()->create([
                'product_id' => $product->id,
                'status' => 'completed',
                'canonical_status' => 'DONE',
                'draft_id' => $draft->id,
                'finished_at' => now(),
            ]);
            $this->assertAvailableWithHistory($product, $item, $approval);
        }
    }

    public function test_active_and_actionable_lineage_remains_current(): void
    {
        foreach ([
            ['QUEUED', 'queued', 'QUEUED'],
            ['RUNNING', 'processing', 'PROCESSING'],
            ['VALIDATING', 'validating', 'VALIDATING'],
            ['FACT_CHECKING', 'fact_checking', 'VALIDATING'],
        ] as [$canonical, $legacy, $expectedStatus]) {
            $product = Product::factory()->create();
            $item = $this->job()->items()->create([
                'product_id' => $product->id,
                'status' => $legacy,
                'canonical_status' => $canonical,
            ]);

            $state = app(AiProductContentStateResolver::class)->resolve($product);
            $actions = app(ProductAiActionResolver::class)->resolve($product);

            $this->assertSame('PROCESSING', $state['product_state'], $canonical);
            $this->assertSame($expectedStatus, $state['status'], $canonical);
            $this->assertSame($item->id, $state['item']->id, $canonical);
            $this->assertFalse($actions['can_generate_primary'], $canonical);
            $this->assertTrue($actions['show_processing_status'], $canonical);
        }
    }

    public function test_current_invariant_block_is_not_weakened_by_history_contract(): void
    {
        $product = Product::factory()->create();
        foreach (range(1, 2) as $unused) {
            $this->job()->items()->create([
                'product_id' => $product->id,
                'status' => 'processing',
                'canonical_status' => 'RUNNING',
            ]);
        }

        $state = app(AiProductContentStateResolver::class)->resolve($product);
        $actions = app(ProductAiActionResolver::class)->resolve($product);
        $readiness = app(ProductAiGenerationReadiness::class)->resolve(
            $product->fresh(['brand', 'category']),
            ProductContentEligibilityPolicy::LONG_DESCRIPTION,
            ['worker' => ['ready' => true], 'provider' => ['ready' => true]],
        );

        $this->assertSame('INVARIANT_BLOCKED', $state['product_state']);
        $this->assertSame('BLOCKED', $state['status']);
        $this->assertContains('MULTIPLE_ACTIVE_OPERATIONS', $state['blockers']);
        $this->assertTrue($actions['can_view_block_reason']);
        $this->assertFalse($actions['can_generate_primary']);
        $this->assertFalse($readiness['can_generate']);
        $this->assertContains('MULTIPLE_ACTIVE_OPERATIONS', array_column($readiness['mandatory_blockers'], 'code'));
    }

    private function assertAvailableWithHistory(Product $product, AiProductJobItem $item, string $historyStatus): void
    {
        $state = app(AiProductContentStateResolver::class)->resolve($product);
        $actions = app(ProductAiActionResolver::class)->resolve($product);

        $this->assertSame('AVAILABLE', $state['status'], $historyStatus);
        $this->assertSame('AVAILABLE', $state['product_state'], $historyStatus);
        $this->assertNull($state['item'], $historyStatus);
        $this->assertNull($state['draft'], $historyStatus);
        $this->assertSame($item->id, $state['latest_history']['item']->id, $historyStatus);
        $this->assertSame($historyStatus, $state['latest_history']['status'], $historyStatus);
        $this->assertTrue($actions['can_generate_primary'], $historyStatus);
        $this->assertFalse($actions['can_view_block_reason'], $historyStatus);
    }

    private function historicalItem(Product $product, string $canonical, string $legacy): AiProductJobItem
    {
        return $this->job()->items()->create([
            'product_id' => $product->id,
            'status' => $legacy,
            'canonical_status' => $canonical,
            'status_reason' => $canonical.'_HISTORY',
            'finished_at' => now(),
        ]);
    }

    private function job(): AiProductJob
    {
        return AiProductJob::create([
            'type' => 'history_fixture',
            'scope' => 'selected',
            'status' => 'completed',
            'canonical_status' => 'DONE',
            'total' => 1,
            'processed' => 1,
            'finished_at' => now(),
            'config_json' => [],
        ]);
    }
}
