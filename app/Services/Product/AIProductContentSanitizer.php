<?php

namespace App\Services\Product;

use App\Support\EncodingGuard;
use Illuminate\Support\Str;
use RuntimeException;

class AIProductContentSanitizer
{
    private const ALLOWED_TAGS = '<h2><h3><p><ul><ol><li><strong><em><table><thead><tbody><tr><th><td><a>';

    private const DISPLAY_TEXT_KEYS = [
        'excerpt',
        'content_html',
        'seo_title',
        'meta_description',
        'og_title',
        'og_description',
        'merchant_title',
        'merchant_description',
    ];

    private const MAX_TEXT_LENGTHS = [
        'seo_title' => 255,
        'meta_description' => 255,
        'og_title' => 255,
        'og_description' => 255,
        'merchant_title' => 255,
    ];

    private const VIETNAMESE_DIACRITIC_PATTERN = '/[ăâđêôơưáàảãạắằẳẵặấầẩẫậéèẻẽẹếềểễệíìỉĩịóòỏõọốồổỗộớờởỡợúùủũụứừửữựýỳỷỹỵ]/iu';

    private const MOJIBAKE_PATTERN = '/�|Ã.|Ä.|Æ.|áº|á»|â€|â€™|â€œ|â€|â€“|â€”/u';

    private const UNACCENTED_VIETNAMESE_PATTERN = '/\b(dieu hoa|may lanh|cong suat|am tran|tu dung|ong gio|khong khi|dien tich|do on|luu luong gio|lap dat|bao hanh|chinh hang|nha xuong|van phong|dan nong|dan lanh|gas lanh|tiet kiem dien)\b/iu';

    public function sanitizeHtml(string $html): string
    {
        $html = $this->humanizeInternalLanguage($html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, self::ALLOWED_TAGS);
        $html = preg_replace('/\s(on[a-z]+|style|class|id)=(".*?"|\'.*?\'|[^\s>]*)/iu', '', $html) ?? '';
        $html = preg_replace('/href=("|\')\s*javascript:.*?\1/iu', 'href="#"', $html) ?? '';

        return trim($html);
    }

    public function sanitizePayload(array $payload): array
    {
        $this->assertUtf8Array($payload);

        $payload['excerpt'] = $this->cleanText((string) ($payload['excerpt'] ?? ''));
        $payload['content_html'] = $this->sanitizeHtml((string) ($payload['content_html'] ?? ''));
        $payload['seo_title'] = $this->cleanLimitedText('seo_title', (string) ($payload['seo_title'] ?? ''));
        $payload['meta_description'] = $this->cleanLimitedText('meta_description', (string) ($payload['meta_description'] ?? ''));
        $payload['og_title'] = $this->cleanLimitedText('og_title', (string) ($payload['og_title'] ?? ''));
        $payload['og_description'] = $this->cleanLimitedText('og_description', (string) ($payload['og_description'] ?? ''));
        $payload['merchant_title'] = $this->cleanLimitedText('merchant_title', (string) ($payload['merchant_title'] ?? ''));
        $payload['merchant_description'] = $this->cleanText((string) ($payload['merchant_description'] ?? ''));
        $payload['tags'] = $this->sanitizeTags($payload['tags'] ?? []);
        $payload['internal_links'] = $this->sanitizeLinks($payload['internal_links'] ?? []);
        $payload['warnings'] = $this->sanitizeStringList($payload['warnings'] ?? []);
        $payload['faq'] = $this->sanitizeFaq($payload['faq'] ?? []);

        $this->assertNoUnsafePlaceholders($payload);
        $this->assertNoInternalLanguage($payload);
        $this->assertCleanEncoding($payload);
        $this->assertVietnameseText($payload);

        $payload['warnings'] = array_values(array_unique(array_merge($payload['warnings'], [
            'encoding_checked',
            'vietnamese_verified',
        ])));

        return $payload;
    }

    public function assertUtf8Array(array $payload): void
    {
        array_walk_recursive($payload, function ($value): void {
            if (is_string($value) && ! EncodingGuard::isValidUtf8($value)) {
                throw new RuntimeException('AI output không phải UTF-8 hợp lệ.');
            }
        });
    }

