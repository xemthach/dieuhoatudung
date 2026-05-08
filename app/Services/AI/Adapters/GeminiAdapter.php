<?php

namespace App\Services\AI\Adapters;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;

class GeminiAdapter implements AIAdapterInterface
{
    public function testConnection(AiProvider $provider): array
    {
        $endpoint = $provider->endpoint ?: 'https://generativelanguage.googleapis.com/v1beta/models/';
        $url = rtrim($endpoint, '/') . '/' . $provider->model . ':generateContent?key=' . $provider->api_key;

        try {
            $response = Http::timeout(10)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Hello. Reply with only the word "OK"']
                        ]
                    ]
                ]
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
        $endpoint = $provider->endpoint ?: 'https://generativelanguage.googleapis.com/v1beta/models/';
        $url = rtrim($endpoint, '/') . '/' . $provider->model . ':generateContent?key=' . $provider->api_key;

        $prompt = "";
        if (!empty($payload['system'])) {
            $prompt .= "System: " . $payload['system'] . "\n\n";
        }
        if (!empty($payload['prompt'])) {
            $prompt .= $payload['prompt'] . "\n\n";
        }
        if (!empty($payload['input'])) {
            $prompt .= "Input:\n" . $payload['input'];
        }

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => trim($prompt)]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $payload['temperature'] ?? 0.7,
            ]
        ];

        if (!empty($options['require_json'])) {
            // Gemini expects response_mime_type
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }

        $start = microtime(true);
        $response = Http::timeout(120)->post($url, $body);
        $latency = (int) ((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            $isRateLimit = $response->status() === 429;
            $isAuthError = in_array($response->status(), [401, 403]);
            
            throw new \Exception(json_encode([
                'message' => 'Gemini API Error: ' . $response->body(),
                'status' => $response->status(),
                'is_rate_limit' => $isRateLimit,
                'is_auth_error' => $isAuthError,
            ]));
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $tokens = $data['usageMetadata']['totalTokenCount'] ?? 0;

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
