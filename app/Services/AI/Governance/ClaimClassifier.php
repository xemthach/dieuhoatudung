<?php

namespace App\Services\AI\Governance;

use Illuminate\Support\Str;

/** Classifies numeric claims by semantic context, not by number alone. */
class ClaimClassifier
{
    private const EDUCATIONAL_PATTERNS = [
        '/BTU\s+là\s+/iu', '/BTU\s+(?:viết tắt|nghĩa là|có nghĩa|được hiểu)/iu',
        '/đơn\s+vị\s+(?:đo|tính|công suất)/iu', '/(?:diện tích|m2|m²)\s+là\s+đơn vị/iu',
        '/(?:Pa|pascal)\s+là\s+đơn vị/iu', '/(?:dB|decibel)\s+là\s+đơn vị/iu',
        '/(?:kW|kilowatt)\s+là\s+đơn vị/iu', '/HP\s+(?:là|viết tắt|nghĩa là)/iu',
        '/công\s+suất\s+(?:thường\s+)?(?:được\s+)?(?:đo|tính|biểu\s+thị)\s+bằng/iu',
        '/(?:phụ thuộc|tùy thuộc|ảnh hưởng)\s+(?:vào|bởi)\s+(?:diện tích|nhiều yếu tố)/iu',
    ];

    private const EDUCATIONAL_ASCII_PATTERNS = [
        '/btu\s+la\s+/iu', '/btu\s+(?:viet tat|nghia la|co nghia|duoc hieu)/iu',
        '/don\s+vi\s+(?:do|tinh|cong suat)/iu', '/(?:dien tich|m2)\s+la\s+don\s+vi/iu',
        '/(?:pa|pascal)\s+la\s+don\s+vi/iu', '/(?:db|decibel)\s+la\s+don\s+vi/iu',
        '/(?:kw|kilowatt)\s+la\s+don\s+vi/iu', '/hp\s+(?:la|viet tat|nghia la)/iu',
        '/cong\s+suat\s+(?:thuong\s+)?(?:duoc\s+)?(?:do|tinh|bieu\s+thi)\s+bang/iu',
        '/(?:phu thuoc|tuy thuoc|anh huong)\s+(?:vao|boi)\s+(?:dien tich|nhieu yeu to)/iu',
    ];

    private const FORMULA_PATTERNS = [
        '/\d+\s*m[2²]\s*[×x*]\s*\d+\s*BTU/iu', '/m[2²]\s*[×x*]\s*\d+\s*BTU/iu',
        '/\d+\s*m2\s*=\s*\d+[\.,]?\d*\s*BTU/iu',
        '/\d+\s*BTU\s*\/\s*m[2²]/iu', '/(?:diện tích|dien tich)\s*[×x*]\s*\d+/iu',
        '/(?:công thức|cong thuc)\s+(?:là|la)\s+/iu',
        '/phòng\s+\d+\s*m[2²]\s+(?:cần|can)\s+\d+[\.,]?\d*\s*BTU/iu',
        '/phong\s+\d+\s*m2\s+can\s+\d+[\.,]?\d*\s*btu/iu',
    ];

    private const PRODUCT_CLAIM_INDICATORS = [
        '/(?:sản phẩm|san pham)\s+(?:có|co)\s+(?:công suất|cong suat)/iu',
        '/(?:máy|may)\s+(?:này|nay)\s+(?:có|co)/iu',
        '/(?:công suất|cong suat)\s+(?:của|cua)\s+(?:sản phẩm|san pham|máy|may)/iu',
        '/(?:phù hợp|phu hop)\s+(?:phòng|phong|không gian|khong gian)\s+\d+/iu',
        '/(?:độ ồn|do on)\s+(?:chỉ|chi)?\s*\d+/iu', '/(?:áp suất|ap suat)\s+(?:tĩnh|tinh)?\s*\d+/iu',
        '/(?:lưu lượng|luu luong)\s+(?:gió|gio)\s+\d+/iu', '/(?:kích thước|kich thuoc)\s+\d+/iu',
        '/(?:trọng lượng|trong luong)\s+\d+/iu',
    ];

    private const BUSINESS_CLAIM_KEYWORDS = [
        'vat', 'bao gồm vat', 'bao gom vat', 'miễn phí', 'mien phi', 'bảo hành', 'bao hanh',
        'chính hãng', 'chinh hang', 'co/cq', 'co cq', 'giao hàng', 'giao hang', 'lắp đặt', 'lap dat',
        'khuyến mãi', 'khuyen mai', 'hotline', 'cam kết', 'cam ket', 'chính sách', 'chinh sach',
    ];

