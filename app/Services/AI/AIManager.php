<?php

namespace App\Services\AI;

use App\Models\AiGenerationSession;
use App\Models\AiProvider;
use App\Models\AiRequestLog;
use App\Services\AI\Adapters\AIAdapterInterface;
use App\Services\AI\Adapters\GeminiAdapter;
use App\Services\AI\Adapters\OpenAIAdapter;
use Illuminate\Support\Facades\Log;

class AIManager
{
    private AIProviderPool $pool;

    public function __construct(AIProviderPool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * Generate content via unified AI gateway.
     */
    public function generate(array $payload, array $options = []): array
    {
        $taskType = $options['task_type'] ?? 'general';
        $contextId = $options['context_id'] ?? null;
        $allowFallback = $options['allow_fallback'] ?? true;
        $maxAttempts = $options['max_attempts'] ?? 3;

        $session = null;
        $provider = null;

        // 1. Context Consistency
        if ($contextId) {
            $session = AiGenerationSession::firstOrCreate(
                ['context_id' => $contextId],
                ['status' => 'active', 'task_type' => $taskType]
            );

            if ($session->provider_id && $session->status === 'active') {
                $provider = AiProvider::find($session->provider_id);
            }
        }

        $attempts = 0;
        $lastError = null;

        while ($attempts < $maxAttempts) {
            $attempts++;

            // 2. Select Provider
            if (!$provider || $provider->status !== 'active') {
                $provider = $this->pool->selectProvider($taskType, $options);
                
                if (!$provider) {
                    throw new \RuntimeException('No AI providers available.');
                }

                if ($session) {
                    $session->update([
                        'provider_id' => $provider->id,
                        'provider' => $provider->provider,
                        'model' => $provider->model,
                    ]);
                }
            }

            // 3. Adapter Execution
            $adapter = $this->getAdapter($provider);
            
            try {
                $result = $adapter->generate($provider, $payload, $options);
                
                // 4. Success
                $this->pool->markSuccess($provider, [
                    'total_tokens' => $result['tokens_used'] ?? 0,
                ]);

                $this->logRequest($provider, 'success', $taskType, $contextId, $result, null);

                return [
                    'success' => true,
                    'provider' => $provider->provider,
                    'provider_id' => $provider->id,
                    'model' => $provider->model,
                    'content' => $result['content'],
                    'json' => $result['json'] ?? [],
                    'tokens_used' => $result['tokens_used'] ?? 0,
                    'latency_ms' => $result['latency_ms'] ?? 0,
                ];

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $isRateLimit = false;

                // Try parse error
                $errorData = json_decode($lastError, true);
                if (is_array($errorData) && !empty($errorData['is_rate_limit'])) {
                    $isRateLimit = true;
                } elseif (stripos($lastError, '429') !== false) {
                    $isRateLimit = true;
                }

                if ($isRateLimit) {
                    $this->pool->markRateLimited($provider);
                    $this->logRequest($provider, 'rate_limited', $taskType, $contextId, [], $lastError);
                } else {
                    $this->pool->markFailure($provider, $lastError);
                    $this->logRequest($provider, 'failed', $taskType, $contextId, [], $lastError);
                }

                if (!$allowFallback) {
                    break;
                }

                // Force pick another provider for next attempt
                $provider = null;
                $this->logRequest(null, 'fallback', $taskType, $contextId, [], 'Fallback triggered');
            }
        }

        throw new \RuntimeException("AI Generation failed after {$attempts} attempts. Last error: " . $lastError);
    }

    private function getAdapter(AiProvider $provider): AIAdapterInterface
    {
        return match ($provider->provider) {
            'gemini' => new GeminiAdapter(),
            'openai', 'groq', 'ollama', 'custom' => new OpenAIAdapter(),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider->provider}")
        };
    }

    private function logRequest(?AiProvider $provider, string $status, string $taskType, ?string $contextId, array $result, ?string $error): void
    {
        AiRequestLog::create([
            'ai_provider_id' => $provider?->id,
            'provider' => $provider?->provider ?? 'system',
            'model' => $provider?->model ?? 'unknown',
            'task_type' => $taskType,
            'context_id' => $contextId,
            'status' => $status,
            'latency_ms' => $result['latency_ms'] ?? null,
            'tokens_total' => $result['tokens_used'] ?? null,
            'error_message' => $error,
        ]);
    }
}
