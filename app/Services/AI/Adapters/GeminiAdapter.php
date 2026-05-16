<?php

namespace App\Services\AI\Adapters;

use App\Models\AiProvider;
use App\Services\AI\AIJsonResponseParser;
use App\Support\EncodingGuard;
use Illuminate\Support\Facades\Http;

class GeminiAdapter implements AIAdapterInterface
{
    public function testConnection(AiProvider $provider): array
    {
        try {
            $response = $this->request($provider, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => 'Hello. Reply with only the word "OK"'],
                        ],
                    ],
                ],
            ], 10);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected successfully'];
            }

            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'Rate limited', 'rate_limited' => true];
            }

            return ['success' => false, 'message' => 'HTTP '.$response->status().': '.$response->body()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generate(AiProvider $provider, array $payload, array $options = []): array
    {
        $prompt = '';
        if (! empty($payload['system'])) {
            $prompt .= 'System: '.$payload['system']."\n\n";
        }
        if (! empty($payload['prompt'])) {
            $prompt .= $payload['prompt']."\n\n";
        }
        if (! empty($payload['input'])) {
            $prompt .= "Input:\n".$payload['input'];
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => trim($prompt)],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $payload['temperature'] ?? 0.7,
            ],
        ];

        if (! empty($options['require_json'])) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }

        $maxOutputTokens = $payload['max_output_tokens']
            ?? $payload['max_tokens']
            ?? $options['max_output_tokens']
            ?? $options['max_tokens']
            ?? null;
        if ($maxOutputTokens) {
            $body['generationConfig']['maxOutputTokens'] = (int) $maxOutputTokens;
        }

        $start = microtime(true);
        $response = $this->request($provider, $body, 120);
        $latency = (int) ((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            throw new \Exception(EncodingGuard::jsonEncode([
                'message' => 'Gemini API Error: '.$response->body(),
                'status' => $response->status(),
                'is_rate_limit' => $response->status() === 429,
                'is_auth_error' => in_array($response->status(), [401, 403]),
            ]));
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'content' => $text,
            'json' => app(AIJsonResponseParser::class)->parse($text, ! empty($options['require_json'])),
            'tokens_used' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            'latency_ms' => $latency,
        ];
    }

    private function request(AiProvider $provider, array $body, int $timeout)
    {
        $url = $this->endpoint($provider);
        $request = Http::withHeaders($this->headers($provider))->timeout($timeout);

        return $request->post($url, $body);
    }

    private function endpoint(AiProvider $provider): string
    {
        $endpoint = trim((string) ($provider->endpoint ?: 'https://generativelanguage.googleapis.com/v1beta/models/'));
        $endpoint = rtrim($endpoint, '/');
        $model = rawurlencode($provider->model);

        if ($this->usesGoogleApiKeyAuth($endpoint)) {
            return $endpoint.'/'.$model.':generateContent?key='.urlencode((string) $provider->api_key);
        }

        if (str_contains($endpoint, ':generateContent')) {
            return $endpoint;
        }

        if (preg_match('#/v1beta/models/[^/]+$#', $endpoint)) {
            return $endpoint.':generateContent';
        }

        if (str_ends_with($endpoint, '/v1beta/models')) {
            return $endpoint.'/'.$model.':generateContent';
        }

        return $endpoint.'/v1beta/models/'.$model.':generateContent';
    }

    private function headers(AiProvider $provider): array
    {
        $headers = ['Accept' => 'application/json'];

        if (! $this->usesGoogleApiKeyAuth((string) $provider->endpoint) && ! empty($provider->api_key)) {
            $headers['Authorization'] = 'Bearer '.$provider->api_key;
        }

        return $headers;
    }

    private function usesGoogleApiKeyAuth(string $endpoint): bool
    {
        return $endpoint === '' || str_contains($endpoint, 'generativelanguage.googleapis.com');
    }

}