    public function classify(string $claimText, string $sentenceContext): string
    {
        $ascii = Str::ascii(Str::lower($sentenceContext));
        foreach (self::BUSINESS_CLAIM_KEYWORDS as $keyword) if (str_contains($ascii, $keyword)) return 'business_config_claim';
        foreach (self::FORMULA_PATTERNS as $pattern) if (preg_match($pattern, $sentenceContext) || preg_match($pattern, $ascii)) return 'formula_calculation_claim';
        foreach (self::EDUCATIONAL_PATTERNS as $pattern) if (preg_match($pattern, $sentenceContext)) return 'educational_statement';
        foreach (self::EDUCATIONAL_ASCII_PATTERNS as $pattern) if (preg_match($pattern, $ascii)) return 'educational_statement';

        // Capacity has two deliberately non-interchangeable meanings. This
        // classification must happen before generic fact-registry matching so
        // a product name or marketing number cannot satisfy a rated claim.
        if (preg_match('/\b(?:btu|kw)\b/iu', $sentenceContext)
            && preg_match('/\b(?:den|toi da|maximum)\s+[\d.,]+\s*(?:btu|kw)\b/iu', $ascii)) {
            return 'technical_capacity_range_claim';
        }
        if (preg_match('/\b(?:btu|kw)\b/iu', $sentenceContext)
            && (str_contains($ascii, 'cong suat') || preg_match('/\b(?:nhom cong suat|phan khuc|dong may|model thuong mai|commercial grouping|commercial capacity|marketing capacity)\b/iu', $ascii))) {
            if (preg_match('/\b(?:dai cong suat|pham vi cong suat|dao dong|toi thieu|toi da|minimum|maximum|capacity range)\b/iu', $ascii)
                || preg_match('/\btu\s+[\d.,]+\s*(?:btu|kw)?\s+den\s+[\d.,]+\s*(?:btu|kw)\b/iu', $ascii)) {
                return 'technical_capacity_range_claim';
            }
            if (preg_match('/\b(?:nhom cong suat|cong suat thuong mai|phan khuc|dong may|model thuong mai|commercial grouping|commercial capacity|marketing capacity)\b/iu', $ascii)) {
                return 'marketing_capacity_claim';
            }

            if (preg_match('/\b(?:cong suat ky thuat|cong suat dinh muc|cong suat danh dinh|cong suat lam lanh danh dinh|cong suat lam lanh dinh muc|cong suat thuc|cong suat lanh|rated capacity|technical capacity|cooling capacity)\b/iu', $ascii)) {
                return 'technical_capacity_claim';
            }

            return 'ambiguous_capacity_claim';
        }
        if (preg_match('/\b(?:btu|kw)\b/iu', $sentenceContext)) {
            return 'generic_capacity_mention';
        }
        foreach (self::PRODUCT_CLAIM_INDICATORS as $pattern) if (preg_match($pattern, $sentenceContext) || preg_match($pattern, $ascii)) return 'product_technical_claim';
        return 'product_technical_claim';
    }

    public function extractSentenceContext(string $fullText, string $claimText): string
    {
        $position = mb_stripos($fullText, $claimText);
        if ($position === false) return $fullText;
        $before = mb_substr($fullText, 0, $position); $after = mb_substr($fullText, $position);
        $sentenceStart = 0;
        foreach (['.', '!', '?', "\n"] as $delimiter) {
            $delimiterPosition = mb_strrpos($before, $delimiter);
            if ($delimiterPosition !== false) {
                $sentenceStart = max($sentenceStart, $delimiterPosition + 1);
            }
        }
        $sentenceEnd = $position + mb_strlen($claimText);
        foreach (['.', '!', '?', "\n"] as $delimiter) {
            $pos = mb_strpos($after, $delimiter, mb_strlen($claimText));
            if ($pos !== false) { $sentenceEnd = min($sentenceEnd, $position + $pos + 1); break; }
        }
        return trim(mb_substr($fullText, $sentenceStart, $sentenceEnd - $sentenceStart));
    }

    public function isUnitDefinitionNumber(string $claimText, string $sentenceContext): bool
    {
        if (! preg_match('/^(\d+(?:[.,]\d+)?)\s*/u', trim($claimText), $match)) return false;
        if ((float) str_replace(',', '.', $match[1]) > 1.0) return false;
        $ascii = Str::ascii(Str::lower($sentenceContext));
        foreach ([
            '/(?:mỗi|moi)\s+1\s*/iu', '/1\s*(?:m[2²]|btu|kw|hp|pa|db)\s+(?:là|la|=|tương ứng|tuong ung)/iu',
            '/(?:đơn vị|don vi)\s+/iu', '/(?:quy đổi|quy doi|chuyển đổi|chuyen doi)/iu',
        ] as $pattern) if (preg_match($pattern, $sentenceContext) || preg_match($pattern, $ascii)) return true;
        return false;
    }
}
