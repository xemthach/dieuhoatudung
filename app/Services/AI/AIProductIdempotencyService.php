<?php

namespace App\Services\AI;

use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\Product\ProductTechnicalFactResolver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

final class AIProductIdempotencyService
{
    public function __construct(private readonly ProductTechnicalFactResolver $technicalFacts) {}

    public function contextHash(Product $product): string
    {
        return hash('sha256', json_encode([
            'product_id' => $product->id,
            'model' => $product->model_code,
            'brand_id' => $product->brand_id,
            'marketing_capacity_btu' => $this->technicalFacts->value($product, 'marketing_capacity_btu'),
            'verified_facts' => $this->technicalFacts->allVerified($product),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    public function key(Product $product, array $config, string $operation = 'product_content', ?array $fields = null): string
    {
        $normalized = [
            'product_id' => $product->id,
            'operation' => $config['operation'] ?? $operation,
            'fields' => $fields ?? array_keys(array_filter((array) ($config['outputs'] ?? []))),
            'technical_context_hash' => $this->contextHash($product),
            'prompt_version' => AIContentGovernance::PROMPT_VERSION,
            'provider_policy' => $config['provider_policy'] ?? 'default',
            'operation_generation' => $config['operation_generation']
                ?? $config['authorized_operation_id']
                ?? 'legacy-generation',
        ];

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function existing(string $key, ?int $exceptItemId = null): ?AiProductJobItem
    {
        if (! Schema::hasColumn('ai_product_job_items', 'idempotency_key')) {
            return null;
        }

        return AiProductJobItem::query()
            ->where('idempotency_key', $key)
            ->when($exceptItemId, fn ($query) => $query->where('id', '<>', $exceptItemId))
            ->latest('id')
            ->first();
    }

    public function isDuplicateKeyException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'ai_product_item_idempotency_uq')
            || str_contains($message, 'duplicate entry');
    }
}
