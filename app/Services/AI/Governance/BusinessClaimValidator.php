<?php

namespace App\Services\AI\Governance;

use App\Support\IssueList;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Validates business/admin-configured claims:
 * VAT, warranty, free installation, genuine product, CO/CQ, delivery, promotions.
 *
 * These claims are NOT technical specifications and must be validated
 * against admin settings, product config, and policy pages - not against
 * the HVAC technical fact registry.
 */
class BusinessClaimValidator
{
    /**
     * Business claim definitions with their detection patterns and sources.
     */
    private const BUSINESS_CLAIMS = [
        'vat' => [
            'patterns' => ['/\bvat\b/iu', '/\bVAT\b/u', '/thuế\s+giá\s+trị\s+gia\s+tăng/iu'],
            'ascii_patterns' => ['/\bvat\b/iu', '/thue\s+gia\s+tri\s+gia\s+tang/iu'],
            'source_keys' => ['product.vat_enabled', 'settings.vat_enabled'],
            'neutral_rewrite' => 'liên hệ để được tư vấn về chính sách giá',
        ],
        'mien_phi' => [
            'patterns' => ['/\bmiễn\s+phí\b/iu'],
            'ascii_patterns' => ['/\bmien\s+phi\b/iu'],
            'source_keys' => ['policy.free_claim'],
            'neutral_rewrite' => 'liên hệ để được tư vấn',
        ],
        'bao_hanh' => [
            'patterns' => ['/\bbảo\s+hành\b/iu'],
            'ascii_patterns' => ['/\bbao\s+hanh\b/iu'],
            'source_keys' => ['product.warranty_info'],
            'neutral_rewrite' => 'thông tin bảo hành theo chính sách nhà sản xuất',
        ],
        'chinh_hang' => [
            'patterns' => ['/\bchính\s+hãng\b/iu'],
            'ascii_patterns' => ['/\bchinh\s+hang\b/iu'],
            'source_keys' => ['policy.official_goods'],
            'neutral_rewrite' => 'theo thông tin sản phẩm đã lưu',
        ],
        'co_cq' => [
            'patterns' => ['/\b(?:co\/cq|co\s+cq|CO\/CQ)\b/iu'],
            'ascii_patterns' => ['/\b(?:co\/cq|co\s+cq)\b/iu'],
            'source_keys' => ['policy.co_cq'],
            'neutral_rewrite' => 'hồ sơ chứng từ cần xác nhận',
        ],
        'giao_hang' => [
            'patterns' => ['/\bgiao\s+hàng(?:\s+miễn\s+phí)?\b/iu'],
            'ascii_patterns' => ['/\bgiao\s+hang(?:\s+mien\s+phi)?\b/iu'],
            'source_keys' => ['policy.delivery', 'settings.delivery_policy'],
            'neutral_rewrite' => 'liên hệ để được tư vấn về giao hàng',
        ],
        'lap_dat' => [
            'patterns' => ['/\blắp\s+đặt(?:\s+miễn\s+phí)?\b/iu'],
            'ascii_patterns' => ['/\blap\s+dat(?:\s+mien\s+phi)?\b/iu'],
            'source_keys' => ['policy.installation', 'settings.installation_policy'],
            'neutral_rewrite' => 'liên hệ để được tư vấn về lắp đặt',
        ],
        'khuyen_mai' => [
            'patterns' => ['/\bkhuyến\s+mãi\b/iu'],
            'ascii_patterns' => ['/\bkhuyen\s+mai\b/iu'],
            'source_keys' => ['settings.promotion_active', 'campaign.active'],
            'neutral_rewrite' => 'liên hệ để biết chương trình ưu đãi hiện hành',
        ],
    ];

