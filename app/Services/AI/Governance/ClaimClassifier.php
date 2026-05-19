<?php

namespace App\Services\AI\Governance;

use Illuminate\Support\Str;

/**
 * Classifies numeric claims by sentence context to determine if they are:
 * - product_technical_claim: references a specific product's specs, must be verified
 * - educational_statement: general HVAC knowledge, no product-specific verification needed
 * - formula_calculation_claim: BTU formulas/calculations, only allowed from verified service
 * - business_config_claim: VAT, warranty, admin-configured options
 */
class ClaimClassifier
{
    /**
     * Educational patterns - general HVAC knowledge statements
     * that should NOT be treated as product-specific claims.
     */
    private const EDUCATIONAL_PATTERNS = [
        '/BTU\s+là\s+/iu',
        '/BTU\s+(?:viết tắt|nghĩa là|có nghĩa|được hiểu)/iu',
        '/đơn\s+vị\s+(?:đo|tính|công suất)/iu',
        '/(?:diện tích|m2|m²)\s+là\s+đơn\s+vị/iu',
        '/(?:Pa|pascal)\s+là\s+đơn\s+vị/iu',
        '/(?:dB|decibel)\s+là\s+đơn\s+vị/iu',
        '/(?:kW|kilowatt)\s+là\s+đơn\s+vị/iu',
        '/HP\s+(?:là|viết tắt|nghĩa là)/iu',
        '/công\s+suất\s+(?:thường\s+)?(?:được\s+)?(?:đo|tính|biểu\s+thị)\s+bằng/iu',
        '/(?:phụ thuộc|tùy thuộc|ảnh hưởng)\s+(?:vào|bởi)\s+(?:diện tích|nhiều yếu tố)/iu',
    ];

    /**
     * ASCII equivalents for educational patterns (tiếng Việt không dấu).
     */
    private const EDUCATIONAL_ASCII_PATTERNS = [
        '/btu\s+la\s+/iu',
        '/btu\s+(?:viet tat|nghia la|co nghia|duoc hieu)/iu',
        '/don\s+vi\s+(?:do|tinh|cong suat)/iu',
        '/(?:dien tich|m2)\s+la\s+don\s+vi/iu',
        '/(?:pa|pascal)\s+la\s+don\s+vi/iu',
        '/(?:db|decibel)\s+la\s+don\s+vi/iu',
        '/(?:kw|kilowatt)\s+la\s+don\s+vi/iu',
        '/hp\s+(?:la|viet tat|nghia la)/iu',
        '/cong\s+suat\s+(?:thuong\s+)?(?:duoc\s+)?(?:do|tinh|bieu\s+thi)\s+bang/iu',
        '/(?:phu thuoc|tuy thuoc|anh huong)\s+(?:vao|boi)\s+(?:dien tich|nhieu yeu to)/iu',
    ];

    /**
     * Formula/calculation patterns - BTU calculation formulas
     * that require verified BTUCalculatorService.
     */
    private const FORMULA_PATTERNS = [
        '/\d+\s*m[2²]\s*[=×x*]\s*\d+\s*BTU/iu',
        '/m[2²]\s*[=×x*]\s*\d+\s*BTU/iu',
        '/\d+\s*BTU\s*\/\s*m[2²]/iu',
        '/(?:diện tích|dien tich)\s*[×x*]\s*\d+/iu',
        '/(?:công thức|cong thuc)\s+(?:là|la)\s+/iu',
        '/phòng\s+\d+\s*m[2²]\s+(?:cần|can)\s+\d+[\.,]?\d*\s*BTU/iu',
        '/phong\s+\d+\s*m2\s+(?:can|cần)\s+\d+[\.,]?\d*\s*btu/iu',
    ];

    /**
     * Product-specific claim indicators.
     */
    private const PRODUCT_CLAIM_INDICATORS = [
        '/(?:sản phẩm|san pham)\s+(?:có|co)\s+(?:công suất|cong suat)/iu',
        '/(?:máy|may)\s+(?:này|nay)\s+(?:có|co)/iu',
        '/(?:công suất|cong suat)\s+(?:của|cua)\s+(?:sản phẩm|san pham|máy|may)/iu',
        '/(?:phù hợp|phu hop)\s+(?:phòng|phong|không gian|khong gian)\s+\d+/iu',
        '/(?:độ ồn|do on)\s+(?:chỉ|chi)?\s*\d+/iu',
        '/(?:áp suất|ap suat)\s+(?:tĩnh|tinh)?\s*\d+/iu',
        '/(?:lưu lượng|luu luong)\s+(?:gió|gio)\s+\d+/iu',
        '/(?:kích thước|kich thuoc)\s+\d+/iu',
        '/(?:trọng lượng|trong luong)\s+\d+/iu',
    ];

