<?php

namespace App\Services\AI\Adapters;

use App\Models\AiProvider;

interface AIAdapterInterface
{
    /**
     * Test the connection to the provider.
     * Returns an array: ['success' => true/false, 'message' => '...']
     */
    public function testConnection(AiProvider $provider): array;

    /**
     * Generate content.
     * Payload expected: ['system' => '...', 'prompt' => '...']
     * Options: ['require_json' => true/false]
     * Returns array: ['content' => '...', 'json' => [...], 'tokens_used' => int, 'latency_ms' => int]
     */
    public function generate(AiProvider $provider, array $payload, array $options = []): array;
}