    /**
     * Validate business claims in text content.
     *
     * @return array{status: string, warnings: array, allowed_claims: array, rewrite_claims: array, log: array}
     */
    public function validate(string $text, array $context): array
    {
        $ascii = Str::ascii(Str::lower($text));
        $warnings = [];
        $allowedClaims = [];
        $rewriteClaims = [];
        $log = [];

        foreach (self::BUSINESS_CLAIMS as $code => $definition) {
            $detected = false;

            // Check with original text patterns
            foreach ($definition['patterns'] as $pattern) {
                if (preg_match($pattern, $text)) {
                    $detected = true;
                    break;
                }
            }

            // Check with ASCII patterns if not detected
            if (! $detected) {
                foreach ($definition['ascii_patterns'] as $pattern) {
                    if (preg_match($pattern, $ascii)) {
                        $detected = true;
                        break;
                    }
                }
            }

            if (! $detected) {
                continue;
            }

            // Check if allowed by admin source
            $allowed = $this->isAllowedBySource($definition['source_keys'], $context);

            if ($allowed) {
                $sourceKey = $this->findActiveSourceKey($definition['source_keys'], $context);
                $allowedClaims[] = $code;
                $log[] = [
                    'validator' => 'BusinessClaimValidator',
                    'claim' => $code,
                    'source' => $sourceKey,
                    'action' => 'allowed',
                ];
            } else {
                $rewriteClaims[] = [
                    'code' => $code,
                    'rewrite_to' => $definition['neutral_rewrite'],
                ];
                $warnings[] = 'business_claim_needs_rewrite:'.$code;
                $log[] = [
                    'validator' => 'BusinessClaimValidator',
                    'claim' => $code,
                    'source' => 'no_admin_source_found',
                    'action' => 'rewrite_to_neutral',
                ];
            }
        }

        return [
            'status' => 'verified', // Business claims never hard-block
            'warnings' => IssueList::normalize($warnings),
            'allowed_claims' => $allowedClaims,
            'rewrite_claims' => $rewriteClaims,
            'log' => $log,
        ];
    }

    /**
     * Rewrite unverified business claims in text to neutral statements.
     */
    public function rewriteText(string $text, array $context): array
    {
        $result = $this->validate($text, $context);
        $rewritten = [];

        foreach ($result['rewrite_claims'] as $claim) {
            $code = $claim['code'];
            $definition = self::BUSINESS_CLAIMS[$code] ?? null;
            if (! $definition) {
                continue;
            }

            foreach ($definition['patterns'] as $pattern) {
                $newText = preg_replace($pattern, $claim['rewrite_to'], $text);
                if ($newText !== null && $newText !== $text) {
                    $text = $newText;
                    $rewritten[] = $code;
                    break;
                }
            }

            // Try ASCII patterns if original patterns didn't match
            if (! in_array($code, $rewritten, true)) {
                foreach ($definition['ascii_patterns'] as $pattern) {
                    $newText = preg_replace($pattern, $claim['rewrite_to'], $text);
                    if ($newText !== null && $newText !== $text) {
                        $text = $newText;
                        $rewritten[] = $code;
                        break;
                    }
                }
            }
        }

        return [
            'text' => $text,
            'rewritten_claims' => array_values(array_unique($rewritten)),
        ];
    }

    /**
     * Check if the business claim is allowed by any admin source.
     */
    private function isAllowedBySource(array $sourceKeys, array $context): bool
    {
        foreach ($sourceKeys as $key) {
            $value = Arr::get($context, 'allowed_facts.'.$key.'.value');
            if ($this->sourceValueAllows($value)) {
                return true;
            }

            // Check verified fact registry
            foreach ((array) Arr::get($context, 'verified_fact_registry', []) as $fact) {
                if (($fact['fact_key'] ?? null) === $key && $this->sourceValueAllows($fact['original_value'] ?? null)) {
                    return true;
                }
            }
        }

        // Also check the ai_claim_rules config for allow_if keys
        foreach ($sourceKeys as $sourceKey) {
            $configKey = str_replace(['product.', 'settings.', 'policy.', 'campaign.'], '', $sourceKey);
            $rule = (array) config('ai_claim_rules.claims.'.$configKey, []);

            foreach ((array) ($rule['allow_if'] ?? []) as $allowKey) {
                $value = Arr::get($context, 'allowed_facts.'.$allowKey.'.value');
                if ($this->sourceValueAllows($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find which source key is active.
     */
    private function findActiveSourceKey(array $sourceKeys, array $context): string
    {
        foreach ($sourceKeys as $key) {
            $value = Arr::get($context, 'allowed_facts.'.$key.'.value');
            if ($this->sourceValueAllows($value)) {
                return 'admin_option.'.$key;
            }
        }

        return 'config_rule';
    }

    private function sourceValueAllows(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filled($value);
    }
}
