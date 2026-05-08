<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini API Configuration
    |--------------------------------------------------------------------------
    */

    'api_key' => env('GEMINI_API_KEY'),

    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/',

    /*
    |--------------------------------------------------------------------------
    | Generation Settings
    |--------------------------------------------------------------------------
    */

    'generation' => [
        'temperature' => 0.7,
        'top_p' => 0.9,
        'top_k' => 40,
        'max_output_tokens' => 8192,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Generation Prompts
    |--------------------------------------------------------------------------
    */

    'prompts' => [

        'blog_outline' => <<<'PROMPT'
Bạn là chuyên gia SEO content về lĩnh vực điều hòa không khí, đặc biệt là điều hòa tủ đứng.

Hãy tạo outline chi tiết cho bài viết với chủ đề: "{topic}"
Từ khóa chính: "{keyword}"
Search intent: {intent}

Yêu cầu:
- Outline phải có H1, H2, H3 rõ ràng
- Mỗi heading phải chứa keyword tự nhiên
- Có phần mở bài, thân bài, kết luận
- Đề xuất FAQ (3-5 câu hỏi)
- Đề xuất internal links
- Đề xuất meta title và meta description
- Viết bằng tiếng Việt
- Tối ưu cho SEO
PROMPT,

        'blog_draft' => <<<'PROMPT'
Bạn là chuyên gia viết content SEO về điều hòa tủ đứng.

Dựa trên outline sau, hãy viết bài viết đầy đủ:

{outline}

Yêu cầu:
- Viết bằng tiếng Việt tự nhiên, dễ đọc
- Độ dài 1500-3000 từ
- Sử dụng heading H2, H3 đúng outline
- Chèn keyword tự nhiên, không spam
- Có ví dụ thực tế
- Có số liệu cụ thể khi có thể
- Tone chuyên nghiệp nhưng gần gũi
- Kết thúc bằng CTA tư vấn/báo giá
- Output dạng HTML (không markdown)
PROMPT,

        'tag_suggestions' => <<<'PROMPT'
Dựa trên nội dung bài viết về điều hòa tủ đứng sau:

Tiêu đề: "{title}"
Nội dung tóm tắt: "{excerpt}"

Hãy gợi ý 5-10 tags phù hợp theo các nhóm:
- brand: thương hiệu (vd: Daikin, LG, Panasonic)
- btu: công suất (vd: 18000 BTU, 24000 BTU)
- technology: công nghệ (vd: inverter, non-inverter)
- use_case: ứng dụng (vd: văn phòng, nhà xưởng)
- technical: kỹ thuật (vd: gas R32, 3 pha)
- topic: chủ đề (vd: so sánh, hướng dẫn)

Output JSON array: [{"name": "...", "type": "..."}]
PROMPT,

        'blog_faq' => <<<'PROMPT'
Dựa trên outline sau:
{outline}

Hãy tạo 3-5 câu hỏi thường gặp (FAQ) và câu trả lời.
Output JSON array: [{"question": "...", "answer": "..."}]
PROMPT,

        'blog_meta' => <<<'PROMPT'
Dựa trên outline sau:
{outline}

Hãy viết SEO Meta Title (tối đa 60 ký tự) và SEO Meta Description (tối đa 155 ký tự).
Output JSON object: {"seo_title": "...", "seo_description": "..."}
PROMPT,

        'blog_internal_links' => <<<'PROMPT'
Dựa trên bài viết đã hoàn thành sau:
{draft}

Hãy gợi ý 3-5 vị trí chèn internal links (các cụm từ khóa anchor text).
Output JSON array: [{"anchor_text": "...", "suggested_url_slug": "..."}]
PROMPT,

    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'max_requests_per_minute' => 10,
        'retry_after_seconds' => 60,
    ],

];
