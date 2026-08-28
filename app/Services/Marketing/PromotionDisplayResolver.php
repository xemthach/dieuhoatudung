<?php

namespace App\Services\Marketing;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PromotionDisplayResolver
{
    public const PLACEMENTS = [
        'landing' => 'Trang landing',
        'banner' => 'Banner trang chủ',
        'popup' => 'Popup theo ngữ cảnh',
        'announcement_bar' => 'Thanh thông báo toàn site',
    ];

    public function forRequest(Request $request): Collection
    {
        if (! Schema::hasTable('promotions') || ! Schema::hasColumn('promotions', 'placement')) {
            return collect();
        }

        $routeName = (string) $request->route()?->getName();
        $placements = match ($routeName) {
            'home' => ['banner', 'popup', 'announcement_bar'],
            'landing' => ['landing', 'popup', 'announcement_bar'],
            default => ['popup', 'announcement_bar'],
        };

        $product = $this->productContext($request, $routeName);

        return Promotion::query()
            ->currentlyActive()
            ->whereIn('placement', $placements)
            ->where(function ($query) use ($product) {
                $query->where('scope', 'global');

                if ($product) {
                    $query
                        ->orWhere(fn ($q) => $q->where('scope', 'product')->whereHas('products', fn ($r) => $r->whereKey($product->id)))
                        ->orWhere(fn ($q) => $q->where('scope', 'category')->whereHas('categories', fn ($r) => $r->whereKey($product->product_category_id)))
                        ->orWhere(fn ($q) => $q->where('scope', 'brand')->whereHas('brands', fn ($r) => $r->whereKey($product->brand_id)));
                }
            })
            ->orderByDesc('id')
            ->get()
            ->unique('placement')
            ->values();
    }

    public function isRenderable(Promotion $promotion): bool
    {
        return array_key_exists((string) $promotion->placement, self::PLACEMENTS)
            && collect([$promotion->title, $promotion->description, $promotion->content, $promotion->banner_copy, $promotion->cta_content])
                ->contains(fn ($value): bool => filled($value));
    }

    private function productContext(Request $request, string $routeName): ?Product
    {
        if ($routeName !== 'product.show') {
            return null;
        }

        $parameter = $request->route()?->parameter('slug');
        if ($parameter instanceof Product) {
            return $parameter;
        }

        return is_string($parameter) ? Product::query()->where('slug', $parameter)->first() : null;
    }
}
