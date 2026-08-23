<?php

namespace App\Services\Product;

use App\Enums\DiscountType;
use App\Models\Promotion;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PromotionPriceResolver
{
    private static ?array $schemaCapabilities = null;

    public function resolve(Product $product, ?CarbonInterface $now = null): array
    {
        $now ??= Carbon::now();

        $regularPrice = $this->toFloat($product->regular_price);
        $rawSalePrice = $this->toFloat($product->sale_price);
        $discountPercent = $this->toInt($product->discount_percent);
        $promotionIsActive = $this->promotionWindowIsActive($product, $now);

        $salePrice = null;
        $promotion = null;
        $promotionSource = null;

        if ($promotionIsActive && $rawSalePrice !== null && ($regularPrice === null || $rawSalePrice < $regularPrice)) {
            $salePrice = $rawSalePrice;
            $promotionSource = 'product_sale_price';
        } elseif ($promotionIsActive && $regularPrice !== null && $discountPercent > 0) {
            $salePrice = round($regularPrice * (100 - min($discountPercent, 100)) / 100);
            $promotionSource = 'product_discount_percent';
        }

        if ($regularPrice !== null && $regularPrice > 0) {
            foreach ($this->matchingPromotions($product) as $candidate) {
                $candidatePrice = $this->applyPromotion($regularPrice, $candidate);

                if ($candidatePrice !== null && $candidatePrice < $regularPrice && ($salePrice === null || $candidatePrice < $salePrice)) {
                    $salePrice = $candidatePrice;
                    $promotion = $candidate;
                    $promotionSource = 'promotion_'.$candidate->scope;
                }
            }
        }

        $finalPrice = $salePrice ?? $regularPrice ?? $rawSalePrice;
        $hasDiscount = $regularPrice !== null && $salePrice !== null && $salePrice < $regularPrice;

        if ($hasDiscount && $discountPercent <= 0 && $regularPrice > 0) {
            $discountPercent = (int) round((($regularPrice - $salePrice) / $regularPrice) * 100);
        }

        return [
            'regular_price' => $regularPrice,
            'sale_price' => $salePrice,
            'raw_sale_price' => $rawSalePrice,
            'final_price' => $finalPrice,
            'has_discount' => $hasDiscount,
            'discount_percent' => $hasDiscount ? $discountPercent : null,
            'promotion_is_active' => $promotionIsActive,
            'promotion_start_at' => $product->promotion_start_at,
            'promotion_end_at' => $product->promotion_end_at,
            'promotion_id' => $promotion?->getKey(),
            'promotion_title' => $promotion?->title,
            'promotion_source' => $promotionSource,
            'price_includes_vat' => (bool) $product->price_includes_vat,
        ];
    }

    public function hasActiveDiscount(Product $product, ?CarbonInterface $now = null): bool
    {
        return (bool) $this->resolve($product, $now)['has_discount'];
    }

    protected function promotionWindowIsActive(Product $product, CarbonInterface $now): bool
    {
        if ($product->promotion_start_at && $now->lt($product->promotion_start_at)) {
            return false;
        }

        if ($product->promotion_end_at && $now->gt($product->promotion_end_at)) {
            return false;
        }

        return true;
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    protected function toInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) $value;
    }

    protected function matchingPromotions(Product $product)
    {
        $schema = $this->schemaCapabilities();
        if (! $product->exists || ! $schema['promotions'] || ! $schema['promotion_scope']) {
            return collect();
        }

        $with = [];
        if ($schema['product_promotion']) {
            $with[] = 'products:id';
        }
        if ($schema['category_promotion']) {
            $with[] = 'categories:id';
        }
        if ($schema['brand_promotion']) {
            $with[] = 'brands:id';
        }

        return Promotion::query()
            ->currentlyActive()
            ->with($with)
            ->where(function ($query) use ($product, $schema) {
                $query->where('scope', 'global');

                if ($schema['product_promotion']) {
                    $query->orWhere(fn ($q) => $q->where('scope', 'product')->whereHas('products', fn ($relation) => $relation->whereKey($product->getKey())));
                }

                if ($schema['category_promotion']) {
                    $query->orWhere(fn ($q) => $q->where('scope', 'category')->whereHas('categories', fn ($relation) => $relation->whereKey($product->product_category_id)));
                }

                if ($schema['brand_promotion']) {
                    $query->orWhere(fn ($q) => $q->where('scope', 'brand')->whereHas('brands', fn ($relation) => $relation->whereKey($product->brand_id)));
                }
            })
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->appliesToProduct($product));
    }

    /**
     * Promotion schema capabilities are stable during one PHP request/process.
     * Cache the metadata lookup instead of repeating information_schema queries
     * once per Product card/feed item.
     */
    protected function schemaCapabilities(): array
    {
        return self::$schemaCapabilities ??= [
            'promotions' => Schema::hasTable('promotions'),
            'promotion_scope' => Schema::hasColumn('promotions', 'scope'),
            'product_promotion' => Schema::hasTable('product_promotion'),
            'category_promotion' => Schema::hasTable('category_promotion'),
            'brand_promotion' => Schema::hasTable('brand_promotion'),
        ];
    }

    protected function applyPromotion(float $regularPrice, Promotion $promotion): ?float
    {
        $discountValue = $this->toFloat($promotion->discount_value);
        if ($discountValue === null || $discountValue <= 0) {
            return null;
        }

        $discountType = $promotion->discount_type instanceof DiscountType
            ? $promotion->discount_type->value
            : (string) $promotion->discount_type;

        return match ($discountType) {
            DiscountType::Percent->value => max(0, round($regularPrice * (100 - min($discountValue, 100)) / 100)),
            DiscountType::Fixed->value => max(0, $regularPrice - $discountValue),
            default => null,
        };
    }
}
