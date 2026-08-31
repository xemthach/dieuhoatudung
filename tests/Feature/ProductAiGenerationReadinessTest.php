<?php

namespace Tests\Feature;

use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProvider;
use App\Models\Brand;
use App\Models\Product;
use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AIWorkerReadinessService;
use App\Services\AI\ProductAiGenerationReadiness;
use App\Services\Product\ProductContentEligibilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductAiGenerationReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_preflight_is_bounded_and_existing_actionable_draft_is_excluded(): void
    {
        $this->readyRuntime();
        $brand = Brand::factory()->create(['name' => 'Readiness fixture']);
        $products = Product::factory()->count(20)->create(['brand_id' => $brand->id]);
        $conflicted = $products->first();
        $job = AiProductJob::create(['type' => 'fixture', 'scope' => 'selected', 'status' => 'needs_review', 'total' => 1]);
        AiProductDraft::create([
            'job_id' => $job->id,
            'product_id' => $conflicted->id,
            'normalized_output_json' => ['content_html' => '<p>Draft</p>'],
            'status' => 'needs_review',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $ten = app(ProductAiGenerationReadiness::class)->resolveMany(
            $products->take(10)->pluck('id')->all(),
            [ProductContentEligibilityPolicy::LONG_DESCRIPTION],
        );
        $tenQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $twenty = app(ProductAiGenerationReadiness::class)->resolveMany(
            $products->pluck('id')->all(),
            [ProductContentEligibilityPolicy::LONG_DESCRIPTION],
        );
        $twentyQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(9, $ten['ready']);
        $this->assertSame(1, $ten['blocked']);
        $this->assertContains('ACTIVE_DRAFT_OR_APPLY_CONFLICT', array_column($ten['rows'][0]['mandatory_blockers'], 'code'));
        $this->assertSame(19, $twenty['ready']);
        $this->assertLessThanOrEqual($tenQueries + 3, $twentyQueries, 'Query count must not grow with Product count.');
    }

    public function test_provider_not_configured_is_a_preflight_block_without_creating_job(): void
    {
        $monitor = $this->mock(AIQueueMonitor::class);
        $monitor->shouldReceive('liveStatusHealth')->andReturn([
            'worker_desired_state' => 'ENABLED',
            'worker_heartbeat' => ['health_status' => 'ONLINE', 'accepting_new_jobs' => true],
        ]);
        $this->app->forgetInstance(AIWorkerReadinessService::class);
        $brand = Brand::factory()->create();
        $product = Product::factory()->create(['brand_id' => $brand->id]);

        $before = AiProductJob::count();
        $result = app(ProductAiGenerationReadiness::class)->resolveMany(
            [$product->id],
            [ProductContentEligibilityPolicy::LONG_DESCRIPTION],
        );

        $this->assertSame(0, $result['ready']);
        $this->assertSame('PROVIDER_NOT_CONFIGURED', $result['rows'][0]['mandatory_blockers'][0]['code']);
        $this->assertSame($before, AiProductJob::count());
    }

    private function readyRuntime(): void
    {
        $monitor = $this->mock(AIQueueMonitor::class);
        $monitor->shouldReceive('liveStatusHealth')->andReturn([
            'worker_desired_state' => 'ENABLED',
            'worker_heartbeat' => ['health_status' => 'ONLINE', 'accepting_new_jobs' => true],
        ]);
        $this->app->forgetInstance(AIWorkerReadinessService::class);
        AiProvider::create([
            'provider' => 'custom',
            'name' => 'Readiness provider',
            'model' => 'fixture-model',
            'api_key' => 'fixture-only',
            'status' => 'active',
            'priority' => 'primary',
            'weight' => 1,
        ]);
    }
}
