<?php

namespace App\Services\Sitemap;

use App\Enums\PostStatus;
use App\Enums\CaseStudyStatus;
use App\Models\CaseStudy;
use App\Models\Post;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PolicyPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SitemapService
{
    /**
     * Sinh XML cho sitemap index (trỏ đến các sub-sitemaps).
     */
    public function buildIndex(): string
    {
        $baseUrl = config('seo.sitemap.base_url', config('app.url'));

        $sitemaps = [];

        if (setting('sitemap.sitemap_enabled', true)) {
            $sitemaps[] = [
                'loc'     => $baseUrl . '/sitemap-static.xml',
                'lastmod' => Carbon::now()->toAtomString(),
            ];
            
            if (setting('sitemap.sitemap_include_products', true)) {
                $sitemaps[] = [
                    'loc'     => $baseUrl . '/sitemap-products.xml',
                    'lastmod' => Carbon::now()->toAtomString(),
                ];
            }
            
            if (setting('sitemap.sitemap_include_categories', true)) {
                $sitemaps[] = [
                    'loc'     => $baseUrl . '/sitemap-categories.xml',
                    'lastmod' => Carbon::now()->toAtomString(),
                ];
            }
            
            if (setting('sitemap.sitemap_include_posts', true)) {
                $sitemaps[] = [
                    'loc'     => $baseUrl . '/sitemap-posts.xml',
                    'lastmod' => Carbon::now()->toAtomString(),
                ];
            }

            // Brands sitemap
            $sitemaps[] = [
                'loc'     => $baseUrl . '/sitemap-brands.xml',
                'lastmod' => Carbon::now()->toAtomString(),
            ];

            // Case Studies sitemap
            if (setting('sitemap.sitemap_include_case_studies', false)) {
                $sitemaps[] = [
                    'loc'     => $baseUrl . '/sitemap-case-studies.xml',
                    'lastmod' => Carbon::now()->toAtomString(),
                ];
            }
        }

        return view('seo.sitemap-index', compact('sitemaps'))->render();
    }

    /**
     * Sitemap cho sản phẩm.
     */
    public function buildProducts(): string
    {
        $products = Product::where('is_active', true)
            ->when(setting('sitemap.sitemap_exclude_noindex', true), fn ($query) => $this->withoutNoindex($query))
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        $urls = $products->map(fn ($p) => [
            'loc'        => route('product.show', $p->slug),
            'lastmod'    => $p->updated_at->toAtomString(),
            'changefreq' => config('seo.sitemap.products_changefreq', 'weekly'),
            'priority'   => config('seo.sitemap.products_priority', '0.8'),
        ]);

        return view('seo.sitemap-urlset', compact('urls'))->render();
    }

    /**
     * Sitemap cho danh mục sản phẩm.
     */
    public function buildCategories(): string
    {
        $categories = ProductCategory::where('is_active', true)
            ->when(setting('sitemap.sitemap_exclude_noindex', true), fn ($query) => $this->withoutNoindex($query))
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        $urls = $categories->map(fn ($c) => [
            'loc'        => route('category.show', $c->slug),
            'lastmod'    => $c->updated_at->toAtomString(),
            'changefreq' => config('seo.sitemap.categories_changefreq', 'weekly'),
            'priority'   => config('seo.sitemap.categories_priority', '0.6'),
        ]);

        return view('seo.sitemap-urlset', compact('urls'))->render();
    }

    /**
     * Sitemap cho bài viết blog.
     */
    public function buildPosts(): string
    {
        $posts = Post::where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at']);

        $urls = $posts->map(fn ($p) => [
            'loc'        => route('blog.show', $p->slug),
            'lastmod'    => $p->updated_at->toAtomString(),
            'changefreq' => config('seo.sitemap.posts_changefreq', 'weekly'),
            'priority'   => config('seo.sitemap.posts_priority', '0.7'),
        ]);

        return view('seo.sitemap-urlset', compact('urls'))->render();
    }

    /**
     * Sitemap cho thương hiệu.
     */
    public function buildBrands(): string
    {
        $brands = Brand::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('robots')->orWhere('robots', 'not like', '%noindex%');
            })
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        $urls = $brands->map(fn ($b) => [
            'loc'        => route('brands.show', $b->slug),
            'lastmod'    => $b->updated_at->toAtomString(),
            'changefreq' => 'weekly',
            'priority'   => '0.6',
        ]);

        return view('seo.sitemap-urlset', compact('urls'))->render();
    }

    /**
     * Sitemap cho dự án (case studies).
     */
    public function buildCaseStudies(): string
    {
        $caseStudies = CaseStudy::where('status', CaseStudyStatus::Published)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at']);

        $urls = $caseStudies->map(fn ($cs) => [
            'loc'        => route('case-studies.show', $cs->slug),
            'lastmod'    => $cs->updated_at->toAtomString(),
            'changefreq' => 'monthly',
            'priority'   => '0.6',
        ]);

        return view('seo.sitemap-urlset', compact('urls'))->render();
    }

    /**
     * Sitemap cho các trang tĩnh.
     */
    public function buildStatic(): string
    {
        $urls = collect([
            [
                'loc'        => route('home'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'daily',
                'priority'   => '1.0',
            ],
            [
                'loc'        => route('landing'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority'   => '0.9',
            ],
            [
                'loc'        => route('products.index'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'daily',
                'priority'   => '0.8',
            ],
            [
                'loc'        => route('blog.index'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'daily',
                'priority'   => '0.7',
            ],
            [
                'loc'        => route('contact'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority'   => '0.5',
            ],
            [
                'loc'        => route('quote.index'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority'   => '0.5',
            ],
            [
                'loc'        => route('brands.index'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ],
        ]);

        // Add active, indexable policy pages
        $policyPages = PolicyPage::active()
            ->where(function ($q) {
                $q->whereNull('robots')->orWhere('robots', 'not like', '%noindex%');
            })
            ->get();

        foreach ($policyPages as $policy) {
            $urls->push([
                'loc'        => route('policy-pages.show', $policy->slug),
                'lastmod'    => $policy->updated_at->toAtomString(),
                'changefreq' => 'monthly',
                'priority'   => '0.4',
            ]);
        }

        // FAQ page
        try {
            $urls->push([
                'loc'        => route('faq.dieu-hoa'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority'   => '0.5',
            ]);
        } catch (\Exception $e) {}

        // BTU Calculator
        try {
            $urls->push([
                'loc'        => route('btu-calculator.index'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority'   => '0.5',
            ]);
        } catch (\Exception $e) {}

        // Price List
        try {
            $urls->push([
                'loc'        => route('price-list'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ]);
        } catch (\Exception $e) {}

        // Case Studies Index
        try {
            $urls->push([
                'loc'        => route('case-studies.index'),
                'lastmod'    => Carbon::now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority'   => '0.5',
            ]);
        } catch (\Exception $e) {}

        // Compare Page
        // The compare page is session/state based and should stay out of XML sitemaps.

        return view('seo.sitemap-urlset', compact('urls'))->render();
    }

    protected function withoutNoindex($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('robots')->orWhere('robots', 'not like', '%noindex%');
        });
    }
}
