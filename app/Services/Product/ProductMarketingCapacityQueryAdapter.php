<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Migration-safe business-capacity query boundary.
 *
 * Customer-facing queries use the persisted commercial marketing column.
 * The legacy btu column is not a query/display fallback once the canonical
 * column exists; it is audit evidence for an explicitly approved backfill.
 */
class ProductMarketingCapacityQueryAdapter
{
    public const LEGACY_DISPLAY_ONLY = 'LEGACY_DISPLAY_ONLY';

    private static ?string $resolvedColumn = null;

    public function column(): string
    {
        return self::$resolvedColumn ??= (
            Schema::hasColumn('products', 'marketing_capacity_btu')
                ? 'marketing_capacity_btu'
                : 'btu'
        );
    }

    public function mode(): string
    {
        return $this->column() === 'btu' ? self::LEGACY_DISPLAY_ONLY : 'MARKETING_CAPACITY_CANONICAL';
    }

    public function applyPresent(Builder $query): Builder
    {
        return $query->whereNotNull($this->column())->where($this->column(), '>', 0);
    }

    public function applyBetween(Builder $query, int $min, int $max): Builder
    {
        return $query->whereBetween($this->column(), [$min, $max]);
    }

    public function value(Product $product): ?int
    {
        $value = $product->getAttribute('marketing_capacity_btu');
        return $value === null || $value === '' ? null : (int) $value;
    }

    public function distance(Collection $products, int $target): Collection
    {
        return $products->sortBy(fn (Product $product): int => abs(($this->value($product) ?? 0) - $target))->values();
    }
}
