<?php

namespace App\Services\AI;

use App\Models\AiProvider;
use RuntimeException;

/**
 * Builds the worst-case token exposure for one provider request.
 *
 * The estimator is deliberately conservative and deterministic. It is not a
 * billing calculator: it is the pre-dispatch safety envelope.
 */
class BulkRuntimeTokenEnvelopeService
{
    public function forPayload(array $payload, ?AiProvider $provider = null, array $options = []): array
    {
        $capability = $this->capability($provider);
        if (! $capability['supports_output_token_limit']) {
            throw new RuntimeException('HARD_TOKEN_BUDGET_UNENFORCEABLE');
        }

        $maxOutput = (int) ($payload['max_tokens']
            ?? $payload['max_output_tokens']
            ?? $options['max_tokens']
            ?? $options['max_output_tokens']
            ?? config('ai.hard_budget_default_max_output_tokens', 12000));
        if ($maxOutput < 1) {
            throw new RuntimeException('HARD_TOKEN_BUDGET_UNENFORCEABLE');
        }

        $serialized = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $bytesPerToken = max(1, (int) config('ai.token_estimator_bytes_per_token', 4));
        $input = max(1, (int) ceil(strlen($serialized) / $bytesPerToken));

        return [
            'estimated_input_tokens' => $input,
            'effective_max_output_tokens' => $maxOutput,
            'reservation_envelope' => $input + $maxOutput,
            'provider_parameter' => $capability['output_parameter'],
            'provider' => $provider?->provider,
            'model' => $provider?->model,
            'estimator' => 'canonical_json_bytes_divided_by_'. $bytesPerToken,
        ];
    }

    public function capability(?AiProvider $provider): array
    {
        $kind = (string) ($provider?->provider ?? config('ai.default_provider', 'custom'));
        return match ($kind) {
            'custom', 'openai', 'groq', 'ollama' => [
                'supports_output_token_limit' => true,
                'output_parameter' => 'max_tokens',
            ],
            'gemini' => [
                'supports_output_token_limit' => true,
                'output_parameter' => 'max_output_tokens',
            ],
            'claude' => [
                'supports_output_token_limit' => true,
                'output_parameter' => 'max_tokens',
            ],
            default => [
                'supports_output_token_limit' => false,
                'output_parameter' => null,
            ],
        };
    }
}
