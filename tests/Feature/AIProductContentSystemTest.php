<?php

namespace Tests\Feature;

use App\Jobs\AiProductContentBatchJob;
use App\Jobs\AiProductContentSingleJob;
use App\Models\AiProductContentVersion;
use App\Models\AiProductJob;
use App\Models\Brand;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AI\AIManager;
use App\Services\Product\AIProductContentSanitizer;
use App\Services\Product\AIProductContentSystem;
use App\Services\Product\AIProductSeoScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class AIProductContentSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_one_product_with_full_data(): void
    {
        $product = $this->product(['btu' => 42000, 'specs_json' => [['key' => 'airflow', 'value' => '1800 m3/h']]]);
        $service = $this->serviceReturning($this->validPayload());

        $service->generate($product, $this->config(['apply_mode' => 'auto_apply']));

        $product->refresh();
        $this->assertContains($product->ai_status, ['completed_verified', 'completed_with_warnings']);
        $this->assertGreaterThanOrEqual(70, $product->ai_score);
        $this->assertNotEmpty($product->long_description);
        $this->assertSame('Merchant title Gree Cassette 42000BTU', $product->merchant_title);
        $this->assertSame(3, $product->faqs()->count());
        $this->assertSame(3, $product->tags()->count());
    }

    public function test_generate_one_product_missing_technical_specs_allows_shorter_content_with_warning(): void
    {
        $product = $this->product([
            'name' => 'Điều hòa âm trần Gree Cassette',
            'btu' => null,
            'capacity_kw' => null,
            'hp' => null,
            'model_code' => null,
            'specs_json' => null,
        ]);
        $payload = $this->payloadWithoutVerifiedSpecs(contentWords: 260, warnings: ['missing_technical_data']);

        $service = $this->serviceReturning($payload);
        $service->generate($product, $this->config(['apply_mode' => 'auto_apply']));

        $product->refresh();
        $this->assertGreaterThan(0, $product->ai_warning_count);
        $this->assertNotSame('failed', $product->ai_status);
    }

    public function test_bulk_10_gree_cassette_products_creates_items_and_single_jobs(): void
    {
        Bus::fake();
        $brand = Brand::factory()->create(['name' => 'GREE']);
        $category = ProductCategory::factory()->create(['name' => 'Điều hòa âm trần Cassette']);
        $products = Product::factory()->count(10)->create(['brand_id' => $brand->id, 'product_category_id' => $category->id]);
        $job = AiProductJob::create(['type' => 'generate_ai_content', 'scope' => 'selected', 'status' => 'queued', 'total' => 10, 'config_json' => $this->config()]);

        (new AiProductContentBatchJob($job->id, $products->pluck('id')->all()))->handle();

        $this->assertSame(10, $job->items()->count());
        $this->assertSame(10, Product::whereKey($products->pluck('id'))->where('ai_status', 'queued')->count());
        Bus::assertDispatched(AiProductContentSingleJob::class, 10);
    }

    public function test_bulk_20_mix_category_products_creates_items(): void
    {
        Bus::fake();
        $cassette = ProductCategory::factory()->create(['name' => 'Cassette']);
        $duct = ProductCategory::factory()->create(['name' => 'Ống gió Duct']);
        $products = collect()
            ->merge(Product::factory()->count(10)->create(['product_category_id' => $cassette->id]))
            ->merge(Product::factory()->count(10)->create(['product_category_id' => $duct->id]));
        $job = AiProductJob::create(['type' => 'generate_ai_content', 'scope' => 'selected', 'status' => 'queued', 'total' => 20, 'config_json' => $this->config(['batch_size' => 20])]);

        (new AiProductContentBatchJob($job->id, $products->pluck('id')->all()))->handle();

        $this->assertSame(20, $job->items()->count());
        Bus::assertDispatched(AiProductContentSingleJob::class, 20);
    }

    public function test_rewrite_weak_content_only_skips_strong_product(): void
    {
        $product = $this->productWithStrongContent();
        $aiManager = Mockery::mock(AIManager::class);
        $aiManager->shouldReceive('generate')->never();

        $service = new AIProductContentSystem($aiManager, app(AIProductSeoScorer::class), app(AIProductContentSanitizer::class));
        $result = $service->generate($product, $this->config(['mode' => 'rewrite_weak', 'apply_mode' => 'auto_apply']));

        $this->assertSame('completed_with_warnings', $result['status']);
        $this->assertGreaterThan(0, $product->refresh()->ai_warning_count);
    }

    public function test_generate_missing_only_does_not_overwrite_existing_seo(): void
    {
        $product = $this->product([
            'seo_title' => 'Existing SEO title with enough characters for product page',
            'seo_description' => str_repeat('Existing meta description ', 7),
        ]);
        $service = $this->serviceReturning($this->validPayload());

        $service->generate($product, $this->config(['mode' => 'missing_only', 'apply_mode' => 'auto_apply']));

        $product->refresh();
        $this->assertSame('Existing SEO title with enough characters for product page', $product->seo_title);
        $this->assertStringStartsWith('Existing meta description', $product->seo_description);
        $this->assertNotEmpty($product->long_description);
    }

    public function test_force_overwrite_and_rollback_restores_previous_content(): void
    {
        $product = $this->product([
            'short_description' => 'Old excerpt',
            'long_description' => '<h2>Old</h2><p>Old content</p>',
            'seo_title' => 'Old SEO title',
        ]);
        $service = $this->serviceReturning($this->validPayload());

        $service->generate($product, $this->config(['mode' => 'force_overwrite', 'apply_mode' => 'auto_apply']));
        $this->assertSame(1, AiProductContentVersion::where('product_id', $product->id)->count());
        $this->assertNotSame('Old excerpt', $product->refresh()->short_description);

        $service->rollback($product);

        $product->refresh();
        $this->assertSame('Old excerpt', $product->short_description);
        $this->assertSame('<h2>Old</h2><p>Old content</p>', $product->long_description);
    }

    public function test_rate_limit_simulation_releases_job_for_retry(): void
    {
        $product = $this->product();
        $job = AiProductJob::create(['type' => 'generate_ai_content', 'scope' => 'selected', 'status' => 'queued', 'total' => 1, 'config_json' => $this->config()]);
        $item = $job->items()->create(['product_id' => $product->id, 'status' => 'queued']);
        $aiManager = Mockery::mock(AIManager::class);
        $aiManager->shouldReceive('generate')->once()->andThrow(new \RuntimeException(json_encode(['is_rate_limit' => true, 'message' => '429'])));
        $this->app->instance(AIManager::class, $aiManager);

        (new AiProductContentSingleJob($product->id, $job->id, $item->id))->handle(app(AIProductContentSystem::class));

        $this->assertSame('queued', $item->refresh()->status);
        $this->assertSame('queued', $product->refresh()->ai_status);
    }

    public function test_invalid_ai_json_response_marks_item_failed(): void
    {
        $product = $this->product();
        $job = AiProductJob::create(['type' => 'generate_ai_content', 'scope' => 'selected', 'status' => 'queued', 'total' => 1, 'config_json' => $this->config()]);
        $item = $job->items()->create(['product_id' => $product->id, 'status' => 'queued']);
        $this->app->instance(AIManager::class, $this->aiManagerReturning([]));

        (new AiProductContentSingleJob($product->id, $job->id, $item->id))->handle(app(AIProductContentSystem::class));

        $this->assertSame('failed', $item->refresh()->status);
        $this->assertStringContainsString('thiếu content_html', $item->error_message);
        $this->assertSame('failed', $product->refresh()->ai_status);
    }

    public function test_xss_content_validation_strips_unsafe_html(): void
    {
        $sanitizer = app(AIProductContentSanitizer::class);
        $payload = $sanitizer->sanitizePayload($this->validPayload(content: '<h2 onclick="bad()">Title</h2><p><script>alert(1)</script><a href="javascript:alert(1)">link</a></p><h3>Ứng dụng</h3>'));

        $this->assertStringNotContainsString('script', $payload['content_html']);
        $this->assertStringNotContainsString('onclick', $payload['content_html']);
        $this->assertStringNotContainsString('javascript:', $payload['content_html']);
    }

    public function test_internal_code_language_is_not_allowed_in_ai_payload(): void
    {
        $this->expectException(\RuntimeException::class);

        app(AIProductContentSanitizer::class)->sanitizePayload($this->validPayload(
            content: '<h2>Cong suat</h2><p>Cong suat duoc tinh bang BadController.</p><h3>Ung dung</h3>'
        ));
    }

    public function test_product_payload_gets_utf8_and_vietnamese_warnings(): void
    {
        $payload = app(AIProductContentSanitizer::class)->sanitizePayload($this->validPayload());

        $this->assertContains('encoding_checked', $payload['warnings']);
        $this->assertContains('vietnamese_verified', $payload['warnings']);
        $this->assertContains('gree', $payload['tags']);
        $this->assertContains('42000btu', $payload['tags']);
    }

    public function test_product_payload_normalizes_technical_tags_without_rejecting_them(): void
    {
        $payload = $this->validPayload();
        $payload['tags'] = ['GREE', 'R410A', 'GCC42S6I/GMC42S6I', '42.000 BTU', 'Äiá»u hÃ²a Ã¢m tráº§n'];

        $payload = app(AIProductContentSanitizer::class)->sanitizePayload($payload);

        $this->assertContains('gree', $payload['tags']);
        $this->assertContains('r410a', $payload['tags']);
        $this->assertContains('gcc42s6i-gmc42s6i', $payload['tags']);
        $this->assertContains('42000btu', $payload['tags']);
    }

    public function test_unaccented_vietnamese_product_content_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tiếng Việt không dấu');

        app(AIProductContentSanitizer::class)->sanitizePayload($this->validPayload(
            content: '<h2>Điều hòa âm trần</h2><p>Dieu hoa am tran co cong suat phu hop.</p><h3>Ứng dụng</h3>'
        ));
    }

    public function test_mojibake_product_content_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('lỗi mã hóa');

        app(AIProductContentSanitizer::class)->sanitizePayload($this->validPayload(
            content: '<h2>Giáº£i pháp</h2><p>Điều hòa âm trần cho showroom.</p><h3>Ứng dụng</h3>'
        ));
    }

    public function test_wrapped_product_content_output_shape_is_accepted(): void
    {
        $product = $this->product();
        $payload = [
            'product_id' => $product->id,
            'content' => $this->validPayload(),
            'warnings' => ['encoding_checked', 'vietnamese_verified'],
        ];
        $service = $this->serviceReturning($payload);

        $result = $service->generate($product, $this->config(['apply_mode' => 'needs_review']));

        $this->assertSame($product->id, $result['payload']['product_id']);
        $this->assertContains('encoding_checked', $result['payload']['warnings']);
        $this->assertContains('vietnamese_verified', $result['payload']['warnings']);
    }

    private function serviceReturning(array $payload): AIProductContentSystem
    {
        return new AIProductContentSystem($this->aiManagerReturning($payload), app(AIProductSeoScorer::class), app(AIProductContentSanitizer::class));
    }

    private function aiManagerReturning(array $payload): AIManager
    {
        $aiManager = Mockery::mock(AIManager::class);
        $aiManager->shouldReceive('generate')->andReturn([
            'json' => $payload,
            'content' => json_encode($payload),
            'tokens_used' => 123,
            'latency_ms' => 20,
            'provider' => 'custom',
            'model' => 'gpt-test',
        ]);

        return $aiManager;
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'Điều hòa âm trần Gree Cassette 42000BTU',
            'model_code' => 'GCC42S6I',
            'sku' => 'GREE-GCC42S6I',
            'btu' => 42000,
            'capacity_kw' => 12.3,
            'refrigerant_gas' => 'R32',
            'voltage' => '1 pha',
            'short_description' => null,
            'long_description' => null,
            'seo_title' => null,
            'seo_description' => null,
        ], $overrides));
    }

    private function productWithStrongContent(): Product
    {
        $product = $this->product([
            'short_description' => 'Sản phẩm Gree Cassette GCC42S6I phù hợp văn phòng, showroom và không gian thương mại.',
            'long_description' => $this->content(900),
            'seo_title' => 'Điều hòa âm trần Gree GCC42S6I 42000BTU cho công trình',
            'seo_description' => mb_substr(str_repeat('Điều hòa âm trần Gree GCC42S6I 42000BTU cho văn phòng, showroom, tiết kiệm điện và vận hành ổn định. ', 3), 0, 150),
            'og_title' => 'Điều hòa âm trần Gree GCC42S6I',
            'og_description' => 'Gree cassette 42000BTU cho công trình thương mại.',
            'merchant_title' => 'Gree GCC42S6I 42000BTU',
            'merchant_description' => 'Điều hòa âm trần Gree cassette cho công trình.',
            'google_product_category' => '604',
            'product_type' => 'Điều hòa > Cassette > Gree',
        ]);
        $product->tags()->create(['name' => 'Gree Cassette']);
        $faq = Faq::create(['question' => 'Có phù hợp showroom không?', 'answer' => 'Có.', 'group' => 'product', 'is_active' => true]);
        $product->faqs()->attach($faq->id, ['sort_order' => 1]);

        return $product->refresh()->load(['tags', 'faqs']);
    }

    private function config(array $overrides = []): array
    {
        return array_merge([
            'action' => 'generate_ai_content',
            'mode' => 'missing_only',
            'depth' => 'seo',
            'tone' => 'hvac_expert',
            'batch_size' => 10,
            'apply_mode' => 'needs_review',
            'outputs' => [
                'content' => true,
                'seo' => true,
                'merchant' => true,
                'tags' => true,
                'faq' => true,
                'internal_links' => true,
                'og' => true,
            ],
        ], $overrides);
    }

    private function validPayload(int $contentWords = 850, array $warnings = [], ?string $content = null): array
    {
        return [
            'excerpt' => 'Điều hòa âm trần Gree Cassette GCC42S6I 42000BTU phù hợp không gian thương mại cần phân phối gió đều.',
            'content_html' => $content ?? $this->content($contentWords),
            'seo_title' => 'Điều hòa âm trần Gree GCC42S6I 42000BTU cho công trình',
            'meta_description' => mb_substr(str_repeat('Điều hòa âm trần Gree GCC42S6I 42000BTU cho văn phòng, showroom, tiết kiệm điện và vận hành ổn định. ', 3), 0, 150),
            'og_title' => 'Điều hòa âm trần Gree GCC42S6I 42000BTU',
            'og_description' => 'Giải pháp cassette Gree 42000BTU cho không gian thương mại.',
            'merchant_title' => 'Merchant title Gree Cassette 42000BTU',
            'merchant_description' => 'Merchant description cho điều hòa âm trần Gree cassette GCC42S6I 42000BTU.',
            'tags' => ['Gree', 'cassette', '42000BTU'],
            'faq' => [
                ['question' => 'Máy phù hợp diện tích nào?', 'answer' => 'Cần khảo sát tải nhiệt, nhưng nhóm 42.000 BTU thường dùng cho khu vực thương mại vừa.'],
                ['question' => 'Có nên dùng cho showroom không?', 'answer' => 'Có, nếu trần giả đủ điều kiện lắp đặt và cần gió phân phối đều.'],
                ['question' => 'Cần lưu ý gì khi lắp?', 'answer' => 'Cần kiểm tra thoát nước ngưng, chiều cao trần và vị trí dàn nóng.'],
            ],
            'internal_links' => [
                ['type' => 'product', 'anchor' => 'Xem điều hòa cassette', 'url' => '/dieu-hoa-cassette'],
            ],
            'warnings' => $warnings,
        ];
    }

    private function payloadWithoutVerifiedSpecs(int $contentWords = 260, array $warnings = []): array
    {
        $payload = $this->validPayload($contentWords, $warnings, $this->contentWithoutVerifiedSpecs($contentWords));
        $payload['excerpt'] = 'Điều hòa âm trần Gree Cassette phù hợp không gian thương mại cần khảo sát tải nhiệt trước khi chọn công suất.';
        $payload['seo_title'] = 'Điều hòa âm trần Gree Cassette cho công trình';
        $payload['meta_description'] = 'Điều hòa âm trần Gree Cassette cho văn phòng, showroom và không gian thương mại cần khảo sát tải nhiệt thực tế.';
        $payload['og_title'] = 'Điều hòa âm trần Gree Cassette';
        $payload['og_description'] = 'Giải pháp cassette Gree cho không gian thương mại.';
        $payload['merchant_title'] = 'Gree Cassette';
        $payload['merchant_description'] = 'Điều hòa âm trần Gree cassette cho công trình thương mại.';
        $payload['tags'] = ['Gree', 'cassette'];
        $payload['faq'] = [
            ['question' => 'Có nên dùng cho showroom không?', 'answer' => 'Có thể cân nhắc nếu trần giả đủ điều kiện lắp đặt và đã khảo sát tải nhiệt.'],
            ['question' => 'Cần dữ liệu gì trước khi chọn máy?', 'answer' => 'Cần diện tích, chiều cao trần, hướng nắng, số người và thiết bị tỏa nhiệt.'],
            ['question' => 'Khi thiếu thông số kỹ thuật nên làm gì?', 'answer' => 'Nên kiểm tra catalogue hoặc liên hệ kỹ thuật trước khi chốt phương án.'],
        ];

        return $payload;
    }

    private function content(int $words): string
    {
        $sentence = 'Gree Cassette GCC42S6I 42000BTU dùng cho văn phòng showroom trần giả phân phối gió đều tiết kiệm điện và cần khảo sát tải nhiệt thực tế. ';

        return '<h2>Giới thiệu sản phẩm</h2><p>'.str_repeat($sentence, (int) ceil($words / 20)).'</p>'
            .'<h2>Điểm nổi bật kỹ thuật</h2><h3>Phân phối gió cassette</h3><ul><li>Thiết kế âm trần phù hợp không gian thương mại.</li><li>Cần kiểm tra đường thoát nước ngưng.</li></ul>'
            .'<h2>Khi nào nên dùng</h2><p>'.str_repeat($sentence, 4).'</p>'
            .'<h2>Lưu ý lắp đặt/vận hành</h2><h3>Khảo sát thực tế</h3><p>'.str_repeat($sentence, 4).'</p>';
    }

    private function contentWithoutVerifiedSpecs(int $words): string
    {
        $sentence = 'Gree Cassette dùng cho văn phòng showroom trần giả cần khảo sát tải nhiệt thực tế trước khi chọn cấu hình. ';

        return '<h2>Giới thiệu sản phẩm</h2><p>'.str_repeat($sentence, (int) ceil($words / 14)).'</p>'
            .'<h2>Điểm nổi bật kỹ thuật</h2><h3>Thiết kế âm trần</h3><ul><li>Phù hợp không gian có trần giả.</li><li>Cần kiểm tra đường thoát nước ngưng.</li></ul>'
            .'<h2>Khi nào nên dùng</h2><p>'.str_repeat($sentence, 4).'</p>'
            .'<h2>Lưu ý lắp đặt/vận hành</h2><h3>Khảo sát thực tế</h3><p>'.str_repeat($sentence, 4).'</p>';
    }
}
