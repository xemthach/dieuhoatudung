<?php

namespace App\Services\Product;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Query-side counterpart of PromotionPriceResolver for public Product lists. */
final class ProductEffectivePriceQuery
{
    /** @return array{sql: string, bindings: list<mixed>} */
    public function expression(?CarbonInterface $now = null): array
    {
        $now ??= Carbon::now();

        $window = '(`products`.`promotion_start_at` is null or `products`.`promotion_start_at` <= ?)'
            .' and (`products`.`promotion_end_at` is null or `products`.`promotion_end_at` >= ?)';

        $directDiscount = "case
            when {$window}
                and `products`.`sale_price` is not null
                and (`products`.`regular_price` is null or `products`.`sale_price` < `products`.`regular_price`)
                then `products`.`sale_price`
            when {$window}
                and `products`.`regular_price` is not null
                and `products`.`discount_percent` > 0
                then round(`products`.`regular_price` * (100 - case when `products`.`discount_percent` > 100 then 100 else `products`.`discount_percent` end) / 100)
            else null
        end";

        $candidates = [
            $directDiscount,
            '`products`.`regular_price`',
            'case when `products`.`regular_price` is null then `products`.`sale_price` else null end',
        ];
        $bindings = [$now, $now, $now, $now];

        if ($this->promotionSchemaIsAvailable()) {
            $candidates[] = $this->promotionDiscountExpression();
            $bindings[] = $now;
            $bindings[] = $now;
        }

        $sentinel = '9999999999999.99';
        $minimum = DB::connection()->getDriverName() === 'sqlite' ? 'min' : 'least';
        $coalesced = array_map(
            static fn (string $candidate): string => "coalesce(({$candidate}), {$sentinel})",
            $candidates,
        );
        $sql = "nullif({$minimum}(".implode(', ', $coalesced)."), {$sentinel})";

        return [
            'sql' => $sql,
            'bindings' => $bindings,
        ];
    }

    public function whereMinimum(Builder $query, float $minimum): Builder
    {
        $price = $this->expression();

        return $query->whereRaw($this->numeric($price['sql']).' >= ?', [...$price['bindings'], $minimum]);
    }

    public function whereMaximum(Builder $query, float $maximum): Builder
    {
        $price = $this->expression();

        return $query->whereRaw($this->numeric($price['sql']).' <= ?', [...$price['bindings'], $maximum]);
    }

    public function whereDiscounted(Builder $query): Builder
    {
        $price = $this->expression();

        return $query
            ->whereNotNull('products.regular_price')
            ->whereRaw($this->numeric($price['sql']).' < `products`.`regular_price`', $price['bindings']);
    }

    public function orderBy(Builder $query, string $direction): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $price = $this->expression();
        $numeric = $this->numeric($price['sql']);

        return $query
            ->addSelect('products.*')
            ->selectRaw("{$numeric} as effective_price_sort", $price['bindings'])
            ->orderByRaw('case when effective_price_sort is null then 1 else 0 end asc')
            ->orderBy('effective_price_sort', $direction);
    }

    private function numeric(string $expression): string
    {
        return 'cast(('.$expression.') as decimal(15,2))';
    }

    private function promotionSchemaIsAvailable(): bool
    {
        return Schema::hasTable('promotions')
            && Schema::hasColumn('promotions', 'scope')
            && Schema::hasTable('product_promotion')
            && Schema::hasTable('category_promotion')
            && Schema::hasTable('brand_promotion');
    }

    private function promotionDiscountExpression(): string
    {
        return "select min(case
                when `effective_promotions`.`discount_type` = 'percent'
                    then round(`products`.`regular_price` * (100 - case when `effective_promotions`.`discount_value` > 100 then 100 else `effective_promotions`.`discount_value` end) / 100)
                when `effective_promotions`.`discount_type` = 'fixed'
                    then case
                        when `products`.`regular_price` - `effective_promotions`.`discount_value` < 0 then 0
                        else `products`.`regular_price` - `effective_promotions`.`discount_value`
                    end
                else null
            end)
            from `promotions` as `effective_promotions`
            where `products`.`regular_price` is not null
              and `products`.`regular_price` > 0
              and `effective_promotions`.`deleted_at` is null
              and `effective_promotions`.`is_active` = 1
              and `effective_promotions`.`discount_value` is not null
              and `effective_promotions`.`discount_value` > 0
              and (`effective_promotions`.`start_at` is null or `effective_promotions`.`start_at` <= ?)
              and (`effective_promotions`.`end_at` is null or `effective_promotions`.`end_at` >= ?)
              and (
                    coalesce(`effective_promotions`.`scope`, 'global') = 'global'
                    or (`effective_promotions`.`scope` = 'product' and exists (
                        select 1 from `product_promotion`
                        where `product_promotion`.`promotion_id` = `effective_promotions`.`id`
                          and `product_promotion`.`product_id` = `products`.`id`
                    ))
                    or (`effective_promotions`.`scope` = 'category' and exists (
                        select 1 from `category_promotion`
                        where `category_promotion`.`promotion_id` = `effective_promotions`.`id`
                          and `category_promotion`.`product_category_id` = `products`.`product_category_id`
                    ))
                    or (`effective_promotions`.`scope` = 'brand' and exists (
                        select 1 from `brand_promotion`
                        where `brand_promotion`.`promotion_id` = `effective_promotions`.`id`
                          and `brand_promotion`.`brand_id` = `products`.`brand_id`
                    ))
              )";
    }
}
