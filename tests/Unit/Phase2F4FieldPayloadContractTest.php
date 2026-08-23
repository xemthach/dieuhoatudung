<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\AI\Adapters\OpenAIAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase2F4FieldPayloadContractTest extends TestCase
{
    public function test_field_retry_payload_builds_valid_chat_messages(): void
    {
        $captured = null;
        Http::fake(function ($request) use (&$captured) {
            $captured = $request->data();
            return Http::response(['choices' => [['message' => ['content' => '{"content_html":"<h2>ok</h2>"}']]], 'usage' => ['total_tokens' => 12]], 200);
        });

        $provider = new AiProvider(['provider' => 'custom', 'model' => 'gemini-2.5-flash', 'endpoint' => 'https://example.test', 'api_key' => 'redacted-test-key', 'supports_json_mode' => true]);
        app(OpenAIAdapter::class)->generate($provider, [
            'system' => 'You are a governed HVAC content editor. Return JSON only.',
            'prompt' => 'Generate only content_html; minimum 800 words.',
            'input' => 'VERIFIED CONTEXT: product 1237',
        ], ['require_json' => true]);

        $this->assertSame('system', $captured['messages'][0]['role']);
        $this->assertSame('user', $captured['messages'][1]['role']);
        $this->assertStringContainsString('Generate only content_html', $captured['messages'][1]['content']);
        $this->assertStringContainsString('VERIFIED CONTEXT', $captured['messages'][1]['content']);
        $this->assertSame(['type' => 'json_object'], $captured['response_format']);
    }
}
