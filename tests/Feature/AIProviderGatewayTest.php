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
        $this->assertSame(AIContentJobStatus::Completed, $contentJob->status);
        $this->assertSame($this->validHvacOutput()['content'], $contentJob->output_draft);
        $this->assertSame('huong-dan-chon-dieu-hoa-tu-dung-cho-nha-xuong', $contentJob->output_meta['slug']);
        $this->assertNotEmpty($contentJob->output_faq);
        $this->assertNotEmpty($contentJob->output_internal_links);
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
                ['question' => 'Xưởng 100m2 cần bao nhiêu BTU?', 'answer' => 'Cần tính theo tải nhiệt, nhưng có thể lấy 60.000 BTU là mốc tham khảo sơ bộ trước khi khảo sát.'],
                ['question' => 'Có nên chọn inverter cho nhà xưởng?', 'answer' => 'Nên cân nhắc nếu vận hành nhiều giờ và cần giữ nhiệt ổn định.'],
            ],
            'internal_links' => [
                ['type' => 'related_post', 'anchor' => 'cách tính BTU', 'url' => '/blog/cach-tinh-btu'],
            ],
        ];
    }

    private function validHvacContent(): string
    {
        $paragraph = '<p>Với một khu vực sản xuất khoảng 100m2, tải nhiệt không chỉ đến từ diện tích sàn mà còn đến từ mái tôn, số người làm việc, motor máy móc, cửa xuất nhập hàng và thời gian mở cửa trong ngày. Mốc 600 BTU/m2 chỉ nên xem là ước tính sơ bộ cho không gian thông thường; công trình thực tế cần cộng thêm hệ số cho mái nóng, hướng nắng và lưu lượng gió tươi. Vì vậy, một phương án 60.000 BTU có thể đủ cho khu vực ít máy móc, nhưng chưa chắc đủ cho xưởng có máy ép, máy nén khí hoặc cửa cuốn mở liên tục.</p>';

        return '<h2>Tổng quan công trình</h2>'
            .'<p>Khi nhà xưởng bắt đầu nóng cục bộ ở khu vực công nhân đứng máy, lựa chọn điều hòa tủ đứng phải đi từ mặt bằng, tải nhiệt và cách phân phối gió thay vì chỉ nhìn vào công suất ghi trên catalogue. Bài viết này dùng ví dụ xưởng 80-120m2, trần cao vừa phải và nhu cầu làm mát theo ca để phân tích.</p>'
            .str_repeat($paragraph, 4)
            .'<h2>Khi nào nên dùng</h2><h3>Điều kiện mặt bằng phù hợp</h3><ul><li>Xưởng cần làm mát nhanh theo vùng.</li><li>Không muốn can thiệp trần thạch cao như cassette.</li><li>Cần bảo trì dễ tiếp cận.</li></ul>'
            .str_repeat($paragraph, 3)
            .'<h2>So sánh giải pháp</h2><table><tr><th>Giải pháp</th><th>Phù hợp</th><th>Lưu ý</th></tr><tr><td>Tủ đứng</td><td>Khu vực mở, cần gió xa</td><td>Cần bố trí hướng thổi tránh thổi trực tiếp vào người</td></tr><tr><td>Cassette</td><td>Trần thấp, cần thẩm mỹ</td><td>Phụ thuộc kết cấu trần</td></tr><tr><td>Duct</td><td>Cần giấu máy và chia gió</td><td>Cần thiết kế ống gió</td></tr></table>'
            .str_repeat($paragraph, 3)
            .'<h2>Sai lầm thường gặp</h2><h3>Chọn máy chỉ theo diện tích</h3><ul><li>Bỏ qua tải nhiệt từ mái và máy móc.</li><li>Không tính thất thoát do cửa mở.</li><li>Đặt dàn lạnh ở vị trí gió bị cản.</li></ul>'
            .str_repeat($paragraph, 3)
            .'<h2>Gợi ý giải pháp thực tế</h2><p>Với xưởng khoảng 70m2 đến 90m2, có thể khảo sát nhóm máy 42.000 BTU đến 50.000 BTU tùy tải nhiệt. Với xưởng lớn hơn, nên chia nhiều điểm thổi hoặc cân nhắc VRF/duct để nhiệt độ đồng đều hơn. Nếu cần một phương án dễ lắp, dễ bảo trì, điều hòa tủ đứng Gree inverter là nhóm sản phẩm đáng đưa vào danh sách so sánh.</p>'
            .str_repeat($paragraph, 2)
            .'<p>Đội kỹ thuật nên đo kích thước, hướng nắng, số người và thiết bị sinh nhiệt trước khi chốt model. Khi đã có mặt bằng, việc chọn công suất sẽ chính xác hơn và tránh tình trạng máy chạy liên tục nhưng khu vực làm việc vẫn nóng.</p>';
    }
}
