<?php

namespace Tests\Feature;

use App\Services\AI\AIJobStateMachine;
use App\Services\AI\AIProductIdempotencyService;
use App\Services\Product\ProductAIContentService;
use App\Services\Product\ProductContentEligibilityPolicy;
use App\Models\Product;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class Phase2AArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_contract_columns_are_additive_and_available_in_test_schema(): void
    {
        $this->assertTrue(Schema::hasColumn('ai_product_job_items', 'idempotency_key'));
        $this->assertTrue(Schema::hasColumn('ai_product_job_items', 'technical_context_hash'));
        $this->assertTrue(Schema::hasColumn('ai_product_job_items', 'canonical_status'));
        $this->assertTrue(Schema::hasColumn('ai_product_jobs', 'canonical_status'));
    }

    public function test_idempotency_key_changes_when_verified_context_changes(): void
    {
        $product = Product::factory()->create([
            'technical_capacity_btu' => 42650,
            'technical_capacity_status' => 'verified_candidate',
        ]);
        $service = app(AIProductIdempotencyService::class);
        $config = ['outputs' => ['content' => true, 'faq' => true]];

        $first = $service->key($product, $config);
        $product->technical_capacity_btu = 48000;
        $product->save();

        $this->assertNotSame($first, $service->key($product->refresh(), $config));
    }

    public function test_legacy_product_ai_service_is_hard_disabled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Legacy Product AI path disabled');

        app(ProductAIContentService::class)->generateContent([], []);
    }

    public function test_legacy_statuses_map_to_canonical_lifecycle(): void
    {
        $this->assertSame(AIJobStateMachine::QUEUED, AIJobStateMachine::fromLegacy('queued'));
        $this->assertSame(AIJobStateMachine::RUNNING, AIJobStateMachine::fromLegacy('processing'));
        $this->assertSame(AIJobStateMachine::REVIEW_REQUIRED, AIJobStateMachine::fromLegacy('needs_review'));
        $this->assertSame(AIJobStateMachine::DONE, AIJobStateMachine::fromLegacy('completed'));
        $this->assertSame(AIJobStateMachine::FAILED, AIJobStateMachine::fromLegacy('completed_with_errors'));
        $this->assertSame(AIJobStateMachine::BLOCKED, AIJobStateMachine::fromLegacy('stuck'));
    }

    public function test_identical_successful_request_is_reused_and_context_change_is_new(): void
    {
        $product = Product::factory()->create();
        $job = AiProductJob::create(['type' => 'test', 'scope' => 'selected', 'status' => 'queued']);
        $service = app(AIProductIdempotencyService::class);
        $config = ['outputs' => ['faq' => true], 'provider_policy' => 'fake'];
        $key = $service->key($product, $config, 'faq', ['faq']);

        $item = AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'completed',
            'canonical_status' => AIJobStateMachine::DONE,
            'idempotency_key' => $key,
        ]);

        $this->assertSame($item->id, $service->existing($key)?->id);
        $product->forceFill([
            'technical_capacity_btu' => 42650,
            'technical_capacity_status' => 'verified_candidate',
        ])->save();
        $this->assertNotSame($key, $service->key($product->refresh(), $config, 'faq', ['faq']));
    }

    public function test_field_retry_has_a_distinct_idempotency_key(): void
    {
        $product = Product::factory()->create();
        $service = app(AIProductIdempotencyService::class);
        $config = ['outputs' => ['content' => true, 'faq' => true], 'provider_policy' => 'fake'];

        $this->assertNotSame(
            $service->key($product, $config, 'product_content', ['content', 'faq']),
            $service->key($product, $config, 'faq_retry', ['faq'])
        );
    }

    public function test_new_authorized_operation_generation_does_not_reuse_failed_history_key(): void
    {
        $product = Product::factory()->create();
        $service = app(AIProductIdempotencyService::class);
        $base = ['outputs' => ['content' => true], 'provider_policy' => 'fake'];

        $failedKey = $service->key($product, $base + ['operation_generation' => 'historical-operation'], 'product_content', ['content_html']);
        $freshKey = $service->key($product, $base + ['operation_generation' => 'new-authorized-operation'], 'product_content', ['content_html']);

        $this->assertNotSame($failedKey, $freshKey);
    }

    public function test_historical_product_1241_remains_blocked_for_content_generation(): void
    {
        $product = Product::factory()->make(['id' => 1241, 'name' => 'Gree', 'model_code' => 'GDC36S6I/GMC36S6I']);
        $result = app(ProductContentEligibilityPolicy::class)->evaluate($product, ProductContentEligibilityPolicy::LONG_DESCRIPTION);

        $this->assertFalse($result['eligible']);
        $this->assertContains('HISTORICAL_ROLLOUT_DISPOSITION_PRESERVED', $result['reasons']);
    }

    public function test_terminal_state_cannot_be_recycled(): void
    {
        $job = AiProductJob::create([
            'type' => 'test',
            'scope' => 'selected',
            'status' => 'completed',
            'canonical_status' => AIJobStateMachine::DONE,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        AIJobStateMachine::transition($job, AIJobStateMachine::RUNNING, 'invalid_recycle');
    }
}