    public function assertNoUnsafePlaceholders(array $payload): void
    {
        $text = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        if (preg_match('/\b(lorem|undefined|null value|insert here)\b|N\/A|\{\{|\}\}|\$[a-zA-Z_][a-zA-Z0-9_]*/iu', $text)) {
            throw new RuntimeException('AI output chứa placeholder hoặc raw variable không an toàn.');
        }
    }

    public function assertNoInternalLanguage(array $payload): void
    {
        $text = $this->displayText($payload);

        if (preg_match('/\b[A-Z]{2,}[A-Za-z]*(?:Service|Controller|Model)\b|\b[A-Z][a-z]+(?:[A-Z][A-Za-z0-9]+)+(?:Service|Controller|Model|Repository|Provider|Gateway|Adapter)?\b|\b[a-zA-Z_][a-zA-Z0-9_]*\s*\(\s*\)|\b(?:product|post|blog|config|request|response|job|payload|input|output)\.[a-zA-Z_][a-zA-Z0-9_.]*\b/u', $text)) {
            throw new RuntimeException('AI output chua ngon ngu noi bo hoac cu phap giong code.');
        }
    }

    public function assertCleanEncoding(array $payload): void
    {
        $this->assertCleanEncodingText($this->displayText($payload));
    }

    public function assertVietnameseText(array $payload): void
    {
        $this->assertVietnameseTextString($this->displayText($payload));

        foreach ($payload['tags'] ?? [] as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }

            $isVietnamese = (bool) preg_match(self::VIETNAMESE_DIACRITIC_PATTERN, $tag);
            $isAsciiTag = (bool) preg_match('/^[a-z0-9][a-z0-9\s-]*$/', $tag);

            if (! $isVietnamese && ! $isAsciiTag) {
                throw new RuntimeException('AI output có tag không đúng chuẩn tiếng Việt hoặc slug hợp lệ.');
            }
        }
    }

    public function assertCleanEncodingText(string $text): void
    {
        if (! EncodingGuard::isValidUtf8($text)) {
            throw new RuntimeException('AI output không phải UTF-8 hợp lệ.');
        }

        if (EncodingGuard::hasMojibake($text) || preg_match(self::MOJIBAKE_PATTERN, $text)) {
            throw new RuntimeException('AI output có dấu hiệu lỗi mã hóa tiếng Việt.');
        }
    }

    public function assertVietnameseTextString(string $text): void
    {
        if ($text !== '' && ! preg_match(self::VIETNAMESE_DIACRITIC_PATTERN, $text)) {
            throw new RuntimeException('AI output chưa đạt chuẩn tiếng Việt có dấu.');
        }

        if (preg_match(self::UNACCENTED_VIETNAMESE_PATTERN, $text)) {
            throw new RuntimeException('AI output có tiếng Việt không dấu.');
        }
    }

    private function cleanText(string $text): string
    {
        $text = EncodingGuard::ensureUtf8($text, autoFixMojibake: false, rejectBroken: true, context: 'AI output text');
        $text = $this->humanizeInternalLanguage($text);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function cleanLimitedText(string $key, string $text): string
    {
        $text = $this->cleanText($text);
        $limit = self::MAX_TEXT_LENGTHS[$key] ?? null;

        return $limit ? Str::limit($text, $limit, '') : $text;
    }

    private function humanizeInternalLanguage(string $text): string
    {
        $text = str_replace(
            ['BTUCalculatorService', 'BtuCalculatorService', 'calculateBTU()', 'product.capacity_btu'],
            ['dữ liệu khảo sát thực tế', 'dữ liệu khảo sát thực tế', 'tính công suất điều hòa', 'công suất điều hòa'],
            $text
        );

        $text = preg_replace(
            '/\b(?:product|post|blog|config|request|response|job|payload|input|output)\.[a-zA-Z_][a-zA-Z0-9_.]*\b/iu',
            'dữ liệu đã lưu trong hệ thống',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\b[a-zA-Z_][a-zA-Z0-9_]*\s*\(\s*\)/u',
            'quy trình xử lý',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\b[A-Za-z0-9_]*(?:Service|Controller|Model|Repository|Provider|Gateway|Adapter)\b/u',
            'hệ thống xử lý nội dung',
            $text
        ) ?? $text;

        $text = preg_replace('/\bAPI\b/u', 'kết nối hệ thống', $text) ?? $text;

        $text = preg_replace_callback(
            '/\b[A-Z][a-z]+(?:[A-Z][A-Za-z0-9]+)+\b/u',
            fn (array $match): string => trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', $match[0])),
            $text
        ) ?? $text;

        return $text;
    }

    private function displayText(array $payload): string
    {
        $parts = [];

        foreach (self::DISPLAY_TEXT_KEYS as $key) {
            $parts[] = (string) ($payload[$key] ?? '');
        }

        foreach ((array) ($payload['faq'] ?? []) as $item) {
            if (is_array($item)) {
                $parts[] = (string) ($item['question'] ?? '');
                $parts[] = (string) ($item['answer'] ?? '');
            }
        }

        return trim(html_entity_decode(strip_tags(implode("\n", $parts)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function sanitizeStringList(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($item) => $this->cleanText($this->stringFromMixed($item)))
            ->map(fn ($item) => preg_match(self::VIETNAMESE_DIACRITIC_PATTERN, $item) ? $item : mb_strtolower($item, 'UTF-8'))
            ->filter()
            ->unique(fn ($item) => mb_strtolower($item))
            ->values()
            ->all();
    }

    private function sanitizeTags(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($item) => $this->cleanText($this->stringFromMixed($item)))
            ->map(function (string $tag): string {
                if ($tag === '') {
                    return '';
                }

                if (preg_match(self::VIETNAMESE_DIACRITIC_PATTERN, $tag)) {
                    $tag = mb_strtolower($tag, 'UTF-8');

                    return trim(preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $tag) ?? '');
                }

                $ascii = Str::ascii($tag);
                $ascii = mb_strtolower($ascii, 'UTF-8');
                $ascii = preg_replace('/(\d)[.,](\d{3})/u', '$1$2', $ascii) ?? $ascii;
                $ascii = preg_replace('/(\d)\s+(btu|hp|kw|db|pa|mm|kg|w|a)\b/u', '$1$2', $ascii) ?? $ascii;
                $ascii = preg_replace('/[^a-z0-9]+/u', '-', $ascii) ?? '';

                return trim($ascii, '-');
            })
            ->filter()
            ->unique(fn ($item) => mb_strtolower($item))
            ->values()
            ->all();
    }

    private function stringFromMixed(mixed $item): string
    {
        if (is_scalar($item)) {
            return (string) $item;
        }

        if (is_array($item)) {
            foreach (['name', 'code', 'warning', 'claim', 'message', 'value', 'label'] as $key) {
                if (isset($item[$key]) && is_scalar($item[$key])) {
                    return (string) $item[$key];
                }
            }

            return json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return '';
    }

    private function sanitizeFaq(mixed $faq): array
    {
        return collect(is_array($faq) ? $faq : [])
            ->filter(fn ($item) => is_array($item))
            ->map(fn ($item) => [
                'question' => $this->cleanText((string) ($item['question'] ?? '')),
                'answer' => $this->sanitizeHtml((string) ($item['answer'] ?? '')),
            ])
            ->filter(fn ($item) => $item['question'] !== '' && $item['answer'] !== '')
            ->take(8)
            ->values()
            ->all();
    }

    private function sanitizeLinks(mixed $links): array
    {
        return collect(is_array($links) ? $links : [])
            ->filter(fn ($item) => is_array($item))
            ->map(function ($item) {
                $url = $this->cleanText((string) ($item['url'] ?? $item['suggested_url'] ?? ''));

                return [
                    'type' => $this->cleanText((string) ($item['type'] ?? 'related')),
                    'anchor' => $this->cleanText((string) ($item['anchor'] ?? $item['anchor_text'] ?? 'Xem thêm')),
                    'url' => str_starts_with($url, '/') ? $url : '',
                ];
            })
            ->filter(fn ($item) => $item['url'] !== '')
            ->values()
            ->all();
    }
}