    /**
     * Business/admin-configured claim keywords.
     */
    private const BUSINESS_CLAIM_KEYWORDS = [
        'vat',
        'bao gồm vat', 'bao gom vat',
        'miễn phí', 'mien phi',
        'bảo hành', 'bao hanh',
        'chính hãng', 'chinh hang',
        'co/cq', 'co cq',
        'giao hàng', 'giao hang',
        'lắp đặt', 'lap dat',
        'khuyến mãi', 'khuyen mai',
        'hotline',
        'cam kết', 'cam ket',
        'chính sách', 'chinh sach',
    ];

    /**
     * Classify a numeric claim based on the sentence context it appears in.
     *
     * @return string One of: product_technical_claim, educational_statement,
     *                formula_calculation_claim, business_config_claim
     */
    public function classify(string $claimText, string $sentenceContext): string
    {
        $ascii = Str::ascii(Str::lower($sentenceContext));

        // Check if this is a business/admin claim
        foreach (self::BUSINESS_CLAIM_KEYWORDS as $keyword) {
            if (str_contains($ascii, $keyword)) {
                return 'business_config_claim';
            }
        }

        // Check formula/calculation patterns first (more specific)
        foreach (self::FORMULA_PATTERNS as $pattern) {
            if (preg_match($pattern, $sentenceContext) || preg_match($pattern, $ascii)) {
                return 'formula_calculation_claim';
            }
        }

        // Check educational patterns
        foreach (self::EDUCATIONAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $sentenceContext)) {
                return 'educational_statement';
            }
        }

        foreach (self::EDUCATIONAL_ASCII_PATTERNS as $pattern) {
            if (preg_match($pattern, $ascii)) {
                return 'educational_statement';
            }
        }

        // Check product-specific indicators
        foreach (self::PRODUCT_CLAIM_INDICATORS as $pattern) {
            if (preg_match($pattern, $sentenceContext) || preg_match($pattern, $ascii)) {
                return 'product_technical_claim';
            }
        }

        // Default: if number + unit appears with no educational framing, treat as product claim
        return 'product_technical_claim';
    }

    /**
     * Extract the sentence containing a claim from the full text.
     */
    public function extractSentenceContext(string $fullText, string $claimText): string
    {
        $position = mb_stripos($fullText, $claimText);
        if ($position === false) {
            return $fullText;
        }

        // Find sentence boundaries around the claim
        $before = mb_substr($fullText, 0, $position);
        $after = mb_substr($fullText, $position);

        // Find start of sentence
        $sentenceStart = max(
            (int) mb_strrpos($before, '.') + 1,
            (int) mb_strrpos($before, '!') + 1,
            (int) mb_strrpos($before, '?') + 1,
            (int) mb_strrpos($before, "\n") + 1,
            0
        );

        // Find end of sentence
        $sentenceEnd = $position + mb_strlen($claimText);
        foreach (['.', '!', '?', "\n"] as $delimiter) {
            $pos = mb_strpos($after, $delimiter, mb_strlen($claimText));
            if ($pos !== false) {
                $sentenceEnd = min($sentenceEnd, $position + $pos + 1);
                break;
            }
        }

        return trim(mb_substr($fullText, $sentenceStart, $sentenceEnd - $sentenceStart));
    }

    /**
     * Check if a claim is a generic small number used in educational context
     * (e.g., "1 BTU", "1m2" as unit explanation).
     */
    public function isUnitDefinitionNumber(string $claimText, string $sentenceContext): bool
    {
        // Extract the number from the claim
        if (! preg_match('/^(\d+(?:[.,]\d+)?)\s*/u', trim($claimText), $match)) {
            return false;
        }

        $number = (float) str_replace(',', '.', $match[1]);

        // Numbers like 1, used in "1 BTU = ...", "mỗi 1m2", are typically educational
        if ($number <= 1.0) {
            $ascii = Str::ascii(Str::lower($sentenceContext));

            // Check for unit definition context
            $definitionPatterns = [
                '/(?:mỗi|moi)\s+1\s*/iu',
                '/1\s*(?:m[2²]|btu|kw|hp|pa|db)\s+(?:là|la|=|tương ứng|tuong ung)/iu',
                '/(?:đơn vị|don vi)\s+/iu',
                '/(?:quy đổi|quy doi|chuyển đổi|chuyen doi)/iu',
            ];

            foreach ($definitionPatterns as $pattern) {
                if (preg_match($pattern, $sentenceContext) || preg_match($pattern, $ascii)) {
                    return true;
                }
            }
        }

        return false;
    }
}
