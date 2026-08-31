<?php

namespace App\Services\AI;

use App\Models\Product;
use App\Services\AI\Governance\HVACUnitNormalizer;
use App\Services\AI\Governance\VerifiedFactRegistry;

final class AiProductWarningClassifier
{
    public function __construct(
        private readonly VerifiedFactRegistry $facts,
        private readonly HVACUnitNormalizer $units,
    ) {}

    /** @return array{soft_warnings:array<int,array<string,string>>,optional_data:array<int,array<string,string>>,hard_blockers:array<int,array<string,string>>,technical_processed:array<int,array<string,string>>,informational:array<int,array<string,string>>,counts:array<string,int>} */
    public function classify(array $warnings, array $payload, ?Product $product = null): array
    {
        $soft = [];
        $optional = [];
        $hard = [];
        $processed = [];
        $informational = [];
        $content = $this->applicableText($payload);
        $registry = null;

        foreach (array_values(array_unique(array_filter(array_map('strval', $warnings)))) as $code) {
            [$type, $label] = $this->describe($code);

            if (in_array($code, ['encoding_checked', 'vietnamese_verified'], true)) {
                $informational[] = ['code' => $code, 'type' => 'VALIDATION_EVIDENCE', 'label' => $label];
                continue;
            }

            if ($this->isOptionalDataWarning($code)) {
                $optional[] = ['code' => $code, 'type' => 'OPTIONAL_DATA_WARNING', 'label' => $this->optionalDataLabel($code)];
                continue;
            }

            if (str_starts_with($code, 'unverified_used_fact:') && $product) {
                $registry ??= $this->facts->buildForProduct($product);
                $reference = trim(substr($code, strlen('unverified_used_fact:')));
                if ($this->factReferenceExists($registry, $reference)) {
                    $processed[] = ['code' => $code, 'type' => 'TECHNICAL_FACT_WARNING', 'label' => "Nguồn kỹ thuật hiện đã được xác minh: {$reference}"];
                    continue;
                }
            }

            if (str_starts_with($code, 'unverified_technical_claim:')) {
                $claim = trim(substr($code, strlen('unverified_technical_claim:')));
                if ($product) {
                    $registry ??= $this->facts->buildForProduct($product);
                }
                if ($product && $this->technicalClaimIsVerified($registry ?? [], $claim)) {
                    $processed[] = ['code' => $code, 'type' => 'TECHNICAL_FACT_WARNING', 'label' => "Thông số đã được đối chiếu với dữ liệu catalog hiện tại: {$claim}"];
                } elseif ($claim !== '' && mb_stripos($content, $claim) !== false) {
                    $hard[] = ['code' => $code, 'type' => 'TECHNICAL_FACT_BLOCK', 'label' => "Thông số chưa được xác minh vẫn còn trong nội dung: {$claim}"];
                } else {
                    $processed[] = ['code' => $code, 'type' => 'TECHNICAL_FACT_WARNING', 'label' => "Thông số chưa xác minh đã được loại khỏi nội dung: {$claim}"];
                }
                continue;
            }

            $entry = ['code' => $code, 'type' => $type, 'label' => $label];
            if ($type === 'TECHNICAL_FACT_BLOCK') {
                $hard[] = $entry;
            } elseif (str_contains($code, '_removed:') || str_contains($code, '_rewritten:')) {
                $processed[] = $entry;
            } else {
                $soft[] = $entry;
            }
        }

        foreach ((array) ($payload['blocked_claims'] ?? data_get($payload, 'fact_check.blocked_claims', [])) as $claim) {
            $code = (string) $claim;
            $hard[] = ['code' => $code, 'type' => 'TECHNICAL_FACT_BLOCK', 'label' => 'Fact-check kỹ thuật đang chặn: '.$code];
        }

        $hard = $this->unique($hard);
        $soft = $this->unique($soft);
        $optional = $this->unique($optional);
        $processed = $this->unique($processed);
        $informational = $this->unique($informational);

        return [
            'soft_warnings' => $soft,
            'optional_data' => $optional,
            'hard_blockers' => $hard,
            'technical_processed' => $processed,
            'informational' => $informational,
            'counts' => [
                'soft' => count($soft),
                'optional' => count($optional),
                'technical_processed' => count($processed),
                'hard' => count($hard),
                'informational' => count($informational),
            ],
        ];
    }

    private function isOptionalDataWarning(string $code): bool
    {
        return str_starts_with($code, 'missing_')
            && ! in_array($code, ['missing_content', 'missing_h2_h3', 'missing_seo', 'missing_merchant', 'missing_faq'], true);
    }

