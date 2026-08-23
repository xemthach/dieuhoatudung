<?php

namespace Tests\Unit;

use App\Services\AI\AIRuntimePolicyService;
use Tests\TestCase;

class AIRuntimePolicyServiceTest extends TestCase
{
    public function test_policy_snapshot_comes_from_runtime_config_without_secrets(): void
    {
        config()->set('ai.production', [
            'provider' => 'custom',
            'model' => 'gemini-2.5-flash',
            'request_timeout_seconds' => 120,
            'max_attempts' => 3,
            'max_retries' => 2,
            'allow_fallback' => false,
            'prompt_version' => 'ai-product-content-layer-v2',
            'governance_version' => 'verified-facts-v1',
        ]);
        config()->set('ai.governed_queue', 'ai_governed');

        $snapshot = app(AIRuntimePolicyService::class)->snapshot();

        $this->assertSame('custom', $snapshot['provider']);
        $this->assertSame('gemini-2.5-flash', $snapshot['model']);
        $this->assertSame(120, $snapshot['request_timeout_seconds']);
        $this->assertSame(3, $snapshot['max_attempts']);
        $this->assertSame(2, $snapshot['max_retries']);
        $this->assertSame('disabled', $snapshot['fallback']);
        $this->assertSame('ai_governed', $snapshot['worker_queue']);
        $this->assertNotContains('api_key', array_keys($snapshot));
        $this->assertNotContains('password', array_keys($snapshot));
    }
}
