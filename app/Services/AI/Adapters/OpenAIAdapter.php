<?php

namespace App\Services\AI\Adapters;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;

class OpenAIAdapter implements AIAdapterInterface
{
    public function testConnection(AiProvider $provider): array
    {
        $endpoint = $provider->endpoint ?: 'https://api.openai.com/v1/chat/completions';
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $provider->api_key,
            ])->timeout(10)->post($endpoint, [
                'model' => $provider->model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello. Reply with only the word "OK"']
                ],
                'max_tokens' => 10,
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected successfully'];
            }

            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'Rate limited', 'rate_limited' => true];
            }

            return ['success' => false, 'message' => 'HTTP ' . $response->status() . ': ' . $response->body()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generate(AiProvider $provider, array $payload, array $options = []): array
    {
        $endpoint = $provider->endpoint ?: 'https://api.openai.com/v1/chat/completions';
        
        $messages = [];
        if (!empty($payload['system'])) {
            $messages[] = ['role' => 'system', 'content' => $payload['system']];
        }
        
        $userPrompt = "";
        if (!empty($payload['prompt'])) {
            $userPrompt .= $payload['prompt'] . "\n\n";
        }
        if (!empty($payload['input'])) {
            $userPrompt .= "Input:\n" . $payload['input'];
        }
        
        if (!empty($userPrompt)) {
            $messages[] = ['role' => 'user', 'content' => trim($userPrompt)];
        }

        $body = [
            'model' => $provider->model,
            'messages' => $messages,
            'temperature' => $payload['temperature'] ?? 0.7,
        ];

        if (!empty($options['require_json']) && $provider->supports_json_mode) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $headers = [];
        if (!empty($provider->api_key)) {
            $headers['Authorization'] = 'Bearer ' . $provider->api_key;
        }

        $start = microtime(true);
        $response = Http::withHeaders($headers)->timeout(120)->post($endpoint, $body);
        $latency = (int) ((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            $isRateLimit = $response->status() === 429;
            $isAuthError = in_array($response->status(), [401, 403]);
            
            throw new \Exception(json_encode([
                'message' => 'OpenAI API Error: ' . $response->body(),
                'status' => $response->status(),
                'is_rate_limit' => $isRateLimit,
                'is_auth_error' => $isAuthError,
            ]));
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? '';
        $tokens = $data['usage']['total_tokens'] ?? 0;

        $json = [];
        if (!empty($options['require_json'])) {
            if (preg_match('/\[.*\]|\{.*\}/s', $text, $matches)) {
                $json = json_decode($matches[0], true) ?? [];
            }
        }

        return [
            'content' => $text,
            'json' => $json,
            'tokens_used' => $tokens,
            'latency_ms' => $latency,
        ];
    }
}
