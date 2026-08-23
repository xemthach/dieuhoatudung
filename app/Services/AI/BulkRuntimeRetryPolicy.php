<?php

namespace App\Services\AI;

final class BulkRuntimeRetryPolicy
{
    public const RETRYABLE = ['timeout', 'provider_timeout', '429', 'rate_limited', 'provider_5xx', 'temporary_provider_error'];
    public const TERMINAL = ['FACT_CHECK_BLOCKED', 'forbidden_payload', 'stale_context', 'invalid_product', 'invalid_prompt_contract'];

    public function classify(string $code): string
    {
        return in_array(strtolower($code), array_map('strtolower', self::RETRYABLE), true) ? 'RETRYABLE' : 'NON_RETRYABLE';
    }

    public function nextAttempt(int $attempt, int $maxAttempts, string $code): ?array
    {
        if ($this->classify($code) !== 'RETRYABLE' || $attempt >= $maxAttempts) return null;
        return ['attempt' => $attempt + 1, 'next_retry_at' => now()->addSeconds(min(300, 2 ** max(0, $attempt)))];
    }
}
