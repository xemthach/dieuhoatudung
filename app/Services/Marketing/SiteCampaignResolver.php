<?php

namespace App\Services\Marketing;

use App\Models\SiteCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SiteCampaignResolver
{
    public function forRequest(Request $request): Collection
    {
        if (! Schema::hasTable('site_campaigns')) {
            return collect();
        }

        $placement = $this->placementForRoute((string) $request->route()?->getName());
        $path = '/'.ltrim($request->path(), '/');
        $url = $request->fullUrl();

        $matches = SiteCampaign::active()
            ->whereIn('placement', ['all', $placement])
            ->limit(25)
            ->get()
            ->filter(fn (SiteCampaign $campaign): bool => $this->matchesUrlRules($campaign, $path, $url))
            ->values();

        return $this->limitConflicts($matches);
    }

    protected function placementForRoute(string $routeName): string
    {
        return match (true) {
            $routeName === 'home' => 'home',
            $routeName === 'product.show' => 'product',
            $routeName === 'category.show' => 'product_category',
            $routeName === 'brands.show' => 'brand',
            $routeName === 'blog.index' => 'blog_list',
            $routeName === 'blog.show' => 'blog_post',
            str_starts_with($routeName, 'policy-pages.') => 'policy',
            str_starts_with($routeName, 'quote.') => 'quote',
            $routeName === 'search.index' => 'search',
            str_starts_with($routeName, 'compare.') => 'compare',
            default => 'all',
        };
    }

    protected function matchesUrlRules(SiteCampaign $campaign, string $path, string $url): bool
    {
        $targeting = $campaign->targeting_json ?? [];

        $exact = $this->lines($targeting['exact_urls'] ?? null);
        if ($exact !== [] && ! in_array($path, $exact, true) && ! in_array($url, $exact, true)) {
            return false;
        }

        $startsWith = $this->lines($targeting['starts_with'] ?? null);
        if ($startsWith !== [] && ! collect($startsWith)->contains(fn (string $rule): bool => str_starts_with($path, $rule) || str_starts_with($url, $rule))) {
            return false;
        }

        $contains = $this->lines($targeting['contains'] ?? null);
        if ($contains !== [] && ! collect($contains)->contains(fn (string $rule): bool => str_contains($path, $rule) || str_contains($url, $rule))) {
            return false;
        }

        $regex = $this->lines($targeting['regex'] ?? null);
        foreach ($regex as $pattern) {
            if (@preg_match($pattern, '') !== false && preg_match($pattern, $url)) {
                return true;
            }
        }

        return $regex === [];
    }

    protected function limitConflicts(Collection $campaigns): Collection
    {
        $groups = [
            'modal' => ['modal', 'slide_in', 'image_popup', 'video_popup', 'product_promo'],
            'top_bar' => ['top_bar'],
            'bottom_bar' => ['bottom_bar'],
            'floating_cta' => ['floating_cta'],
        ];

        return collect($groups)
            ->flatMap(function (array $types) use ($campaigns) {
                return $campaigns->whereIn('type', $types)->take(1);
            })
            ->values();
    }

    protected function lines(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $value) ?: [])));
    }
}
