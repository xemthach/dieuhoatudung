<?php

namespace App\Services\AI;

use App\Models\AiProvider;
use App\Services\Settings\SettingService;
use App\Support\EncodingGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class AISeoContentGenerator
{
    public const TONES = [
        'hvac_expert' => 'Chuyên gia HVAC',
        'b2b_commercial' => 'Thương mại B2B',
        'google_seo' => 'SEO Google',
        'plain_technical' => 'Kỹ thuật dễ hiểu',
        'landing_short' => 'Ngắn gọn landing page',
    ];

    public function __construct(
        private readonly AIManager $aiManager,
        private readonly AIJsonResponseParser $parser,
        private readonly AITechnicalLogger $logger,
        private readonly SettingService $settings,
    ) {}

    public function generate(string $module, array $input, array $fields, string $tone = 'hvac_expert'): array
    {
        $fields = array_values(array_unique($fields));
        $title = trim((string) Arr::get($input, 'title', Arr::get($input, 'name', '')));

        if ($title === '') {
            throw new RuntimeException('missing_title_for_ai_generation');
        }

        $context = $this->buildVerifiedContext($module, $input, $tone, $fields);

        try {
            $generated = $this->hasActiveProvider()
                ? $this->generateWithProvider($module, $context)
                : $this->generateGovernedDraft($module, $context);

            $generated = $this->normalizeOutput($generated, $fields);
            $this->assertNoUnsupportedClaims($generated, $context);

            $this->logger->event('admin_ai_content', 'generated', 'Admin AI content generated.', [
                'module' => $module,
                'fields' => $fields,
                'tone' => $tone,
                'provider' => $generated['_provider'] ?? 'local_governed_draft',
            ]);

            return $generated;
        } catch (\Throwable $e) {
            $this->logger->exception('admin_ai_content', $e, null, [
                'module' => $module,
                'fields' => $fields,
                'title' => $title,
            ]);

            throw $e;
        }
    }

    private function generateWithProvider(string $module, array $context): array
    {
        $result = $this->aiManager->generate([
            'system' => $this->systemPrompt(),
            'prompt' => $this->buildPrompt($module, $context),
            'temperature' => 0.35,
        ], [
            'task_type' => 'admin_seo_content',
            'context_id' => 'admin-seo-'.$module.'-'.sha1(json_encode($context)),
            'require_json' => true,
            'max_tokens' => 3500,
        ]);

        $json = $result['json'] ?? [];
        if ($json === [] && is_string($result['content'] ?? null)) {
            $json = $this->parser->parse($result['content'], true);
        }

        $json['_provider'] = $result['provider'] ?? null;

        return $json;
    }

    private function buildVerifiedContext(string $module, array $input, string $tone, array $fields): array
    {
        return [
            'module' => $module,
            'title' => trim((string) Arr::get($input, 'title', Arr::get($input, 'name', ''))),
            'category_type' => Arr::get($input, 'category_type'),
            'placement' => Arr::get($input, 'placement'),
            'scope' => Arr::get($input, 'scope'),
            'tone' => self::TONES[$tone] ?? self::TONES['hvac_expert'],
            'fields' => $fields,
            'taxonomy' => [
                'business_domain' => 'HVAC / điều hòa',
                'primary_products' => ['điều hòa tủ đứng', 'điều hòa đặt sàn/áp trần', 'điều hòa âm trần', 'VRF/VRV'],
                'common_use_cases' => ['nhà xưởng', 'văn phòng', 'showroom', 'nhà hàng', 'không gian thương mại'],
            ],
            'business_settings' => [
                'site_name' => $this->settings->get('general.site_name', config('app.name')),
                'global_cta_text' => $this->settings->get('cta.global_cta_text', 'Nhận tư vấn'),
                'quote_cta_text' => $this->settings->get('cta.quote_cta_text', 'Yêu cầu báo giá'),
            ],
            'allowed_facts' => Arr::get($input, 'allowed_facts', []),
        ];
    }

    private function generateGovernedDraft(string $module, array $context): array
    {
        $title = $context['title'];
        $cta = $context['business_settings']['quote_cta_text'] ?: 'Yêu cầu báo giá';

        return match ($module) {
            'product_category' => [
                'short_description' => "{$title} phù hợp cho các không gian thương mại, văn phòng, showroom và công trình cần giải pháp HVAC linh hoạt, dễ tư vấn theo hiện trạng thực tế.",
                'detailed_content' => "<h2>Giới thiệu {$title}</h2><p>{$title} là nhóm giải pháp HVAC được quan tâm trong các công trình cần tối ưu không gian lắp đặt, luồng gió và tính thẩm mỹ. Nội dung tư vấn nên bắt đầu từ mặt bằng, tải nhiệt, mục đích sử dụng và yêu cầu vận hành.</p><h2>Ứng dụng thực tế</h2><ul><li>Không gian thương mại cần phân bổ gió ổn định.</li><li>Văn phòng, showroom, nhà hàng hoặc khu vực dịch vụ cần bố trí thiết bị gọn gàng.</li><li>Công trình cần đội kỹ thuật khảo sát trước khi chọn cấu hình.</li></ul><h2>Gợi ý lựa chọn</h2><p>Admin nên bổ sung dữ liệu sản phẩm, model và thông số đã xác minh trước khi công bố khuyến nghị chi tiết. {$cta} để được tư vấn theo mặt bằng thực tế.</p>",
                'seo_title' => "{$title} cho công trình HVAC",
                'meta_description' => "Tìm hiểu {$title}, ứng dụng thực tế, không gian phù hợp và gợi ý tư vấn HVAC an toàn, không bịa thông số kỹ thuật.",
                'og_title' => "{$title} | Giải pháp HVAC",
                'og_description' => "Giới thiệu {$title} cho công trình thương mại, văn phòng, showroom và nhu cầu tư vấn HVAC thực tế.",
            ],
            'brand' => [
                'brand_introduction' => "{$title} là thương hiệu được quản trị trong hệ thống danh mục HVAC. Nội dung giới thiệu nên tập trung vào phân khúc sản phẩm, ứng dụng điều hòa và dữ liệu đã được nội bộ xác minh.",
                'detailed_content' => "<h2>Giới thiệu thương hiệu {$title}</h2><p>{$title} được trình bày trong hệ thống theo hướng hỗ trợ khách hàng tìm nhóm sản phẩm điều hòa phù hợp với nhu cầu công trình.</p><h2>Ứng dụng HVAC</h2><p>Nội dung nên liên kết {$title} với các nhóm sản phẩm đang có dữ liệu thực tế, tránh nêu lịch sử, thị phần hoặc công nghệ độc quyền nếu chưa có nguồn nội bộ.</p><h2>Tư vấn lựa chọn</h2><p>{$cta} để được gợi ý sản phẩm phù hợp theo không gian, ngân sách và yêu cầu vận hành.</p>",
                'seo_title' => "{$title} HVAC - sản phẩm và tư vấn",
                'meta_description' => "Tổng quan thương hiệu {$title} trong danh mục HVAC, định hướng sản phẩm và tư vấn lựa chọn theo dữ liệu đã xác minh.",
                'og_title' => "{$title} | Thương hiệu HVAC",
                'og_description' => "Khám phá nhóm sản phẩm {$title} và nhận tư vấn điều hòa phù hợp cho công trình.",
            ],
            default => [
                'promotion_description' => "{$title} là nội dung chiến dịch khuyến mãi cần bám sát cấu hình đã nhập trong admin. Nội dung chỉ nêu điều kiện áp dụng, phạm vi và lời mời tư vấn khi các dữ liệu ưu đãi cụ thể chưa được cấu hình.",
                'cta_content' => "{$cta} để kiểm tra điều kiện áp dụng và sản phẩm phù hợp.",
                'banner_copy' => "{$title} - liên hệ tư vấn điều kiện áp dụng",
                'seo_title' => "{$title} | Chương trình HVAC",
                'meta_description' => "Thông tin {$title} cho sản phẩm HVAC. Nội dung khuyến mãi chỉ dùng dữ liệu đã cấu hình, không tự tạo giá hoặc ưu đãi.",
                'og_title' => "{$title} | Khuyến mãi HVAC",
                'og_description' => 'Xem thông tin chiến dịch và liên hệ tư vấn điều kiện áp dụng theo cấu hình admin.',
            ],
        };
    }

    private function normalizeOutput(array $output, array $fields): array
    {
        $aliases = [
            'short_description' => ['short_description', 'intro', 'excerpt', 'description'],
            'detailed_content' => ['detailed_content', 'content', 'content_html'],
            'brand_introduction' => ['brand_introduction', 'description', 'intro'],
            'promotion_description' => ['promotion_description', 'description', 'intro'],
            'meta_description' => ['meta_description', 'seo_description'],
            'seo_title' => ['seo_title'],
            'og_title' => ['og_title'],
            'og_description' => ['og_description'],
            'cta_content' => ['cta_content', 'cta', 'popup_text', 'announcement_bar'],
            'banner_copy' => ['banner_copy', 'headline', 'subtitle'],
        ];

        $normalized = [];
        foreach ($fields as $field) {
            foreach ($aliases[$field] ?? [$field] as $key) {
                if (filled($output[$key] ?? null)) {
                    $normalized[$field] = (string) $output[$key];
                    break;
                }
            }
        }

        EncodingGuard::assertCleanUtf8Array($normalized, 'admin AI generated content');

        return $normalized;
    }

    private function assertNoUnsupportedClaims(array $output, array $context): void
    {
        $text = Str::ascii(Str::lower(implode(' ', $output)));
        $blocked = [];

        $rules = [
            'discount_percent' => '/\b\d{1,3}\s*%/',
            'vat' => '/\bvat\b/',
            'free' => '/\b(mien phi|free)\b/',
            'co_cq' => '/\b(co\/cq|cq|chung nhan xuat xu)\b/',
            'warranty' => '/\b(bao hanh|warranty)\b/',
            'technical_capacity' => '/\b\d+\s*(btu|kw|hp|m2)\b/',
        ];

        foreach ($rules as $code => $pattern) {
            if (preg_match($pattern, $text) && blank($context['allowed_facts'][$code] ?? null)) {
                $blocked[] = $code;
            }
        }

        if ($blocked !== []) {
            throw new RuntimeException('unsupported_claims_detected: '.implode(', ', $blocked));
        }
    }

    private function hasActiveProvider(): bool
    {
        return Schema::hasTable('ai_providers')
            && AiProvider::query()->where('status', 'active')->exists();
    }

    private function systemPrompt(): string
    {
        return 'Bạn là AI Content Workflow Engineer cho website HVAC. Chỉ trả về JSON UTF-8 hợp lệ. Không bịa thông số, bảo hành, VAT, CO/CQ, giá, phần trăm giảm hoặc miễn phí nếu không có allowed_facts.';
    }

    private function buildPrompt(string $module, array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
MODULE: {$module}
VERIFIED_CONTEXT:
{$json}

Trả về JSON object với đúng các field được yêu cầu trong VERIFIED_CONTEXT.fields.
Yêu cầu chung:
- Tiếng Việt có dấu, UTF-8 sạch.
- Đúng search intent HVAC, tự nhiên, không nhồi keyword.
- CTA nhẹ, không hứa ưu đãi chưa có nguồn.
- Product category: có giới thiệu, ứng dụng thực tế, ưu điểm, không gian phù hợp, internal keyword HVAC.
- Brand: giới thiệu thương hiệu theo dữ liệu đã có, phân khúc, ứng dụng HVAC, thế mạnh ở mức tổng quan.
- Promotion: CTA, campaign copy, headline/subtitle/popup/announcement nếu được yêu cầu; tuyệt đối không tự tạo discount %, thời gian, giá, miễn phí hoặc VAT.
PROMPT;
    }
}
