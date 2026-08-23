<?php

namespace App\Services\AI;

use App\Models\Product;

final class DraftOnlyWriteGuard
{
    private static int $depth = 0;

    private static array $attempts = [];

    public static function begin(string $reason = 'draft_only_strict'): void
    {
        self::$depth++;
    }

    public static function end(): void
    {
        self::$depth = max(0, self::$depth - 1);
    }

    public static function isActive(): bool
    {
        return self::$depth > 0;
    }

    public static function block(Product $product): bool
    {
        self::$attempts[] = [
            'product_id' => (int) $product->getKey(),
            'dirty' => array_keys($product->getDirty()),
            'at' => now()->toIso8601String(),
        ];

        return false;
    }

    public static function attempts(): array
    {
        return self::$attempts;
    }

    public static function resetAttempts(): void
    {
        self::$attempts = [];
    }
}
