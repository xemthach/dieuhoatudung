<?php

namespace App\Services\AI;

use App\Models\AiProvider;
final class AiProviderReadinessService
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
        $providers = AiProvider::query()
            ->whereNull('deleted_at')
            ->orderByRaw("CASE WHEN priority = 'primary' THEN 0 ELSE 1 END")
            ->orderBy('weight', 'desc')
            ->get();
        $active = $providers->where('status', 'active');
        $preferred = $active->first() ?: $providers->first();

        return [
            'configured_count' => $providers->filter(fn (AiProvider $provider): bool => $this->credentialConfigured($provider))->count(),
            'active_count' => $active->count(),
            'ready' => $active->contains(fn (AiProvider $provider): bool => $this->credentialConfigured($provider)),
            'preferred' => $preferred ? $this->present($preferred) : null,
            'providers' => $providers->map(fn (AiProvider $provider): array => $this->present($provider))->all(),
            'quota_supported' => false,
            'quota_label' => 'Không được provider cung cấp',
        ];
    }

    /** @return array<string, mixed> */
    public function present(AiProvider $provider): array
    {
        $lastChecked = collect([$provider->last_success_at, $provider->last_error_at])->filter()->sortDesc()->first();
        $lastSuccessIsLatest = $provider->last_success_at
            && (! $provider->last_error_at || $provider->last_success_at->greaterThanOrEqualTo($provider->last_error_at));
        $connection = ! $lastChecked
            ? 'NOT_CHECKED'
            : ($lastSuccessIsLatest ? 'CONNECTED' : 'FAILED');

        return [
            'id' => (int) $provider->id,
            'provider' => (string) $provider->provider,
            'name' => (string) $provider->name,
            'model' => (string) $provider->model,
            'enabled' => $provider->status === 'active',
            'configured' => $this->credentialConfigured($provider),
            'connection' => $connection,
            'connection_label' => match ($connection) {
                'CONNECTED' => 'Đã kết nối',
                'FAILED' => 'Kết nối lỗi',
                default => 'Chưa kiểm tra',
            },
            'last_checked_at' => $lastChecked?->toIso8601String(),
            'last_checked_human' => $lastChecked?->diffForHumans() ?? 'Chưa kiểm tra',
            'last_success_at' => $provider->last_success_at?->toIso8601String(),
            'endpoint_host' => $this->endpointHost($provider),
            'quota_supported' => false,
            'quota_label' => 'Không được provider cung cấp',
        ];
    }

    private function credentialConfigured(AiProvider $provider): bool
    {
        if ($provider->provider === 'ollama') {
            return filled($provider->endpoint);
        }

        try {
            return filled($provider->api_key);
        } catch (\Throwable) {
            return false;
        }
    }

    private function endpointHost(AiProvider $provider): string
    {
        $endpoint = trim((string) $provider->endpoint);
        if ($endpoint === '') {
            return match ($provider->provider) {
                'gemini' => 'generativelanguage.googleapis.com',
                'openai' => 'api.openai.com',
                'claude' => 'api.anthropic.com',
                default => 'Theo adapter mặc định',
            };
        }

        return (string) (parse_url($endpoint, PHP_URL_HOST) ?: 'Endpoint tùy chỉnh');
    }
}
