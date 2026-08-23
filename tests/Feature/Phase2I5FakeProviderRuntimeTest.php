<?php

namespace Tests\Feature;

use App\Jobs\AiProductContentSingleJob;
use App\Models\AiProductJob;
use App\Models\AiProvider;
use App\Models\Product;
use App\Services\AI\AIManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class Phase2I5FakeProviderRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_fake_output_persists_draft_without_product_content_write(): void
    {
        AiProvider::create([
            'provider' => 'custom',
            'name' => 'Phase 2I5 fake',
            'model' => 'fake-contract',
            'priority' => 'primary',
            'status' => 'active',
        ]);
        $product = Product::factory()->create(['short_description' => null, 'long_description' => null, 'seo_title' => null]);
        $before = (array) DB::table('products')->where('id', $product->id)->first();
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'queued',
            'total' => 1,
            'config_json' => [
                'action' => 'generate_ai_content',
                'mode' => 'missing_only',
                'apply_mode' => 'draft_only',
                'draft_only_strict' => true,
                'outputs' => ['content' => true, 'seo' => false, 'merchant' => false, 'tags' => false, 'faq' => false, 'internal_links' => false, 'og' => false],
            ],
        ]);
        $item = $job->items()->create(['product_id' => $product->id, 'status' => 'queued']);

        (new AiProductContentSingleJob($product->id, $job->id, $item->id))->handle(app(\App\Services\Product\AIProductContentSystem::class));

        $this->assertSame('needs_review', $item->refresh()->status);
        $this->assertNotNull($item->draft_id);
        $after = (array) DB::table('products')->where('id', $product->id)->first();
        foreach (['short_description', 'long_description', 'seo_title', 'ai_status', 'ai_last_run_at', 'updated_at'] as $column) {
            $this->assertSame($before[$column] ?? null, $after[$column] ?? null, $column);
        }
    }

    public function test_fake_missing_structure_is_terminal_and_does_not_persist_draft(): void
    {
        $product = Product::factory()->create();
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'queued',
            'total' => 1,
            'config_json' => [
                'action' => 'generate_ai_content',
                'mode' => 'missing_only',
                'apply_mode' => 'draft_only',
                'draft_only_strict' => true,
                'outputs' => ['content' => true, 'seo' => false, 'merchant' => false, 'tags' => false, 'faq' => false, 'internal_links' => false, 'og' => false],
            ],
        ]);
        $item = $job->items()->create(['product_id' => $product->id, 'status' => 'queued']);
        $manager = Mockery::mock(AIManager::class);
        $manager->shouldReceive('generate')->once()->andReturn([
            'content' => '<h2>Title</h2><p>'.str_repeat('Nội dung kiểm thử an toàn. ', 120).'</p>',
            'json' => ['content_html' => '<h2>Title</h2><p>'.str_repeat('Nội dung kiểm thử an toàn. ', 120).'</p>', 'warnings' => [], 'blocked_claims' => []],
            'tokens_used' => 100,
            'latency_ms' => 1,
            'provider' => 'fake',
            'model' => 'fake-contract',
        ]);
        $this->app->instance(AIManager::class, $manager);

        (new AiProductContentSingleJob($product->id, $job->id, $item->id))->handle(app(\App\Services\Product\AIProductContentSystem::class));

        $this->assertSame('failed', $item->refresh()->status);
        $this->assertNull($item->draft_id);
        $this->assertSame('content_structure_failed', $item->last_error_code);
    }
}
