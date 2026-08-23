<?php

namespace App\Services\AI;

final class AIRuntimePolicyService
{
    /**
     * Read-only operational policy shared by runtime execution and admin UI.
     */
    public function snapshot(): array
    {
        $production = (array) config('ai.production', []);

        return [
            'provider' => (string) ($production['provider'] ?? ''),
            'model' => (string) ($production['model'] ?? ''),
            'request_timeout_seconds' => (int) ($production['request_timeout_seconds'] ?? 0),
            'max_attempts' => (int) ($production['max_attempts'] ?? 0),
            'max_retries' => (int) ($production['max_retries'] ?? 0),
            'fallback' => ! empty($production['allow_fallback']) ? 'enabled' : 'disabled',
            'prompt_version' => (string) ($production['prompt_version'] ?? ''),
            'governance_version' => (string) ($production['governance_version'] ?? ''),
            'worker_queue' => (string) config('ai.governed_queue', 'ai_governed'),
            'hard_budget_mode' => 'enforced',
            'single_operator_policy' => (string) config('ai.single_operator.policy', 'SINGLE_OPERATOR_CONTROLLED_ROLLOUT'),
            'single_operator_active' => (bool) config('ai.single_operator.enabled', false),
            'single_operator_auto_approve' => (bool) config('ai.single_operator.auto_approve', false),
            'single_operator_auto_apply' => (bool) config('ai.single_operator.auto_apply', false),
        ];
    }
}
