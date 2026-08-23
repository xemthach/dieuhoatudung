<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\AI\AIContentGovernance;
use App\Services\AI\AIManager;
use App\Services\Product\AIProductContentSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class Phase2B2IsolatedRerunTest extends TestCase
{
    use RefreshDatabase;

    public function test_gcc42_semantic_precedence_is_proven_on_isolated_database(): void
    {
        $this->assertSemantic(42000, 42650, 'nhóm công suất 42.000 BTU', 'verified', false);
        $this->assertSemantic(42000, 42650, 'công suất danh định 42.000 BTU', 'blocked', true);
        $this->assertSemantic(42000, 42650, 'công suất kỹ thuật 42.650 BTU', 'verified', false);
    }

    public function test_gdc24_and_gud50_keep_distinct_capacity_semantics(): void
    {
        $this->assertSemantic(24000, 24200, 'dòng máy 24.000 BTU', 'verified', false);
        $this->assertSemantic(24000, 24200, 'công suất kỹ thuật 24.200 BTU', 'verified', false);
        $this->assertSemantic(18000, 16400, 'nhóm công suất thương mại 18.000 BTU', 'verified', false);
        $this->assertSemantic(18000, 16400, 'công suất thực 16.400 BTU', 'verified', false);
    }

    public function test_content_only_draft_retry_does_not_mutate_product_content_or_successful_fields(): void
    {
        $product = Product::factory()->create([
            'marketing_capacity_btu' => 24000,
            'technical_capacity_btu' => 24200,
            'technical_capacity_status' => 'verified_candidate',
            'short_description' => 'existing excerpt',
            'long_description' => 'existing content',
            'seo_title' => 'existing seo',
            'og_title' => 'existing og',
            'merchant_title' => 'existing merchant',
        ]);
        $before = $product->only(['short_description', 'long_description', 'seo_title', 'og_title', 'merchant_title']);
        $payload = [
            'product_id' => $product->id,
            'content_html' => '<h2>Giải pháp HVAC</h2><h3>Ứng dụng</h3>'.str_repeat('Nội dung kỹ thuật được xác minh và cần khảo sát thực tế. ', 180),
            'warnings' => [], 'blocked_claims' => [], 'used_verified_facts' => [],
        ];
        $manager = Mockery::mock(AIManager::class);
        $manager->shouldReceive('generate')->once()->andReturn(['json' => $payload, 'tokens_used' => 100, 'provider' => 'fake', 'model' => 'isolated-test']);
        $system = new AIProductContentSystem($manager, app(\App\Services\Product\AIProductSeoScorer::class), app(\App\Services\Product\AIProductContentSanitizer::class));
        $job = \App\Models\AiProductJob::create(['type' => 'phase2b2_isolated', 'scope' => 'selected', 'status' => 'queued', 'total' => 1, 'config_json' => [
            'action' => 'retry_ai_product_field', 'apply_mode' => 'draft_only', 'outputs' => ['content' => true, 'seo' => false, 'merchant' => false, 'faq' => false, 'tags' => false, 'internal_links' => false, 'og' => false,
        ]]]);
        $item = $job->items()->create(['product_id' => $product->id, 'status' => 'processing']);

        $system->generate($product, $job->config_json, $job, $item);
        $after = $product->refresh()->only(array_keys($before));

        $this->assertSame($before, $after);
        $this->assertNotNull($item->refresh()->draft_id);
    }

    private function assertSemantic(int $marketing, int $technical, string $text, string $status, bool $contradicted): void
    {
        $product = Product::factory()->create([
            'marketing_capacity_btu' => $marketing,
            'technical_capacity_btu' => $technical,
            'technical_capacity_status' => 'verified_candidate',
        ]);
        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText($text, $governance->buildProductContext($product));
        $this->assertSame($status, $result['status']);
        $this->assertSame($contradicted, collect($result['blocked_claims'])->contains(fn ($claim) => str_starts_with($claim, 'contradicted_technical_capacity:')));
    }
}
