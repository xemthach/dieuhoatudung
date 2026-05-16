<?php

namespace App\Services\AI\Adapters;

use App\Models\AiProvider;
use App\Services\AI\AIJsonResponseParser;
use App\Support\EncodingGuard;
use Illuminate\Support\Facades\Http;

class ClaudeAdapter implements AIAdapterInterface
{
    public function testConnection(AiProvider $provider): array
    {
        try {
            $response = Http::withHeaders($this->headers($provider))
                ->timeout(10)
                ->post($this->endpoint($provider), [
                    'model' => $provider->model,
                    'max_tokens' => 10,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello. Reply with only the word "OK"'],
                    ],
                ]);

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
        $userPrompt = '';
        if (! empty($payload['prompt'])) {
            $userPrompt .= $payload['prompt']."\n\n";
        }
        if (! empty($payload['input'])) {
            $userPrompt .= "Input:\n".$payload['input'];
        }

        $body = [
            'model' => $provider->model,
            'max_tokens' => $payload['max_tokens'] ?? $options['max_tokens'] ?? 4096,
            'messages' => [
                ['role' => 'user', 'content' => trim($userPrompt)],
            ],
            'temperature' => $payload['temperature'] ?? 0.7,
        ];

        if (! empty($payload['system'])) {
            $body['system'] = $payload['system'];
        }

        $start = microtime(true);
        $response = Http::withHeaders($this->headers($provider))
            ->timeout(120)
            ->post($this->endpoint($provider), $body);
        $latency = (int) ((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            throw new \Exception(EncodingGuard::jsonEncode([
                'message' => 'Claude API Error: '.$response->body(),
                'status' => $response->status(),
                'is_rate_limit' => $response->status() === 429,
                'is_auth_error' => in_array($response->status(), [401, 403]),
            ]));
        }

        $data = $response->json();
        $text = $this->extractText($data);
        $tokens = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        return [
            'content' => $text,
            'json' => app(AIJsonResponseParser::class)->parse($text, ! empty($options['require_json'])),
            'tokens_used' => $tokens,
            'latency_ms' => $latency,
        ];
    }

    private function endpoint(AiProvider $provider): string
    {
        $endpoint = trim((string) ($provider->endpoint ?: 'https://api.anthropic.com/v1/messages'));
        $endpoint = rtrim($endpoint, '/');

        if (str_ends_with($endpoint, '/messages')) {
            return $endpoint;
        }

        if (str_ends_with($endpoint, '/v1')) {
            return $endpoint.'/messages';
        }

        return $endpoint.'/v1/messages';
    }

    private function headers(AiProvider $provider): array
    {
        $headers = [
            'Accept' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ];

        if (! empty($provider->api_key)) {
            $headers['Authorization'] = 'Bearer '.$provider->api_key;
            $headers['x-api-key'] = $provider->api_key;
        }

        return $headers;
    }

    private function extractText(array $data): string
    {
        $chunks = [];

        foreach ($data['content'] ?? [] as $part) {
            if (($part['type'] ?? null) === 'text' && isset($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        return trim(implode("\n", $chunks));
    }

}
