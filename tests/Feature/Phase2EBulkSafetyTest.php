<?php

namespace Tests\Feature;

use App\Services\AI\BulkAiRolloutPolicy;
use App\Services\AI\ProductBulkTargetResolver;
use RuntimeException;
use Tests\TestCase;

class Phase2EBulkSafetyTest extends TestCase
{
    public function test_selected_scope_freezes_exactly_two_and_deduplicates(): void
    {
        $resolver = app(ProductBulkTargetResolver::class);
        $this->assertSame([1239, 1240], $resolver->resolve(ProductBulkTargetResolver::SELECTED, [1239, 1240, 1240]));
    }

    public function test_empty_selected_never_falls_back_to_all(): void
    {
        $this->expectExceptionMessage('EMPTY_SELECTED_SCOPE');
        app(ProductBulkTargetResolver::class)->resolve(ProductBulkTargetResolver::SELECTED, []);
    }

    public function test_page_filter_and_explicit_all_are_distinct(): void
    {
        $resolver = app(ProductBulkTargetResolver::class);
        $this->assertSame([1,2], $resolver->resolve(ProductBulkTargetResolver::CURRENT_PAGE, [], [1,2]));
        $this->assertSame([3,4], $resolver->resolve(ProductBulkTargetResolver::CURRENT_FILTER, [], [], [3,4]));
        $this->expectExceptionMessage('EXPLICIT_ALL_CONFIRMATION_REQUIRED');
        $resolver->resolve(ProductBulkTargetResolver::ALL_MATCHING, [], [], [], [1,2]);
    }

    public function test_manifest_hash_detects_target_mutation_and_preserves_filter_snapshot(): void
    {
        $resolver = app(ProductBulkTargetResolver::class);
        $manifest = $resolver->manifest(ProductBulkTargetResolver::CURRENT_FILTER, [1,2,3], 7, ['brand_id'=>4], ['batch_uuid'=>'fixed']);
        $this->assertTrue($resolver->verify($manifest));
        $manifest['resolved_product_ids'][] = 4;
        $this->assertFalse($resolver->verify($manifest));
    }

    public function test_eligibility_and_retry_matrix_are_governed(): void
    {
        $policy = app(BulkAiRolloutPolicy::class);
        $this->assertSame('ELIGIBLE', $policy->eligibility([]));
        $this->assertSame('STALE', $policy->eligibility(['stale'=>true]));
        $this->assertSame('MISSING_VERIFIED_CONTEXT', $policy->eligibility(['verified_context'=>false]));
        $this->assertSame('DUPLICATE', $policy->eligibility(['duplicate'=>true]));
        $this->assertTrue($policy->retryable('rate_limited'));
        $this->assertFalse($policy->retryable('fact_check_blocked'));
    }

    public function test_batch_state_pause_resume_cancel_and_invalid_transition(): void
    {
        $policy = app(BulkAiRolloutPolicy::class);
        $this->assertSame(BulkAiRolloutPolicy::READY, $policy->transition(BulkAiRolloutPolicy::DRAFT, BulkAiRolloutPolicy::READY));
        $this->assertSame(BulkAiRolloutPolicy::RUNNING, $policy->transition(BulkAiRolloutPolicy::PAUSED, BulkAiRolloutPolicy::RUNNING));
        $this->assertSame(BulkAiRolloutPolicy::CANCELLED, $policy->transition(BulkAiRolloutPolicy::QUEUED, BulkAiRolloutPolicy::CANCELLED));
        $this->expectExceptionMessage('INVALID_BATCH_TRANSITION');
        $policy->transition(BulkAiRolloutPolicy::COMPLETED, BulkAiRolloutPolicy::RUNNING);
    }

    public function test_fake_scale_has_bounded_concurrency_exact_calls_and_budget_pause(): void
    {
        $policy = app(BulkAiRolloutPolicy::class);
        $items=[]; for($i=1;$i<=40;$i++) $items[]=['id'=>$i,'tokens'=>100];
        $items[]=['id'=>1,'tokens'=>100];
        $items[5]['stale']=true; $items[6]['verified_context']=false;
        $result=$policy->simulate($items,3,3000);
        $this->assertSame(41,count($items));
        $this->assertLessThanOrEqual(3,$result['concurrency']);
        $this->assertSame(30,$result['provider_calls']);
        $this->assertSame(3000,$result['tokens']);
        $this->assertTrue($result['paused']);
    }

    public function test_chunk_sizes_and_rbac_scope_are_explicit(): void
    {
        $policy=app(BulkAiRolloutPolicy::class);
        $this->assertCount(4,$policy->chunk(range(1,20),5));
        $this->assertCount(20,$policy->chunk(range(1,20),1));
        $this->expectExceptionMessage('PRODUCT_SCOPE_FORBIDDEN');
        app(ProductBulkTargetResolver::class)->resolve(ProductBulkTargetResolver::SELECTED,[1,2],[],[],[],false,[1]);
    }
}
