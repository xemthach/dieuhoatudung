<?php

namespace App\Services\Product;

use App\Models\Product;
use Carbon\Carbon;

class ProductBadgeService
{
    /**
     * Get all applicable badges for a product, sorted by priority.
     *
     * @param Product $product
     * @return array
     */
    public function getBadges(Product $product): array
    {
        $badges = [];

        // 1. Stock Status Badges (Highest Priority: 10)
        if ($product->stock_status === 'out_of_stock') {
            $badges[] = [
                'label' => 'Hết hàng',
                'type' => 'out_of_stock',
                'priority' => 10,
                'css_class' => 'bg-surface-500 text-white',
            ];
        } elseif ($product->stock_status === 'pre_order') {
            $badges[] = [
                'label' => 'Đặt trước',
                'type' => 'pre_order',
                'priority' => 10,
                'css_class' => 'bg-warning-500 text-white',
            ];
        } elseif ($product->stock_status === 'contact') {
            $badges[] = [
                'label' => 'Liên hệ',
                'type' => 'contact',
                'priority' => 10,
                'css_class' => 'bg-info-500 text-white',
            ];
        }

        // 2. Sale/Discount Badge (Priority: 9)
        if ($this->hasActiveSale($product)) {
            $label = 'Giảm giá';
            if ($product->discount_percent > 0) {
                $label = '-' . $product->discount_percent . '%';
            } elseif ($product->regular_price && $product->sale_price && $product->regular_price > $product->sale_price) {
                $percent = round((($product->regular_price - $product->sale_price) / $product->regular_price) * 100);
                if ($percent > 0) {
                    $label = '-' . $percent . '%';
                }
            }

            $badges[] = [
                'label' => $label,
                'type' => 'sale',
                'priority' => 9,
                'css_class' => 'bg-danger-500 text-white',
            ];
        }

        // 3. Bestseller Badge (Priority: 8)
        if ($product->is_bestseller) {
            $badges[] = [
                'label' => 'Bán chạy',
                'type' => 'bestseller',
                'priority' => 8,
                'css_class' => 'bg-orange-500 text-white',
            ];
        }

        // 4. New Badge (Priority: 7)
        if ($product->is_new) {
            $badges[] = [
                'label' => 'Mới',
                'type' => 'new',
                'priority' => 7,
                'css_class' => 'bg-success-500 text-white',
            ];
        }

        // 5. Featured Badge (Priority: 6)
        if ($product->is_featured) {
            $badges[] = [
                'label' => 'Nổi bật',
                'type' => 'featured',
                'priority' => 6,
                'css_class' => 'bg-primary-500 text-white',
            ];
        }

        // Sort by priority descending (highest priority first)
        usort($badges, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return $badges;
    }

    /**
     * Check if the product has an active sale based on prices and dates.
     */
    protected function hasActiveSale(Product $product): bool
    {
        // Must have sale price or discount percent
        if (!$product->sale_price && !$product->discount_percent) {
            return false;
        }

        $now = Carbon::now();

        if ($product->promotion_start_at && $now->lt($product->promotion_start_at)) {
            return false;
        }

        if ($product->promotion_end_at && $now->gt($product->promotion_end_at)) {
            return false;
        }

        return true;
    }
}
