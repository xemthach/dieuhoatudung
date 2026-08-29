<?php

namespace App\Services\Navigation;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class PublicNavigationResolver
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private const FALLBACKS = [
        'header_primary' => [
            ['label' => 'Trang chủ', 'type' => 'route', 'target' => 'home', 'sort_order' => 10],
            ['label' => 'Sản phẩm', 'type' => 'route', 'target' => 'products.index', 'sort_order' => 20],
            ['label' => 'Bảng giá', 'type' => 'route', 'target' => 'price-list', 'sort_order' => 30],
            ['label' => 'Blog', 'type' => 'route', 'target' => 'blog.index', 'sort_order' => 40],
            ['label' => 'FAQ', 'type' => 'route', 'target' => 'faq.dieu-hoa', 'sort_order' => 50],
            ['label' => 'Liên hệ', 'type' => 'route', 'target' => 'contact', 'sort_order' => 60],
        ],
        'header_top' => [
            ['label' => 'Bảng giá', 'type' => 'route', 'target' => 'price-list', 'sort_order' => 10],
            ['label' => 'FAQ', 'type' => 'route', 'target' => 'faq.dieu-hoa', 'sort_order' => 20],
            ['label' => 'Liên hệ', 'type' => 'route', 'target' => 'contact', 'sort_order' => 30],
        ],
        'footer_products' => [
            ['label' => 'Sản phẩm', 'type' => 'route', 'target' => 'products.index', 'sort_order' => 10],
            ['label' => 'Bảng giá', 'type' => 'route', 'target' => 'price-list', 'sort_order' => 20],
            ['label' => 'Kiến thức', 'type' => 'route', 'target' => 'blog.index', 'sort_order' => 30],
            ['label' => 'FAQ', 'type' => 'route', 'target' => 'faq.dieu-hoa', 'sort_order' => 40],
        ],
    ];

    /** @return array<int, array{label:string,url:string,type:string,target:mixed,sort_order:int,open_new_tab:bool}> */
    public function items(string $location): array
    {
        $configured = setting("navigation.{$location}");
        $items = is_array($configured) ? $configured : (self::FALLBACKS[$location] ?? []);

        usort($items, static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return array_values(array_filter(array_map(fn (array $item): ?array => $this->resolve($item), $items)));
    }

    /** @return array{label:string,url:string,type:string,target:mixed,sort_order:int,open_new_tab:bool}|null */
    private function resolve(array $item): ?array
    {
        if (($item['is_active'] ?? true) !== true && ($item['is_active'] ?? true) !== 1 && ($item['is_active'] ?? true) !== '1') {
            return null;
        }

        $type = strtolower((string) ($item['type'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') return null;

        $url = null;
        $target = match ($type) {
            'product_category' => $item['category_target'] ?? $item['target'] ?? null,
            'custom_url' => $item['custom_target'] ?? $item['target'] ?? null,
            default => $item['target'] ?? null,
        };
        if ($type === 'route') {
            $allowed = ['home','products.index','price-list','blog.index','faq.dieu-hoa','contact','quote.index','brands.index','case-studies.index','policy-pages.index'];
            if (! in_array((string) $target, $allowed, true) || ! Route::has((string) $target)) return null;
            $url = route((string) $target);
        } elseif ($type === 'product_category') {
            $category = ProductCategory::query()->whereKey((int) $target)->where('is_active', true)->where('is_indexable', true)->first();
            if (! $category) return null;
            $target = $category->id;
            $url = route('category.show', $category->slug);
        } elseif ($type === 'custom_url') {
            $url = trim((string) $target);
            if (! $this->isSafeUrl($url)) return null;
        } else {
            return null;
        }

        return [
            'label' => $label,
            'url' => $url,
            'type' => $type,
            'target' => $target,
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'open_new_tab' => (bool) ($item['open_new_tab'] ?? false),
        ];
    }

    private function isSafeUrl(string $url): bool
    {
        if ($url === '' || Str::startsWith(strtolower($url), ['javascript:', 'data:', 'vbscript:'])) return false;
        if (str_starts_with($url, '/')) return true;
        return filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http','https'], true);
    }
}
