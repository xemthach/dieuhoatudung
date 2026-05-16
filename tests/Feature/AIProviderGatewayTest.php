<?php

namespace Tests\Feature;

use App\Enums\AIContentJobStatus;
use App\Jobs\GenerateBlogDraftJob;
use App\Models\AiContentJob;
use App\Models\AiProvider;
use App\Services\AI\Adapters\ClaudeAdapter;
use App\Services\AI\Adapters\GeminiAdapter;
use App\Services\AI\Adapters\OpenAIAdapter;
use App\Services\AI\AIManager;
use App\Services\AI\AIProviderPool;
use App\Services\AI\HVACSeoContentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AIProviderGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_adapter_accepts_shopaikey_base_url(): void
    {
        Http::fake([
            'https://api.shopaikey.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"ok":true}']],
                ],
                'usage' => ['total_tokens' => 12],
            ]),
        ]);

        $provider = new AiProvider([
            'provider' => 'custom',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'gpt-test',
            'supports_json_mode' => true,
        ]);

        $result = (new OpenAIAdapter)->generate($provider, ['prompt' => 'Ping'], ['require_json' => true]);

        $this->assertSame(['ok' => true], $result['json']);
        $this->assertSame(12, $result['tokens_used']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.shopaikey.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test'));
    }

    public function test_openai_adapter_strips_json_markdown_fence(): void
    {
        Http::fake([
            'https://api.shopaikey.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => "```json\n{\"ok\":true,\"message\":\"Điều hòa\"}\n```"]],
                ],
                'usage' => ['total_tokens' => 9],
            ]),
        ]);

        $provider = new AiProvider([
            'provider' => 'custom',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'gpt-test',
            'supports_json_mode' => true,
        ]);

        $result = (new OpenAIAdapter)->generate($provider, ['prompt' => 'Ping'], ['require_json' => true]);

        $this->assertSame(['ok' => true, 'message' => 'Điều hòa'], $result['json']);
    }

    public function test_ai_manager_retries_invalid_json_response(): void
    {
        Http::fakeSequence('https://api.shopaikey.com/v1/chat/completions')
            ->push([
                'choices' => [
                    ['message' => ['content' => 'not json']],
                ],
                'usage' => ['total_tokens' => 1],
            ])
            ->push([
                'choices' => [
                    ['message' => ['content' => '{"ok":true}']],
                ],
                'usage' => ['total_tokens' => 2],
            ]);

        $provider = AiProvider::create([
            'provider' => 'custom',
            'name' => 'Retry JSON',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'gpt-test',
            'priority' => 'primary',
            'status' => 'active',
            'supports_json_mode' => true,
        ]);

        $result = app(AIManager::class)->generate(
            ['prompt' => 'Ping'],
            ['require_json' => true, 'max_attempts' => 2, 'task_type' => 'test_json_retry']
        );

        $this->assertSame(['ok' => true], $result['json']);
        $this->assertSame(2, $provider->refresh()->request_count);
    }

    public function test_gemini_adapter_uses_bearer_auth_for_shopaikey_base_url(): void
    {
        Http::fake([
            'https://api.shopaikey.com/v1beta/models/gemini-test:generateContent' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '{"ok":true}']]]],
                ],
                'usageMetadata' => ['totalTokenCount' => 7],
            ]),
        ]);

        $provider = new AiProvider([
            'provider' => 'gemini',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'gemini-test',
        ]);

        $result = (new GeminiAdapter)->generate($provider, ['prompt' => 'Ping'], ['require_json' => true]);

        $this->assertSame(['ok' => true], $result['json']);
        $this->assertSame(7, $result['tokens_used']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.shopaikey.com/v1beta/models/gemini-test:generateContent'
            && $request->hasHeader('Authorization', 'Bearer sk-test'));
    }

    public function test_claude_adapter_accepts_shopaikey_base_url(): void
    {
        Http::fake([
            'https://api.shopaikey.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"ok":true}'],
                ],
                'usage' => ['input_tokens' => 3, 'output_tokens' => 4],
            ]),
        ]);

        $provider = new AiProvider([
            'provider' => 'claude',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'claude-test',
        ]);

        $result = (new ClaudeAdapter)->generate($provider, ['prompt' => 'Ping'], ['require_json' => true]);

        $this->assertSame(['ok' => true], $result['json']);
        $this->assertSame(7, $result['tokens_used']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.shopaikey.com/v1/messages'
            && $request->hasHeader('Authorization', 'Bearer sk-test'));
    }

    public function test_provider_pool_resets_stale_usage_windows(): void
    {
        $provider = AiProvider::create([
            'provider' => 'custom',
            'name' => 'Limited',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'gpt-test',
            'priority' => 'primary',
            'status' => 'active',
            'daily_limit' => 1,
            'daily_used' => 1,
            'minute_limit' => 1,
            'minute_used' => 1,
            'last_used_at' => now()->subDay(),
        ]);

        $available = app(AIProviderPool::class)->getAvailableProviders('primary');

        $this->assertTrue($available->contains('id', $provider->id));
        $provider->refresh();
        $this->assertSame(0, $provider->daily_used);
        $this->assertSame(0, $provider->minute_used);
    }

    public function test_provider_pool_reactivates_expired_rate_limited_provider(): void
    {
        $provider = AiProvider::create([
            'provider' => 'custom',
            'name' => 'Rate Limited',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'gpt-test',
            'priority' => 'primary',
            'status' => 'rate_limited',
            'rate_limited_until' => now()->subSecond(),
        ]);

        $available = app(AIProviderPool::class)->getAvailableProviders('primary');

        $this->assertTrue($available->contains('id', $provider->id));
        $provider->refresh();
        $this->assertSame('active', $provider->status);
        $this->assertNull($provider->rate_limited_until);
    }

    public function test_processing_blog_job_is_not_skipped(): void
    {
        AiProvider::create([
            'provider' => 'custom',
            'name' => 'Active',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'gpt-test',
            'priority' => 'primary',
            'status' => 'active',
        ]);

        $contentJob = AiContentJob::create([
            'topic' => 'Dieu hoa tu dung test',
            'primary_keyword' => 'Dieu hoa tu dung test',
            'intent' => 'informational',
            'status' => AIContentJobStatus::Processing,
        ]);

        $aiManager = Mockery::mock(AIManager::class);
        $aiManager->shouldReceive('generate')->once()->andReturn([
            'json' => $this->validHvacOutput(),
        ]);

        (new GenerateBlogDraftJob($contentJob->id))->handle($aiManager, app(HVACSeoContentEngine::class));

        $contentJob->refresh();
        $this->assertContains($contentJob->status, [
            AIContentJobStatus::CompletedVerified,
            AIContentJobStatus::CompletedWithWarnings,
        ]);
        $this->assertSame($this->validHvacOutput()['content'], $contentJob->output_draft);
        $this->assertSame('huong-dan-chon-dieu-hoa-tu-dung-cho-nha-xuong', $contentJob->output_meta['slug']);
        $this->assertNotEmpty($contentJob->output_faq);
        $this->assertNotEmpty($contentJob->output_internal_links);
    }

    public function test_blog_ai_e2e_via_provider_generates_utf8_content(): void
    {
        Http::fake([
            'https://api.shopaikey.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($this->validHvacOutput(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                ],
                'usage' => ['total_tokens' => 200],
            ]),
        ]);

        AiProvider::create([
            'provider' => 'custom',
            'name' => 'Blog E2E',
            'api_key' => 'sk-test',
            'endpoint' => 'https://api.shopaikey.com',
            'model' => 'gpt-test',
            'priority' => 'primary',
            'status' => 'active',
            'supports_json_mode' => true,
        ]);
        $contentJob = AiContentJob::create([
            'topic' => 'Hướng dẫn chọn điều hòa tủ đứng cho nhà xưởng',
            'primary_keyword' => 'điều hòa tủ đứng nhà xưởng',
            'intent' => 'informational',
            'status' => AIContentJobStatus::Queued,
        ]);

        (new GenerateBlogDraftJob($contentJob->id))->handle(app(AIManager::class), app(HVACSeoContentEngine::class));

        $contentJob->refresh();
        $this->assertContains($contentJob->status, [
            AIContentJobStatus::CompletedVerified,
            AIContentJobStatus::CompletedWithWarnings,
        ]);
        $this->assertStringContainsString('điều hòa', mb_strtolower($contentJob->output_draft, 'UTF-8'));
        $this->assertStringNotContainsString('BTUCalculatorService', $contentJob->output_draft);
        $this->assertStringNotContainsString('product.capacity_btu', $contentJob->output_draft);
    }

    public function test_hvac_engine_generates_prompt_for_missing_topic_and_keyword(): void
    {
        $contentJob = AiContentJob::create([
            'topic' => 'AI tự tạo topic - Giải pháp',
            'primary_keyword' => null,
            'intent' => null,
            'status' => AIContentJobStatus::Pending,
            'input_payload' => [
                'category' => 'Giải pháp',
                'audience' => 'nhà xưởng',
                'bulk_index' => 1,
                'bulk_total' => 10,
            ],
        ]);

        $aiManager = Mockery::mock(AIManager::class);
        $aiManager->shouldReceive('generate')
            ->once()
            ->withArgs(function ($payload, $options) {
                return str_contains($payload['prompt'], 'category: Giải pháp')
                    && str_contains($payload['prompt'], 'đối tượng: nhà xưởng')
                    && str_contains($payload['prompt'], 'Bulk mode: bài 1/10')
                    && $options['task_type'] === 'hvac_blog_content'
                    && $options['require_json'] === true;
            })
            ->andReturn(['json' => $this->validHvacOutput()]);

        $output = app(HVACSeoContentEngine::class)->generate($aiManager, $contentJob, 'ctx-test');

        $this->assertSame('Hướng dẫn chọn điều hòa tủ đứng cho nhà xưởng', $output['title']);
        $this->assertSame('/dieu-hoa-tu-dung', $output['internal_links'][1]['url']);
    }

    private function validHvacOutput(): array
    {
        return [
            'title' => 'Hướng dẫn chọn điều hòa tủ đứng cho nhà xưởng',
            'slug' => 'huong-dan-chon-dieu-hoa-tu-dung-cho-nha-xuong',
            'excerpt' => 'Cách chọn điều hòa tủ đứng cho nhà xưởng theo diện tích, tải nhiệt và nhu cầu vận hành thực tế.',
            'content' => $this->validHvacContent(),
            'seo_title' => 'Chọn điều hòa tủ đứng cho nhà xưởng chuẩn HVAC',
            'meta_description' => 'Hướng dẫn chọn điều hòa tủ đứng cho nhà xưởng theo diện tích, BTU, tải nhiệt và nhu cầu vận hành thực tế.',
            'og_title' => 'Chọn điều hòa tủ đứng cho nhà xưởng',
            'og_description' => 'Gợi ý chọn hệ thống điều hòa phù hợp cho nhà xưởng.',
            'tags' => [
                ['name' => 'điều hòa tủ đứng', 'type' => 'topic'],
                ['name' => 'nhà xưởng', 'type' => 'use_case'],
            ],
            'faq' => [
                ['question' => 'Nhà xưởng có nên dùng điều hòa tủ đứng?', 'answer' => 'Có, nếu diện tích và tải nhiệt phù hợp sau khảo sát.'],
                ['question' => 'Xưởng cần chọn công suất thế nào?', 'answer' => 'Cần tính theo tải nhiệt, diện tích, chiều cao trần và điều kiện vận hành thực tế.'],
                ['question' => 'Có nên chọn inverter cho nhà xưởng?', 'answer' => 'Nên cân nhắc nếu vận hành nhiều giờ và cần giữ nhiệt ổn định.'],
            ],
            'internal_links' => [
                ['type' => 'related_post', 'anchor' => 'cách tính BTU', 'url' => '/blog/cach-tinh-btu'],
            ],
        ];
    }

    private function validHvacContent(): string
    {
        $paragraph = '<p>Với một khu vực sản xuất có tải nhiệt phức tạp, tải lạnh không chỉ đến từ diện tích sàn mà còn đến từ mái tôn, số người làm việc, motor máy móc, cửa xuất nhập hàng và thời gian mở cửa trong ngày. Không nên dùng một hệ số chung để chọn máy khi chưa có dữ liệu khảo sát; công trình thực tế cần đánh giá mái nóng, hướng nắng và lưu lượng gió tươi. Vì vậy, phương án công suất chỉ nên chốt sau khi đã có đủ input tải nhiệt và điều kiện vận hành.</p>';

        return '<h2>Tổng quan công trình</h2>'
            .'<p>Khi nhà xưởng bắt đầu nóng cục bộ ở khu vực công nhân đứng máy, lựa chọn điều hòa tủ đứng phải đi từ mặt bằng, tải nhiệt và cách phân phối gió thay vì chỉ nhìn vào công suất ghi trên catalogue. Bài viết này dùng bối cảnh xưởng có trần cao vừa phải và nhu cầu làm mát theo ca để phân tích.</p>'
            .str_repeat($paragraph, 4)
            .'<h2>Khi nào nên dùng</h2><h3>Điều kiện mặt bằng phù hợp</h3><ul><li>Xưởng cần làm mát nhanh theo vùng.</li><li>Không muốn can thiệp trần thạch cao như cassette.</li><li>Cần bảo trì dễ tiếp cận.</li></ul>'
            .str_repeat($paragraph, 3)
            .'<h2>So sánh giải pháp</h2><table><tr><th>Giải pháp</th><th>Phù hợp</th><th>Lưu ý</th></tr><tr><td>Tủ đứng</td><td>Khu vực mở, cần gió xa</td><td>Cần bố trí hướng thổi tránh thổi trực tiếp vào người</td></tr><tr><td>Cassette</td><td>Trần thấp, cần thẩm mỹ</td><td>Phụ thuộc kết cấu trần</td></tr><tr><td>Duct</td><td>Cần giấu máy và chia gió</td><td>Cần thiết kế ống gió</td></tr></table>'
            .str_repeat($paragraph, 3)
            .'<h2>Sai lầm thường gặp</h2><h3>Chọn máy chỉ theo diện tích</h3><ul><li>Bỏ qua tải nhiệt từ mái và máy móc.</li><li>Không tính thất thoát do cửa mở.</li><li>Đặt dàn lạnh ở vị trí gió bị cản.</li></ul>'
            .str_repeat($paragraph, 3)
            .'<h2>Gợi ý giải pháp thực tế</h2><p>Với xưởng có tải nhiệt vừa, nên khảo sát nhóm máy phù hợp theo kết quả tính tải lạnh trong hệ thống thay vì chọn theo cảm tính. Với xưởng lớn hơn, nên chia nhiều điểm thổi hoặc cân nhắc VRF/duct để nhiệt độ đồng đều hơn. Nếu cần một phương án dễ lắp, dễ bảo trì, điều hòa tủ đứng Gree inverter là nhóm sản phẩm đáng đưa vào danh sách so sánh.</p>'
            .str_repeat($paragraph, 2)
            .'<p>Đội kỹ thuật nên đo kích thước, hướng nắng, số người và thiết bị sinh nhiệt trước khi chốt model. Khi đã có mặt bằng, việc chọn công suất sẽ chính xác hơn và tránh tình trạng máy chạy liên tục nhưng khu vực làm việc vẫn nóng.</p>';
    }
}
