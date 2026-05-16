<?php

namespace App\Services\AI\Adapters;

use App\Models\AiProvider;
use App\Services\AI\AIJsonResponseParser;
use App\Support\EncodingGuard;
use Illuminate\Support\Facades\Http;

class OpenAIAdapter implements AIAdapterInterface
{
    public function testConnection(AiProvider $provider): array
    {
        try {
            $response = Http::withHeaders($this->headers($provider))
                ->timeout(10)
                ->post($this->endpoint($provider), [
                    'model' => $provider->model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello. Reply with only the word "OK"'],
                    ],
                    'max_tokens' => 10,
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
        $messages = [];
        if (! empty($payload['system'])) {
            $messages[] = ['role' => 'system', 'content' => $payload['system']];
        }

        $userPrompt = '';
        if (! empty($payload['prompt'])) {
            $userPrompt .= $payload['prompt']."\n\n";
        }
        if (! empty($payload['input'])) {
            $userPrompt .= "Input:\n".$payload['input'];
        }

        if (! empty($userPrompt)) {
            $messages[] = ['role' => 'user', 'content' => trim($userPrompt)];
        }

        $body = [
            'model' => $provider->model,
            'messages' => $messages,
            'temperature' => $payload['temperature'] ?? 0.7,
        ];

        $maxTokens = $payload['max_tokens'] ?? $options['max_tokens'] ?? null;
        if ($maxTokens) {
            $body['max_tokens'] = (int) $maxTokens;
        }

        if (! empty($options['require_json']) && $provider->supports_json_mode) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $start = microtime(true);
        $response = Http::withHeaders($this->headers($provider))
            ->timeout(120)
            ->post($this->endpoint($provider), $body);
        $latency = (int) ((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            throw new \Exception(EncodingGuard::jsonEncode([
                'message' => 'OpenAI API Error: '.$response->body(),
                'status' => $response->status(),
                'is_rate_limit' => $response->status() === 429,
                'is_auth_error' => in_array($response->status(), [401, 403]),
            ]));
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? '';

        return [
            'content' => $text,
            'json' => app(AIJsonResponseParser::class)->parse($text, ! empty($options['require_json'])),
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'latency_ms' => $latency,
        ];
    }

    private function endpoint(AiProvider $provider): string
    {
        $endpoint = trim((string) ($provider->endpoint ?: 'https://api.openai.com/v1/chat/completions'));
        $endpoint = rtrim($endpoint, '/');

        if (str_ends_with($endpoint, '/chat/completions')) {
            return $endpoint;
        }

        if (str_ends_with($endpoint, '/v1')) {
            return $endpoint.'/chat/completions';
        }

        return $endpoint.'/v1/chat/completions';
    }

    private function headers(AiProvider $provider): array
    {
        $headers = ['Accept' => 'application/json'];

        if (! empty($provider->api_key)) {
            $headers['Authorization'] = 'Bearer '.$provider->api_key;
        }

        return $headers;
    }

}
