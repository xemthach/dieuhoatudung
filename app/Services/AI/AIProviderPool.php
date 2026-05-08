<?php

namespace App\Services\AI;

use App\Models\AiProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AIProviderPool
{
    /**
     * Get active providers, excluding those currently rate-limited.
     */
    public function getAvailableProviders(string $priority = 'primary'): Collection
    {
        return AiProvider::where('status', 'active')
            ->where('priority', $priority)
            ->where(function ($q) {
                $q->whereNull('rate_limited_until')
                  ->orWhere('rate_limited_until', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('daily_limit')
                  ->orWhereColumn('daily_used', '<', 'daily_limit');
            })
            ->where(function ($q) {
                $q->whereNull('minute_limit')
                  ->orWhereColumn('minute_used', '<', 'minute_limit');
            })
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Select a provider using Weighted Round-Robin strategy.
     */
    public function selectProvider(string $taskType = null, array $context = []): ?AiProvider
    {
        // 1. Try Primary
        $primaryProviders = $this->getAvailableProviders('primary');
        if ($primaryProviders->isNotEmpty()) {
            return $this->applyWeightedRoundRobin($primaryProviders, 'primary');
        }

        // 2. Fallback to Fallback
        $fallbackProviders = $this->getAvailableProviders('fallback');
        if ($fallbackProviders->isNotEmpty()) {
            return $this->applyWeightedRoundRobin($fallbackProviders, 'fallback');
        }

        return null;
    }

    /**
     * Weighted Round-Robin logic using Cache counter
     */
    private function applyWeightedRoundRobin(Collection $providers, string $groupKey): ?AiProvider
    {
        if ($providers->count() === 1) {
            return $providers->first();
        }

        // Calculate total weight
        $totalWeight = $providers->sum('weight');
        if ($totalWeight <= 0) {
            return $providers->first();
        }

        // Get and increment current index
        $indexKey = "ai_provider_rotation_index_{$groupKey}";
        $currentIndex = Cache::increment($indexKey);
        
        // Find position in the weight map
        $position = $currentIndex % $totalWeight;

        $accumulatedWeight = 0;
        foreach ($providers as $provider) {
            $accumulatedWeight += $provider->weight;
            if ($position < $accumulatedWeight) {
                return $provider;
            }
        }

        return $providers->first(); // fallback
    }

    public function markSuccess(AiProvider $provider, array $usage = []): void
    {
        $provider->increment('success_count');
        $provider->increment('request_count');
        $provider->update([
            'last_success_at' => now(),
            'last_used_at' => now(),
            'status' => 'active',
            'error_count' => 0,
            'tokens_used' => $provider->tokens_used + ($usage['total_tokens'] ?? 0),
            'daily_used' => $provider->daily_used + 1,
            'minute_used' => $provider->minute_used + 1,
        ]);
    }

    public function markFailure(AiProvider $provider, \Throwable|string $error): void
    {
        $message = is_string($error) ? $error : $error->getMessage();
        
        $provider->increment('error_count');
        $provider->increment('request_count');

        $updates = [
            'last_error_at' => now(),
            'last_used_at' => now(),
            'last_error_message' => $message,
            'daily_used' => $provider->daily_used + 1,
            'minute_used' => $provider->minute_used + 1,
        ];

        if ($provider->error_count >= 3) {
            // Put to failed if continuous errors
            $updates['status'] = 'failed';
        }

        $provider->update($updates);
    }

    public function markRateLimited(AiProvider $provider, ?\DateTimeInterface $until = null): void
    {
        $provider->increment('error_count');
        $provider->increment('request_count');
        
        $provider->update([
            'status' => 'rate_limited',
            'rate_limited_until' => $until ?? now()->addSeconds(60),
            'last_error_at' => now(),
            'last_used_at' => now(),
            'last_error_message' => 'Rate Limited',
            'daily_used' => $provider->daily_used + 1,
            'minute_used' => $provider->minute_used + 1,
        ]);
    }

    public function resetProviderStatus(AiProvider $provider): void
    {
        $provider->update([
            'status' => 'active',
            'error_count' => 0,
            'rate_limited_until' => null,
            'last_error_message' => null,
        ]);
    }
}
