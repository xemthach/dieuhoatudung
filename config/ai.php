<?php

return [
    // Legacy rows remain on `ai`; only governed work is consumed from this queue.
    'governed_queue' => env('AI_GOVERNED_QUEUE', 'ai_governed'),
    // One canonical desired-state file. Tests override only this path so an
    // isolated state transition can never wake a real managed worker.
    'worker_desired_state_path' => storage_path('framework/cache/ai-worker-desired-state.json'),
    'managed_state_directory' => storage_path('framework/cache'),
    // This is an explicit provider-side ceiling, not a token usage estimate.
    'hard_budget_default_max_output_tokens' => (int) env('AI_HARD_BUDGET_MAX_OUTPUT_TOKENS', 12000),
    'token_estimator_bytes_per_token' => 4,
    'production' => [
        'provider' => env('AI_PRODUCTION_PROVIDER', 'custom'),
        'model' => env('AI_PRODUCTION_MODEL', 'gemini-2.5-flash'),
        'prompt_version' => env('AI_PRODUCTION_PROMPT_VERSION', 'ai-product-content-layer-v2'),
        'governance_version' => env('AI_PRODUCTION_GOVERNANCE_VERSION', 'verified-facts-v1'),
        'request_timeout_seconds' => (int) env('AI_PRODUCTION_REQUEST_TIMEOUT', 120),
        'connect_timeout_seconds' => (int) env('AI_PRODUCTION_CONNECT_TIMEOUT', 10),
        'max_attempts' => (int) env('AI_PRODUCTION_MAX_ATTEMPTS', 3),
        'max_retries' => (int) env('AI_PRODUCTION_MAX_RETRIES', 2),
        'allow_fallback' => filter_var(env('AI_PRODUCTION_ALLOW_FALLBACK', false), FILTER_VALIDATE_BOOL),
        'fallback_allowlist' => [],
    ],
    // Explicit, auditable exception for a deployment operated by one person.
    // This never enables a worker or performs an AI action by itself.
    'single_operator' => [
        'enabled' => filter_var(env('AI_SINGLE_OPERATOR_ENABLED', false), FILTER_VALIDATE_BOOL),
        'operator_user_id' => (int) env('AI_SINGLE_OPERATOR_USER_ID', 0),
        'policy' => 'SINGLE_OPERATOR_CONTROLLED_ROLLOUT',
        'super_admin_exception' => filter_var(env('AI_SINGLE_OPERATOR_SUPER_ADMIN_EXCEPTION', false), FILTER_VALIDATE_BOOL),
        'enforce_in_testing' => filter_var(env('AI_SINGLE_OPERATOR_ENFORCE_IN_TESTING', false), FILTER_VALIDATE_BOOL),
        'max_stage1_targets' => 1,
        'max_stage2_targets' => 2,
        'max_stage3_targets' => 5,
        'auto_approve' => false,
        'auto_apply' => false,
    ],
    'alerts' => [
        'cooldown_seconds' => (int) env('AI_ALERT_COOLDOWN_SECONDS', 300),
    ],
];
