<?php

namespace App\Services\Product;

use App\Models\Product;
use Carbon\Carbon;

class ProductBadgeService
{
    public function __construct(private PromotionPriceResolver $priceResolver) {}

    /**
     * Get all applicable badges for a product, sorted by priority.
     */
    public function getBadges(Product $product): array
    {
        $badges = [];

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

        if ($this->hasActiveSale($product)) {
            $price = $this->priceResolver->resolve($product);
            $badges[] = [
                'label' => $price['discount_percent'] ? '-' . $price['discount_percent'] . '%' : 'Giảm giá',
                'type' => 'sale',
                'priority' => 9,
                'css_class' => 'bg-danger-500 text-white',
            ];
        }

        if ($product->is_bestseller) {
            $badges[] = [
                'label' => 'Bán chạy',
                'type' => 'bestseller',
                'priority' => 8,
                'css_class' => 'bg-orange-500 text-white',
            ];
        }

        if ($product->is_new) {
            $badges[] = [
                'label' => 'Mới',
                'type' => 'new',
                'priority' => 7,
                'css_class' => 'bg-success-500 text-white',
            ];
        }

        if ($product->is_featured) {
            $badges[] = [
                'label' => 'Nổi bật',
                'type' => 'featured',
                'priority' => 6,
                'css_class' => 'bg-primary-500 text-white',
            ];
        }

        usort($badges, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $badges;
    }

    protected function hasActiveSale(Product $product): bool
    {
        return $this->priceResolver->hasActiveDiscount($product, Carbon::now());
    }
}
