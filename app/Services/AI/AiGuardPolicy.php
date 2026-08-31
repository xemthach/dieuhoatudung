<?php

namespace App\Services\AI;

use App\Services\Settings\SettingService;

/**
 * Single source of truth for Product-AI guard effects.
 * Locked guards never honour a persisted WARN/IGNORE value.
 */
final class AiGuardPolicy
{
    public const BLOCK = 'BLOCK';
    public const WARN = 'WARN';
    public const IGNORE = 'IGNORE';

    private const LOCKED_PREFIXES = [
        'AUTHORIZATION', 'PERMISSION', 'STALE_', 'DUPLICATE_',
        'ACTIVE_DRAFT_OR_APPLY_CONFLICT', 'APPLY_', 'FACT_CHECK_BLOCKED',
        'CONTRADICTED_', 'AMBIGUOUS_', 'UNSAFE_', 'FORBIDDEN_',
        'PARSER_', 'INVALID_', 'CROSS_PRODUCT_',
    ];

    private const CONFIGURABLE_CODES = [
        'CONTENT_TOO_SHORT', 'MISSING_H2_H3', 'MISSING_SEO', 'MISSING_MERCHANT', 'MISSING_FAQ',
    ];

    public function __construct(private readonly SettingService $settings) {}

    /** @return array{code:string,effect:string,overrideable:bool,source:string} */
    public function evaluate(string $code): array
    {
        $code = strtoupper(trim(explode(':', $code, 2)[0]));
        if ($this->locked($code)) {
            return compact('code') + ['effect' => self::BLOCK, 'overrideable' => false, 'source' => 'SYSTEM'];
        }

        $default = self::WARN;
        $configured = strtoupper((string) $this->settings->get('ai_guard_policy.'.$code, $default));
        $effect = in_array($configured, [self::BLOCK, self::WARN, self::IGNORE], true) ? $configured : $default;

        return compact('code', 'effect') + ['overrideable' => true, 'source' => $effect === $default ? 'DEFAULT' : 'ADMIN_CONFIG'];
    }

    /** @return array<string,string> */
    public function snapshot(): array
    {
        return collect(self::CONFIGURABLE_CODES)
            ->mapWithKeys(fn (string $code): array => [$code => $this->evaluate($code)['effect']])
            ->all();
    }

    public function version(): string
    {
        return 'ai-guard-policy-v1:'.substr(hash('sha256', json_encode($this->snapshot(), JSON_UNESCAPED_SLASHES)), 0, 12);
    }

    public function locked(string $code): bool
    {
        foreach (self::LOCKED_PREFIXES as $prefix) {
            if ($code === $prefix || str_starts_with($code, $prefix)) return true;
        }
        return false;
    }

    private function editorial(string $code): bool
    {
        return str_starts_with($code, 'CONTENT_TOO_SHORT')
            || in_array($code, ['MISSING_CONTENT', 'MISSING_H2_H3', 'MISSING_SEO', 'MISSING_MERCHANT', 'MISSING_FAQ'], true)
            || str_starts_with($code, 'MISSING_')
            || str_starts_with($code, 'UNVERIFIED_')
            || str_starts_with($code, 'BUSINESS_CLAIM_NEEDS_REWRITE');
    }
}
