<?php

namespace App\Services\Product;

use App\Models\AiContentJob;
use App\Models\Product;
use App\Services\AI\AIManager;
use App\Services\HVAC\HVACTechnicalFactValidator;
use App\Support\IssueList;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductAIContentService
{
    private AIManager $aiManager;
    private AIProductContentSanitizer $sanitizer;

    public function __construct(AIManager $aiManager, ?AIProductContentSanitizer $sanitizer = null)
    {
        $this->aiManager = $aiManager;
        $this->sanitizer = $sanitizer ?? app(AIProductContentSanitizer::class);
    }

    public function checkAIEnabled(): void
    {
        $activeCount = \App\Models\AiProvider::where('status', 'active')->count();
        if ($activeCount === 0) {
            throw new Exception('Không có nhà cung cấp AI nào đang hoạt động. Vui lòng cấu hình trong khu vực AI.');
        }
    }

    public function generateContent(array $productData, array $options, ?int $userId = null, ?string $contextId = null): array
    {
        $this->checkAIEnabled();

        $system = "Bạn là một chuyên gia về hệ thống điều hòa không khí (HVAC) và SEO Content Marketing. Bạn phải trả về kết quả dưới định dạng JSON.";

        $prompt = "Nhiệm vụ của bạn là viết nội dung sản phẩm chất lượng cao dựa trên các thông số sau:\n";
        $prompt .= "Tên sản phẩm: " . ($productData['name'] ?? 'Không rõ') . "\n";
        $prompt .= "Model: " . ($productData['model_code'] ?? 'Không rõ') . "\n";
        $prompt .= "Thương hiệu: " . ($productData['brand']['name'] ?? 'Không rõ') . "\n";
        $prompt .= "Công suất: " . ($productData['btu'] ?? 'Không rõ') . " BTU\n";
        $prompt .= "Inverter: " . (($productData['inverter'] ?? false) ? 'Có' : 'Không') . "\n";
        $prompt .= "Kiểu làm lạnh: " . ($productData['cooling_type'] ?? 'Không rõ') . "\n";
        $prompt .= "Gas: " . ($productData['refrigerant_gas'] ?? 'Không rõ') . "\n";
        $prompt .= "Điện áp: " . ($productData['voltage'] ?? 'Không rõ') . "\n\n";

        $prompt .= "Yêu cầu trả về JSON chuẩn theo đúng format sau:\n{\n";

        if ($options['generate_short_description'] ?? false) {
            $prompt .= '  "short_description": "Mô tả ngắn gọn, hấp dẫn, chuẩn SEO (khoảng 150-200 chữ)",' . "\n";
        }
        if ($options['generate_long_description'] ?? false) {
            $prompt .= '  "long_description": "Bài viết giới thiệu chi tiết (HTML sạch: h2, h3, p, ul/li, KHÔNG dùng inline style/emoji/script)",' . "\n";
        }
        if (($options['generate_warranty_info'] ?? false) && ! empty($productData['warranty_info'])) {
            $prompt .= '  "warranty_info": "Chỉ được viết lại từ thông tin bảo hành đã cung cấp, không tự tạo thời hạn bảo hành (HTML sạch)",' . "\n";
        }
        if ($options['generate_installation_note'] ?? false) {
            $prompt .= '  "installation_note": "Lưu ý lắp đặt quan trọng cho loại điều hòa này (HTML sạch)",' . "\n";
        }
        if ($options['generate_faq'] ?? false) {
            $prompt .= '  "faq_suggestions": [{"question": "...", "answer": "..."}],' . "\n";
        }
        if ($options['generate_tags'] ?? false) {
            $prompt .= '  "tag_suggestions": ["tag1", "tag2"]' . "\n";
        }

        $prompt .= "}\n\nQuy tắc kiểm soát nội dung:\n";
        $prompt .= "- Chỉ được dùng thông số có trong input. Không tự tạo BTU, kW, HP, diện tích, độ ồn, lưu lượng gió, gas, giá, bảo hành, VAT, CO/CQ.\n";
        $prompt .= "- Nếu thiếu dữ liệu, bỏ qua trường đó hoặc thêm warning missing_technical_data. Không viết claim tuyệt đối như chính hãng 100%, tốt nhất, rẻ nhất, miễn phí.\n";
        $prompt .= "- Output nên có warnings gồm encoding_checked và vietnamese_verified sau khi tự kiểm tra UTF-8, cùng blocked_claims nếu phát hiện thiếu nguồn.\n\n";
        $prompt .= "Quy tắc:\n1. Viết bằng tiếng Việt có dấu, UTF-8 sạch, ngôn ngữ kỹ thuật chính xác.\n2. Không trả về tiếng Việt không dấu, ký tự lỗi hoặc text bị vỡ dấu.\n3. Không đưa tên service, class, function, API, biến nội bộ, CamelCase hoặc cú pháp code vào nội dung.\n4. Không bịa ra thông số không có thật.\n5. Nếu thiếu thông tin quan trọng, viết trung lập và cần khảo sát thực tế.\n6. Trả về đúng JSON để hệ thống có thể đọc.";

        $contextId = $contextId ?? (string) Str::uuid();

        try {
            $result = $this->aiManager->generate([
                'system' => $system,
                'prompt' => $prompt,
            ], [
                'task_type' => 'product_content',
                'context_id' => $contextId,
                'require_json' => true,
                'preserve_context' => true,
            ]);

            $parsed = $this->factCheckParsedOutput($productData, $result['json']);
            
            $this->logJob($productData, $parsed, 'Generate Product Content', $userId, $result['provider_id']);

            return $parsed;
        } catch (Exception $e) {
            Log::error('AI generateContent failed: ' . $e->getMessage());
            throw new Exception('Lỗi AI khi tạo nội dung: ' . $e->getMessage());
        }
    }

    private function factCheckParsedOutput(array $productData, array $parsed): array
    {
        $product = ! empty($productData['id'])
            ? Product::find($productData['id'])
            : null;

        if (! $product) {
            $parsed['warnings'] = IssueList::normalize(
                $parsed['warnings'] ?? [],
                ['missing_product_record_for_fact_check', 'encoding_checked', 'vietnamese_verified']
            );

            $this->validateLegacyOutputLanguage($parsed);

            return $parsed;
        }

        $result = app(HVACTechnicalFactValidator::class)->validateProductContent($product, $parsed);
        $blockedClaims = IssueList::normalize($result['blocked_claims'] ?? []);

        if (($result['status'] ?? null) === 'blocked') {
            throw new Exception('AI output blocked by HVAC fact validation: ' . implode(', ', $blockedClaims));
        }

        $parsed['warnings'] = IssueList::normalize($parsed['warnings'] ?? [], $result['warnings'] ?? [], ['encoding_checked', 'vietnamese_verified']);
        $parsed['blocked_claims'] = $blockedClaims;
        $parsed['used_facts'] = IssueList::normalize($result['used_facts'] ?? []);
        $this->validateLegacyOutputLanguage($parsed);

        return $parsed;
    }

    private function validateLegacyOutputLanguage(array $parsed): void
    {
        $text = implode("\n", array_filter([
            (string) ($parsed['short_description'] ?? ''),
            (string) ($parsed['long_description'] ?? ''),
            (string) ($parsed['warranty_info'] ?? ''),
            (string) ($parsed['installation_note'] ?? ''),
            (string) ($parsed['seo_title'] ?? ''),
            (string) ($parsed['seo_description'] ?? ''),
            (string) ($parsed['og_title'] ?? ''),
            (string) ($parsed['og_description'] ?? ''),
        ]));

        foreach ((array) ($parsed['faq_suggestions'] ?? []) as $faq) {
            if (is_array($faq)) {
                $text .= "\n".($faq['question'] ?? '').' '.($faq['answer'] ?? '');
            }
        }

        $this->sanitizer->assertUtf8Array($parsed);
        $this->sanitizer->assertNoUnsafePlaceholders($parsed);
        $this->sanitizer->assertNoInternalLanguage($parsed);
        $this->sanitizer->assertCleanEncodingText($text);
        $this->sanitizer->assertVietnameseTextString(strip_tags($text));
    }

    public function generateSeo(array $productData, ?int $userId = null, ?string $contextId = null): array
    {
        $this->checkAIEnabled();

        $system = "Bạn là chuyên gia SEO HVAC. Bạn phải trả về kết quả dưới định dạng JSON chuẩn.";

        $prompt = "Hãy tạo thẻ meta SEO cho sản phẩm:\n";
        $prompt .= "Tên sản phẩm: " . ($productData['name'] ?? 'Không rõ') . "\n";
        $prompt .= "Model: " . ($productData['model_code'] ?? 'Không rõ') . "\n";
        $prompt .= "Thương hiệu: " . ($productData['brand']['name'] ?? 'Không rõ') . "\n";
        $prompt .= "Mô tả ngắn: " . ($productData['short_description'] ?? '') . "\n\n";

        $prompt .= "Format:\n{\n";
        $prompt .= "  \"seo_title\": \"Tiêu đề SEO tối đa 60 ký tự, hấp dẫn\",\n";
        $prompt .= "  \"seo_description\": \"Meta description 140-160 ký tự, chứa keyword\",\n";
        $prompt .= "  \"og_title\": \"Tiêu đề khi share Facebook\",\n";
        $prompt .= "  \"og_description\": \"Mô tả khi share Facebook\"\n";
        $prompt .= "}\n\nQuy tắc: Không dùng emoji, không spam keyword, đúng số lượng ký tự.";

        $contextId = $contextId ?? (string) Str::uuid();

        try {
            $result = $this->aiManager->generate([
                'system' => $system,
                'prompt' => $prompt,
            ], [
                'task_type' => 'product_seo',
                'context_id' => $contextId,
                'require_json' => true,
                'preserve_context' => true,
            ]);

            $parsed = $this->factCheckParsedOutput($productData, $result['json']);

            $this->logJob($productData, $parsed, 'Generate Product SEO', $userId, $result['provider_id']);

            return $parsed;
        } catch (Exception $e) {
            Log::error('AI generateSeo failed: ' . $e->getMessage());
            throw new Exception('Lỗi AI khi tạo SEO: ' . $e->getMessage());
        }
    }

    private function logJob(array $input, array $output, string $topic, ?int $userId, ?int $providerId): void
    {
        try {
            AiContentJob::create([
                // 'provider_id' => $providerId, // if we added it to AiContentJob later
                'topic' => $topic . ': ' . ($input['name'] ?? 'Unknown'),
                'primary_keyword' => $input['name'] ?? null,
                'intent' => 'commercial',
                'input_payload' => $input,
                'output_draft' => $output['long_description'] ?? null,
                'output_meta' => [
                    'seo_title' => $output['seo_title'] ?? null,
                    'seo_description' => $output['seo_description'] ?? null,
                ],
                'output_faq' => $output['faq_suggestions'] ?? null,
                'output_tags' => $output['tag_suggestions'] ?? null,
                'status' => \App\Enums\AIContentJobStatus::Completed,
                'created_by' => $userId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log AI Content Job: ' . $e->getMessage());
        }
    }
}
