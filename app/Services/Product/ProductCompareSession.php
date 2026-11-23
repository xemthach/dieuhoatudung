<?php

namespace App\Services\Product;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** The server session is authoritative; browser storage is only a display cache. */
final class ProductCompareSession
{
    public function __construct(private readonly ProductComparisonService $comparison) {}

    public function products(Session $session, ?array $selection = null): Collection
    {
        $slugs = $selection ?? $session->get('compare.products', []);
        $slugs = is_array($slugs) ? array_values(array_unique(array_filter($slugs, 'is_string'))) : [];
        $products = $this->comparison->getProducts($slugs);
        $this->store($session, $products->pluck('slug')->all());

        return $products;
    }

    public function add(Session $session, string $slug): array
    {
        $slugs = $this->products($session)->pluck('slug')->all();
        if (! in_array($slug, $slugs, true)) {
            if (count($slugs) >= ProductComparisonService::MAX_PRODUCTS) {
                throw ValidationException::withMessages(['slug' => 'Chỉ có thể so sánh tối đa 4 sản phẩm cùng lúc.']);
            }
            $slugs[] = $slug;
        }

        $this->products($session, $slugs);

        return $this->items($session);
    }

    public function remove(Session $session, string $slug): array
    {
        $slugs = $this->products($session)->pluck('slug')->reject(fn ($item) => $item === $slug)->values()->all();
        $this->store($session, $slugs);

        return $this->items($session);
    }

    public function clear(Session $session): array
    {
        $session->forget('compare.products');

        return [];
    }

    public function items(Session $session): array
    {
        return $this->products($session)->map(fn ($product) => [
            'slug' => $product->slug,
            'name' => $product->name,
            'image_url' => $product->compare_image_url,
        ])->all();
    }

    private function store(Session $session, array $slugs): void
    {
        if ($slugs === []) {
            $session->forget('compare.products');
        } else {
            $session->put('compare.products', $slugs);
        }
    }
}