    private function optionalDataLabel(string $code): string
    {
        return match ($code) {
            'missing_refrigerant' => 'Chưa có dữ liệu môi chất lạnh',
            'missing_recommended_area' => 'Chưa có dữ liệu diện tích khuyến nghị',
            'missing_warranty_policy' => 'Chưa có dữ liệu chính sách bảo hành',
            'missing_price' => 'Chưa có dữ liệu giá',
            default => 'Thiếu dữ liệu tùy chọn: '.str_replace('_', ' ', substr($code, strlen('missing_'))),
        };
    }

    /** @return array{string,string} */
    private function describe(string $code): array
    {
        return match (true) {
            str_starts_with($code, 'content_too_short') => ['EDITORIAL_WARNING', 'Nội dung ngắn hơn mức khuyến nghị'],
            $code === 'encoding_checked' => ['VALIDATION_EVIDENCE', 'Mã hóa UTF-8 đã được kiểm tra'],
            $code === 'vietnamese_verified' => ['VALIDATION_EVIDENCE', 'Tiếng Việt có dấu đã được kiểm tra'],
            $code === 'missing_content' => ['EDITORIAL_WARNING', 'Thiếu nội dung chính'],
            $code === 'missing_h2_h3' => ['EDITORIAL_WARNING', 'Thiếu cấu trúc H2/H3'],
            $code === 'missing_seo' => ['EDITORIAL_WARNING', 'Thiếu nội dung SEO'],
            $code === 'missing_merchant' => ['EDITORIAL_WARNING', 'Thiếu nội dung Google Merchant'],
            $code === 'missing_faq' => ['EDITORIAL_WARNING', 'Thiếu FAQ'],
            str_starts_with($code, 'unverified_used_fact:') => ['TECHNICAL_FACT_WARNING', 'Nguồn dữ liệu kỹ thuật chưa được xác minh: '.substr($code, strlen('unverified_used_fact:'))],
            str_starts_with($code, 'unverified_claim_removed:') => ['TECHNICAL_FACT_WARNING', 'Claim chưa xác minh đã được loại bỏ: '.substr($code, strlen('unverified_claim_removed:'))],
            str_starts_with($code, 'unverified_claim_rewritten:') => ['TECHNICAL_FACT_WARNING', 'Claim chưa xác minh đã được viết lại: '.substr($code, strlen('unverified_claim_rewritten:'))],
            str_starts_with($code, 'unverified_technical_claim:') => ['TECHNICAL_FACT_WARNING', 'Thông số kỹ thuật chưa được xác minh'],
            str_starts_with($code, 'business_claim_needs_rewrite:') => ['EDITORIAL_WARNING', 'Claim thương mại cần biên tập lại: '.substr($code, strlen('business_claim_needs_rewrite:'))],
            str_contains($code, 'STALE_') => ['CONCURRENCY_BLOCK', 'Dữ liệu đích đã thay đổi'],
            str_contains($code, 'PARSE_') || str_contains($code, 'SYSTEM_') => ['SYSTEM_ERROR', 'Lỗi hệ thống khi xử lý bản nháp'],
            default => ['EDITORIAL_WARNING', str_replace('_', ' ', $code)],
        };
    }

    private function technicalClaimIsVerified(array $registry, string $claim): bool
    {
        $claims = $this->units->extractTechnicalClaims($claim, $claim);

        return $claims !== [] && collect($claims)->every(
            fn (array $item): bool => $this->facts->findMatchingFact($registry, $item) !== null,
        );
    }

    private function factReferenceExists(array $registry, string $reference): bool
    {
        $needle = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $reference) ?? '', '_'));
        if ($needle === '') {
            return false;
        }

        return collect($registry)->contains(function (array $fact) use ($needle): bool {
            $tokens = [
                (string) ($fact['fact_key'] ?? ''),
                (string) ($fact['normalized_key'] ?? ''),
                (string) ($fact['label'] ?? ''),
            ];

            foreach ($tokens as $token) {
                $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $token) ?? '', '_'));
                if ($normalized === $needle || str_ends_with($normalized, '_'.$needle)) {
                    return true;
                }
            }

            return false;
        });
    }

    private function applicableText(array $payload): string
    {
        $values = [];
        foreach (['excerpt', 'content_html', 'seo_title', 'meta_description', 'og_title', 'og_description', 'merchant_title', 'merchant_description', 'tags', 'faq'] as $field) {
            if (array_key_exists($field, $payload)) {
                $values[] = is_scalar($payload[$field]) ? (string) $payload[$field] : (string) json_encode($payload[$field], JSON_UNESCAPED_UNICODE);
            }
        }

        return html_entity_decode(strip_tags(implode(' ', $values)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function unique(array $entries): array
    {
        return collect($entries)->unique('code')->values()->all();
    }
}
