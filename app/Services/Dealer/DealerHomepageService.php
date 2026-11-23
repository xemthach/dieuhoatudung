<?php

namespace App\Services\Dealer;

use App\Models\AdministrativeUnit;
use App\Models\Dealer;
use Illuminate\Support\Collection;

final class DealerHomepageService
{
    public function enabled(): bool
    {
        return app(\App\Services\Navigation\HomepageLayout::class)->enabled('dealers');
    }

    public function limit(): int
    {
        return min(12, max(1, (int) setting('display.homepage_dealers_limit', 4)));
    }

    /** @return array<string, bool> */
    public function cardOptions(): array
    {
        return [
            'address' => (bool) setting('display.homepage_dealers_show_address', true),
            'contact' => (bool) setting('display.homepage_dealers_show_contact', true),
            'logo' => (bool) setting('display.homepage_dealers_show_logo', true),
            'verified' => (bool) setting('display.homepage_dealers_show_verified', true),
            'distance' => (bool) setting('display.homepage_dealers_show_distance', true),
            'rating' => (bool) setting('display.homepage_dealers_show_rating', true),
            'review_count' => (bool) setting('display.homepage_dealers_show_review_count', true),
            'brands' => (bool) setting('display.homepage_dealers_show_brands', true),
            'categories' => (bool) setting('display.homepage_dealers_show_categories', true),
            'services' => (bool) setting('display.homepage_dealers_show_services', true),
            'phone' => (bool) setting('display.homepage_dealers_show_phone', true),
            ...$this->displayOptions(),
        ];
    }

    /** @return array{brand_style:string,product_line_style:string,service_style:string,card_style:string,max_brands:int,max_product_lines:int,max_services:int} */
    public function displayOptions(): array
    {
        return [
            'brand_style' => $this->choice('brand_style', ['logo', 'badge', 'logo_name', 'text'], 'logo_name'),
            'product_line_style' => $this->choice('product_line_style', ['icon_badge', 'badge', 'icon_list', 'grid'], 'icon_badge'),
            'service_style' => $this->choice('service_style', ['icon_badge', 'badge', 'compact', 'grid'], 'icon_badge'),
            'card_style' => $this->choice('card_style', ['compact', 'standard', 'detailed'], 'standard'),
            'max_brands' => $this->limitSetting('max_brands', 4),
            'max_product_lines' => $this->limitSetting('max_product_lines', 4),
            'max_services' => $this->limitSetting('max_services', 4),
        ];
    }

    /** @return list<string> */
    public function sectionOrder(): array
    {
        return app(\App\Services\Navigation\HomepageLayout::class)->order();
    }

    private function choice(string $key, array $allowed, string $default): string
    {
        $value = (string) setting('display.dealer_'.$key, $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function limitSetting(string $key, int $default): int
    {
        return min(12, max(1, (int) setting('display.dealer_'.$key, $default)));
    }

    /** @return array{dealers: Collection<int, Dealer>, filters: array<string, mixed>, mode: string} */
    public function resolve(array $query, DealerDirectoryService $directory): array
    {
        if (! $this->enabled()) {
            return ['dealers' => collect(), 'filters' => [], 'mode' => 'disabled'];
        }

        $mode = (string) setting('display.homepage_dealers_mode', 'auto');
        if (! in_array($mode, ['auto', 'nearby', 'featured', 'region', 'manual'], true)) {
            $mode = 'auto';
        }

        $filters = $directory->filters(array_intersect_key($query, array_flip(['scheme', 'province', 'district', 'ward'])));
        $hasSelectedRegion = ! empty($filters['province']) || ! empty($filters['district']) || ! empty($filters['ward']);
        $limit = $this->limit();

        if ($mode === 'manual') {
            $ids = setting('display.homepage_dealers_manual', []);
            $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];
            $rows = Dealer::publiclyListed()->whereIn('id', $ids)->with($this->relations())
                ->withCount('publicReviews as review_count')->withAvg('publicReviews as average_rating', 'rating')->get()
                ->keyBy('id');

            return ['dealers' => collect($ids)->map(fn (int $id) => $rows->get($id))->filter()->take($limit)->values(),
                'filters' => $filters, 'mode' => $mode];
        }

        $useRegion = ($mode === 'region' && $hasSelectedRegion) || ($mode === 'auto' && $hasSelectedRegion);
        if ($mode === 'region' && ! $hasSelectedRegion) {
            $defaultProvince = (int) setting('display.homepage_dealers_default_province', 0);
            $province = AdministrativeUnit::query()->whereKey($defaultProvince)->where('scheme', 'current')
                ->where('kind', 'province')->where('dataset_version', config('dealers.current_version'))->first();
            if ($province) {
                $filters = $directory->filters(['scheme' => 'current', 'province' => $province->id]);
                $useRegion = true;
            }
        }

        $base = $directory->query($filters)->withCount('publicReviews as review_count')
            ->withAvg('publicReviews as average_rating', 'rating');
        if ($useRegion) {
            $dealers = $base->limit($limit)->get();
        } else {
            // Nearby waits for an explicit user click; initial page render uses featured dealers only.
            $dealers = $base->where('is_featured', true)->orderBy('sort_order')->orderBy('dealers.id')
                ->limit($limit)->get();
        }

        return ['dealers' => $dealers, 'filters' => $filters, 'mode' => $mode];
    }

    private function relations(): array
    {
        return ['province', 'ward', 'legacyProvince', 'legacyDistrict', 'legacyWard',
            'brands' => fn ($q) => $q->where('is_active', true),
            'categories' => fn ($q) => $q->where('is_active', true),
            'services' => fn ($q) => $q->where('is_active', true)];
    }
}
