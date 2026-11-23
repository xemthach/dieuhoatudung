<?php

namespace App\Services\Navigation;

use App\Models\ProductCategory;

final class SiteProductScope
{
    public function category(): ?ProductCategory
    {
        if (setting('site.product_scope', 'all_products') !== 'primary_category') {
            return null;
        }

        return ProductCategory::query()->whereKey((int) setting('site.primary_product_category_id', 0))
            ->where('is_active', true)->first();
    }

    public function productUrl(): string
    {
        $configured = $this->configuredTarget('product_cta');
        if ($configured !== null) return $configured;
        $category = $this->category();

        return $category ? app(PublicNavigationResolver::class)->categoryUrl($category) : route('products.index');
    }

    public function consultationUrl(): string
    {
        $configured = $this->configuredTarget('consultation_cta');
        if ($configured !== null) return $configured;
        $legacy = setting('cta.quote_cta_link', '');

        return app(PublicNavigationResolver::class)->targetUrl('custom_url', $legacy) ?? route('quote.index');
    }

    private function configuredTarget(string $prefix): ?string
    {
        $type = (string) setting('site.'.$prefix.'_type', 'auto');
        if ($type === 'auto') return null;

        return app(PublicNavigationResolver::class)->targetUrl($type, setting('site.'.$prefix.'_'.$type, ''));
    }

    public function compareTitle(): string
    {
        $category = $this->category();

        return trim((string) setting('site.compare_title', '')) ?: ($category ? 'So sánh '.$category->name : 'So sánh sản phẩm');
    }

    public function productLabel(): string
    {
        return trim((string) setting('site.product_label', '')) ?: ($this->category()?->name ?? 'Sản phẩm điều hòa');
    }

    public function productCtaLabel(): string
    {
        return trim((string) setting('site.product_cta_label', '')) ?: 'Xem sản phẩm';
    }

    public function compareDescription(): string
    {
        return trim((string) setting('site.compare_description', '')) ?: 'So sánh chi tiết thông số kỹ thuật, tính năng và giá bán để lựa chọn sản phẩm phù hợp.';
    }

    public function consultationDescription(): string
    {
        return trim((string) setting('site.consultation_description', '')) ?: 'Đội ngũ chuyên gia HVAC sẵn sàng hỗ trợ bạn chọn model phù hợp với không gian và ngân sách.';
    }

    public function consultationTitle(): string
    {
        $category = $this->category();

        return trim((string) setting('site.consultation_title', '')) ?: ($category ? 'Cần tư vấn '.$category->name.'?' : 'Cần tư vấn chọn điều hòa?');
    }
}
