<?php

namespace App\Services\AI;

use App\Models\AiContentJob;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class HVACSeoContentEngine
{
    public const CATEGORIES = [
        'Kiến thức HVAC',
        'So sánh',
        'Giải pháp',
        'Lỗi / sửa chữa',
    ];

    public const AUDIENCES = [
        'nhà xưởng',
        'văn phòng',
        'showroom',
        'dân dụng',
    ];

    private AIContentGovernance $governance;

    public function __construct(?AIContentGovernance $governance = null)
    {
        $this->governance = $governance ?? app(AIContentGovernance::class);
    }

    public function generate(AIManager $aiManager, AiContentJob $job, string $contextId): array
    {
        $input = $this->inputFor($job);
        $guardContext = $this->governance->buildBlogContext($job, $input);
        $prompt = $this->buildPrompt($input, $guardContext);
        $lastException = null;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = $aiManager->generate([
                'system' => $this->systemPrompt(),
                'prompt' => $prompt,
                'temperature' => $attempt === 1 ? 0.55 : 0.35,
            ], [
                'task_type' => 'hvac_blog_content',
                'context_id' => $contextId,
                'require_json' => true,
                'max_tokens' => 12000,
            ]);

            $json = $result['json'] ?? [];
            if (empty($json) && ! empty($result['content'])) {
                $json = json_decode($result['content'], true) ?: [];
            }

            try {
                $output = $this->normalizeOutput($json, $input);
                $factCheck = $this->governance->validatePayload($output, $guardContext, [
                    'title',
                    'excerpt',
                    'content',
                    'seo_title',
                    'meta_description',
                    'og_title',
                    'og_description',
                ]);

                $output['warnings'] = array_values(array_unique(array_merge($output['warnings'] ?? [], $factCheck['warnings'])));
                $output['blocked_claims'] = array_values(array_unique(array_merge($output['blocked_claims'] ?? [], $factCheck['blocked_claims'])));
                $output['used_facts'] = $factCheck['used_facts'];
                $output['fact_check'] = $factCheck;
                $output['governance_context'] = $this->governance->publicContext($guardContext);

                if ($output['blocked_claims'] !== []) {
                    throw new RuntimeException('AI output bi chan fact-check: '.implode(', ', $output['blocked_claims']));
                }

                return $output;
            } catch (RuntimeException $e) {
                $lastException = $e;

                if (! str_contains($e->getMessage(), 'AI output chưa đạt chuẩn') || $attempt === 5) {
                    break;
                }

                $prompt = $this->buildRetryPrompt($input, $guardContext, $json, $e->getMessage());
            }
        }

        throw $lastException ?? new RuntimeException('AI output không hợp lệ.');
    }

    public function inputFor(AiContentJob $job): array
    {
        $payload = is_array($job->input_payload) ? $job->input_payload : [];

        $product = $this->resolveProduct($payload);
        $brand = $this->resolveBrand($payload, $product);

        $category = trim((string) ($payload['category'] ?? $job->postCategory?->name ?? 'Kiến thức HVAC'));
        if ($category === '') {
            $category = 'Kiến thức HVAC';
        }

        $topic = trim((string) ($payload['topic'] ?? $job->topic ?? ''));
        if (Str::startsWith($topic, 'AI tự tạo topic')) {
            $topic = '';
        }
        $keyword = trim((string) ($job->primary_keyword ?? $payload['keyword'] ?? ''));
        $intent = trim((string) ($job->intent ?? $payload['intent'] ?? ''));

        return [
            'category' => $category,
            'topic' => $topic,
            'keyword' => $keyword,
            'intent' => $intent !== '' ? $intent : $this->inferIntent($topic, $category),
            'audience' => trim((string) ($payload['audience'] ?? '')),
            'product' => $product,
            'brand' => $brand,
            'bulk_index' => $payload['bulk_index'] ?? null,
            'bulk_total' => $payload['bulk_total'] ?? null,
        ];
    }

    public function normalizeOutput(array $json, array $input): array
    {
        $guardFields = [
            'used_facts' => is_array($json['used_facts'] ?? null) ? $json['used_facts'] : [],
            'warnings' => is_array($json['warnings'] ?? null) ? $json['warnings'] : [],
            'blocked_claims' => is_array($json['blocked_claims'] ?? null) ? $json['blocked_claims'] : [],
        ];
        if (is_array($json['content'] ?? null)) {
            $guardFields = [
                'used_facts' => $guardFields['used_facts'],
                'warnings' => $guardFields['warnings'],
                'blocked_claims' => $guardFields['blocked_claims'],
            ];
            $json = array_merge($json['content'], $guardFields);
        }

        $title = trim((string) Arr::get($json, 'title', ''));
        if ($title === '') {
            $title = $input['topic'] ?: $this->fallbackTopic($input);
        }

        $slug = trim((string) Arr::get($json, 'slug', ''));
        $slug = Str::slug($slug !== '' ? $slug : $title);

        $excerpt = trim((string) Arr::get($json, 'excerpt', ''));
        $content = trim((string) Arr::get($json, 'content', ''));

        if ($excerpt === '' || $content === '') {
            throw new RuntimeException('AI output thiếu excerpt hoặc content.');
        }

        $faq = $this->normalizeFaq(Arr::get($json, 'faq', []));
        $qualityIssues = $this->validateContentQuality($content, $faq);
        if ($qualityIssues !== []) {
            throw new RuntimeException('AI output chưa đạt chuẩn: '.implode('; ', $qualityIssues));
        }

        $seoTitle = trim((string) Arr::get($json, 'seo_title', '')) ?: Str::limit($title, 65, '');
        $metaDescription = trim((string) Arr::get($json, 'meta_description', '')) ?: Str::limit($excerpt, 160, '');
        $ogTitle = trim((string) Arr::get($json, 'og_title', '')) ?: $seoTitle;
        $ogDescription = trim((string) Arr::get($json, 'og_description', '')) ?: $metaDescription;

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'content' => $content,
            'seo_title' => $seoTitle,
            'meta_description' => $metaDescription,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'tags' => $this->normalizeTags(Arr::get($json, 'tags', []), $input),
            'faq' => $faq,
            'internal_links' => $this->normalizeLinks(Arr::get($json, 'internal_links', []), $input),
            'used_facts' => $guardFields['used_facts'],
            'warnings' => $guardFields['warnings'],
            'blocked_claims' => $guardFields['blocked_claims'],
        ];
    }

    public function buildPrompt(array $input, array $guardContext = []): string
    {
        $guardJson = json_encode($this->governance->publicContext($guardContext), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $product = $input['product'];
        $brand = $input['brand'];
        $topic = $input['topic'] !== '' ? $input['topic'] : '[AI tự tạo topic theo category]';
        $keyword = $input['keyword'] !== '' ? $input['keyword'] : '[AI tự tạo keyword SEO hợp lý]';
        $audience = $input['audience'] !== '' ? $input['audience'] : '[AI suy luận theo topic/category]';

        $productBlock = $product ? json_encode([
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand?->name,
            'category' => $product->category?->name,
            'model_code' => $product->model_code ?? null,
            'btu' => $product->btu ?? null,
            'capacity_kw' => $product->capacity_kw ?? null,
            'hp' => $product->hp ?? null,
            'inverter' => $product->inverter ?? null,
            'refrigerant_gas' => $product->refrigerant_gas ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null';

        $brandBlock = $brand ? json_encode([
            'name' => $brand->name,
            'slug' => $brand->slug ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null';

        $bulkHint = '';
        if ($input['bulk_index'] && $input['bulk_total']) {
            $bulkHint = "\nBulk mode: bài {$input['bulk_index']}/{$input['bulk_total']}. Tạo góc nội dung riêng, tránh trùng lặp với các bài cùng batch.";
        }

        return <<<PROMPT
NGỮ CẢNH DỮ LIỆU ĐÃ XÁC MINH (bắt buộc tuân thủ):
{$guardJson}

INPUT:
- category: {$input['category']}
- topic: {$topic}
- keyword chính: {$keyword}
- search intent: {$input['intent']}
- đối tượng: {$audience}
- product liên quan: {$productBlock}
- brand liên quan: {$brandBlock}{$bulkHint}

Yêu cầu output:
Trả về DUY NHẤT một JSON object hợp lệ, không markdown, không giải thích ngoài JSON:
{
  "title": "",
  "slug": "",
  "excerpt": "",
  "content": "",
  "seo_title": "",
  "meta_description": "",
  "og_title": "",
  "og_description": "",
  "tags": [],
  "faq": [],
  "internal_links": [],
  "used_facts": [],
  "warnings": [],
  "blocked_claims": []
}

AI governance rule:
- Chỉ được dùng dữ liệu đã xác minh để đưa thông số kỹ thuật, BTU, kW, HP, m2, độ ồn, lưu lượng gió, kích thước, giá, bảo hành, VAT, CO/CQ.
- Không tự sinh công thức BTU, hệ số BTU/m2, diện tích phù hợp hoặc kết quả tải lạnh. Nếu chưa có kết quả tính toán đã xác minh thì chỉ nói nguyên lý và thêm warning "missing_btu_inputs".
- Không đưa công thức kinh nghiệm hoặc ví dụ công suất cụ thể nếu không có kết quả đã xác minh trong dữ liệu đầu vào.
- Mọi dữ liệu đã dùng phải ghi bằng tên dễ hiểu cho người quản trị, không dùng tên biến nội bộ. Nếu phát hiện claim không có nguồn, đưa mã vào blocked_claims.
- Tuyệt đối không đưa tên service, class, function, API, biến nội bộ, CamelCase hoặc cú pháp code vào các trường nội dung hiển thị.
- Toàn bộ nội dung hiển thị phải là tiếng Việt có dấu, UTF-8 sạch; không trả về tiếng Việt không dấu, ký tự lỗi hoặc text vỡ dấu.

Ràng buộc nội dung:
- content là HTML sạch, có h2/h3, p, ul/li, table nếu là bài so sánh.
- Độ dài tối thiểu 1000 từ, mục tiêu 1400-1800 từ để đảm bảo có đủ chiều sâu; không rút gọn bằng vài đoạn tổng quan.
- Mở bài phải nêu vấn đề thực tế theo bối cảnh công trình (nhà xưởng, văn phòng, showroom hoặc dân dụng). Không được mở đầu bằng câu "Điều hòa là một trong những...".
- Bắt buộc có các heading H2 đúng tên: "Khi nào nên dùng", "Sai lầm thường gặp", "Gợi ý giải pháp thực tế".
- Có ít nhất một H3, bullet list, ví dụ thực tế, so sánh giải pháp và gợi ý sản phẩm. Chỉ đưa số BTU/m2 khi có allowed_facts hoặc kết quả đã xác minh.
- Có đoạn giải thích kỹ thuật HVAC, hướng dẫn thực tế, gợi ý giải pháp và CTA nhẹ.
- FAQ phải có 3-5 câu hỏi thực tế, không hỏi kiểu chung chung.
- Không bịa thông số. Nếu thiếu dữ liệu sản phẩm, chỉ nói theo nguyên lý hoặc khoảng tham khảo an toàn.
- Nếu có product, chèn tự nhiên và tạo flow chuyển đổi; nếu product là đối thủ, có thể gợi ý GREE như phương án thay thế nhưng không nói xấu đối thủ.
- Title 55-65 ký tự nếu có thể; meta_description 140-160 ký tự.
- Slug lowercase, không dấu, dùng dấu gạch ngang.
- Internal links gồm category, product nếu có, và bài viết liên quan.
PROMPT;
    }

    private function buildRetryPrompt(array $input, array $guardContext, array $previousJson, string $reason): string
    {
        $previousSummary = json_encode([
            'title' => Arr::get($previousJson, 'title'),
            'word_count' => $this->wordCount((string) Arr::get($previousJson, 'content', '')),
            'faq_count' => count($this->normalizeFaq(Arr::get($previousJson, 'faq', []))),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $previousDraft = json_encode($previousJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->buildPrompt($input, $guardContext).<<<PROMPT

Lần viết trước bị hệ thống từ chối vì: {$reason}
Tóm tắt bản bị từ chối: {$previousSummary}
Bản JSON bị từ chối để bạn mở rộng và viết lại sâu hơn: {$previousDraft}

Hãy viết lại TOÀN BỘ JSON từ đầu. Bắt buộc:
- content mục tiêu 1400-1800 từ, tuyệt đối không dưới 1100 từ, không đếm phần FAQ.
- FAQ đủ 3-5 câu.
- Có đúng các H2 "Khi nào nên dùng", "Sai lầm thường gặp", "Gợi ý giải pháp thực tế".
- Bài phải đi vào thực tế công trình, có ví dụ thực tế, so sánh giải pháp và gợi ý sản phẩm cụ thể. Chỉ dùng số m2/BTU khi có allowed_facts hoặc kết quả đã xác minh.
PROMPT;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Bạn là HVAC SEO Content Engine + Topic Generator + Internal Linking System cho website lead generation về điều hòa/HVAC.
Bạn viết như một HVAC SEO Content Writer cấp chuyên gia: có chiều sâu kỹ thuật, bám thực tế công trình, tránh mọi câu chữ marketing rỗng.

Luật suy luận:
- Nếu thiếu topic, tự tạo topic theo category và intent.
- Nếu thiếu keyword, tự tạo keyword SEO tự nhiên.
- Nếu thiếu intent, suy luận từ topic/category.

Logic category:
- Kiến thức HVAC: giải thích khái niệm, nguyên lý, ứng dụng.
- So sánh: có bảng so sánh, ưu/nhược, nên chọn gì.
- Giải pháp: theo công trình, có m2/BTU khi có cơ sở, hướng dẫn chọn hệ thống.
- Lỗi / sửa chữa: nguyên nhân, cách xử lý, khi nào cần kỹ thuật.

Kiến thức HVAC bắt buộc:
- Hiểu BTU, inverter, cassette, duct, VRF, điều hòa tủ đứng.
- Không bịa số liệu, không khẳng định công suất nếu thiếu dữ kiện tải nhiệt.
- Công thức BTU chỉ được dùng khi có kết quả đã xác minh trong hệ thống hoặc allowed_facts; nếu thiếu dữ liệu thì nói nguyên lý và yêu cầu khảo sát.
- Bài phải có tối thiểu 1000 từ, có H2/H3 rõ ràng, ví dụ thực tế, so sánh giải pháp và gợi ý sản phẩm. Số BTU/m2 chỉ hợp lệ khi có nguồn trong hệ thống.
- Mở bài phải đi thẳng vào vấn đề thực tế của công trình; không mở đầu bằng "Điều hòa là một trong những...".
- Bắt buộc có các section: "Khi nào nên dùng", "Sai lầm thường gặp", "Gợi ý giải pháp thực tế".
- FAQ gồm 3-5 câu hỏi thật, liên quan quyết định chọn/lắp/vận hành.

SEO:
- Không nhồi keyword, không duplicate, không viết chung chung.
- Tags gồm brand nếu có, loại máy, công suất nếu có, intent/topic.
- Internal link fallback: nếu không có product thì gợi ý /dieu-hoa-tu-dung.
PROMPT;
    }

    /**
     * Keep weak generations out of the publishing flow. The AI can be asked
     * nicely, but the job should only complete when the draft is usable.
     */
    private function validateContentQuality(string $content, array $faq): array
    {
        $issues = [];
        $wordCount = $this->wordCount($content);
        $plainText = preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $asciiText = Str::ascii(Str::lower($plainText));
        $asciiContent = Str::ascii(Str::lower($content));

        if ($wordCount < 1000) {
            $issues[] = "content quá ngắn ({$wordCount}/1000 từ)";
        }

        if (substr_count(Str::lower($content), '<h2') < 3) {
            $issues[] = 'thiếu cấu trúc H2 rõ ràng';
        }

        if (! str_contains(Str::lower($content), '<h3')) {
            $issues[] = 'thiếu H3';
        }

        foreach (['khi nao nen dung', 'sai lam thuong gap', 'goi y giai phap thuc te'] as $requiredSection) {
            if (! str_contains($asciiContent, $requiredSection)) {
                $issues[] = "thiếu section {$requiredSection}";
            }
        }

        if (str_starts_with($asciiText, 'dieu hoa la mot trong nhung')) {
            $issues[] = 'mở bài dùng mẫu câu bị cấm';
        }

        if (count($faq) < 3) {
            $issues[] = 'FAQ phải có ít nhất 3 câu hỏi';
        }

        return $issues;
    }

    private function wordCount(string $html): int
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }

    private function resolveProduct(array $payload): ?Product
    {
        $productId = $payload['product_id'] ?? null;

        return $productId ? Product::with(['brand', 'category'])->find($productId) : null;
    }

    private function resolveBrand(array $payload, ?Product $product): ?Brand
    {
        if ($product?->brand) {
            return $product->brand;
        }

        $brandId = $payload['brand_id'] ?? null;

        return $brandId ? Brand::find($brandId) : null;
    }

    private function inferIntent(string $topic, string $category): string
    {
        $haystack = Str::lower($topic.' '.$category);

        if (Str::contains($haystack, ['so sánh', 'so sanh', 'nên chọn', 'nen chon', 'giá', 'bao nhiêu', 'review'])) {
            return 'commercial';
        }

        return 'informational';
    }

    private function fallbackTopic(array $input): string
    {
        $audience = $input['audience'] ?: 'công trình';

        return match ($input['category']) {
            'So sánh' => 'So sánh các giải pháp điều hòa phù hợp cho '.$audience,
            'Giải pháp' => 'Giải pháp điều hòa tối ưu cho '.$audience,
            'Lỗi / sửa chữa' => 'Các lỗi điều hòa thường gặp và cách xử lý an toàn',
            default => 'Kiến thức HVAC cần biết khi chọn hệ thống điều hòa',
        };
    }

    private function normalizeTags(mixed $tags, array $input): array
    {
        $normalized = [];

        foreach (is_array($tags) ? $tags : [] as $tag) {
            if (is_string($tag)) {
                $normalized[] = ['name' => $tag, 'type' => 'topic'];

                continue;
            }

            if (is_array($tag) && ! empty($tag['name'])) {
                $normalized[] = [
                    'name' => trim((string) $tag['name']),
                    'type' => $tag['type'] ?? 'topic',
                ];
            }
        }

        if ($input['brand']) {
            $normalized[] = ['name' => $input['brand']->name, 'type' => 'brand'];
        }

        if ($input['intent']) {
            $normalized[] = ['name' => str_replace('_', '-', $input['intent']), 'type' => 'topic'];
        }

        return collect($normalized)
            ->filter(fn ($tag) => ! empty($tag['name']))
            ->unique(fn ($tag) => Str::lower($tag['type'].'|'.$tag['name']))
            ->values()
            ->all();
    }

    private function normalizeFaq(mixed $faq): array
    {
        return collect(is_array($faq) ? $faq : [])
            ->filter(fn ($item) => is_array($item) && ! empty($item['question']) && ! empty($item['answer']))
            ->take(5)
            ->values()
            ->all();
    }

    private function normalizeLinks(mixed $links, array $input): array
    {
        $normalized = collect(is_array($links) ? $links : [])
            ->filter(fn ($item) => is_array($item) && (! empty($item['url']) || ! empty($item['suggested_url'])))
            ->map(function ($item) {
                return [
                    'type' => $item['type'] ?? 'related_post',
                    'anchor' => $item['anchor'] ?? $item['anchor_text'] ?? 'Xem thêm',
                    'url' => $item['url'] ?? $item['suggested_url'],
                ];
            })
            ->values()
            ->all();

        if ($input['product']) {
            $normalized[] = [
                'type' => 'product',
                'anchor' => 'Xem sản phẩm liên quan',
                'url' => '/san-pham/'.$input['product']->slug,
            ];
        } else {
            $normalized[] = [
                'type' => 'category',
                'anchor' => 'Xem danh mục điều hòa tủ đứng',
                'url' => '/dieu-hoa-tu-dung',
            ];
        }

        return collect($normalized)
            ->unique(fn ($link) => $link['type'].'|'.$link['url'])
            ->values()
            ->all();
    }
}
