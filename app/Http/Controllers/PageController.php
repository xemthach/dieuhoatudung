<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Brand;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Product;
use App\Services\Dealer\DealerDirectoryService;
use App\Services\Dealer\DealerHomepageService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home(Request $request, DealerDirectoryService $directory, DealerHomepageService $dealerHomepage)
    {
        $featuredProducts = Product::with('brand')
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        // Nếu chưa đủ featured → lấy thêm bestseller hoặc mới nhất
        if ($featuredProducts->count() < 4) {
            $featuredProducts = Product::with('brand')
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->orderByDesc('is_bestseller')
                ->orderByDesc('created_at')
                ->take(8)
                ->get();
        }

        $brands = Brand::where('is_active', true)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $latestPosts = Post::with(['category', 'author'])
            ->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        $faqs = Faq::where('is_active', true)
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        $homepageDealerState = $dealerHomepage->resolve($request->all(), $directory);
        $homepageDealersEnabled = $dealerHomepage->enabled();
        $homepageDealers = $homepageDealerState['dealers'];
        $homepageDealerFilters = $homepageDealerState['filters'];
        $homepageDealerMode = $homepageDealerState['mode'];
        $homepageDealerCardOptions = $dealerHomepage->cardOptions();
        $homepageSectionOrder = $dealerHomepage->sectionOrder();
        $homepageDealerGeolocation = (bool) setting('display.homepage_dealers_geolocation', true);
        $homepageDealerTitle = (string) setting('display.homepage_dealers_title', 'Đại lý gần bạn');
        $homepageDealerSubtitle = (string) setting('display.homepage_dealers_subtitle', 'Tìm đại lý và đơn vị dịch vụ gần khu vực của bạn.');
        $homepageDealerLimit = $dealerHomepage->limit();
        $homepageDealerRadius = (int) setting('display.homepage_dealers_radius_km', 20);

        return view('pages.home', compact(
            'featuredProducts',
            'brands',
            'latestPosts',
            'faqs',
            'homepageDealersEnabled',
            'homepageDealers',
            'homepageDealerFilters',
            'homepageDealerMode',
            'homepageDealerCardOptions',
            'homepageSectionOrder',
            'homepageDealerGeolocation',
            'homepageDealerTitle',
            'homepageDealerSubtitle',
            'homepageDealerLimit',
            'homepageDealerRadius'
        ));
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function quote()
    {
        return view('pages.quote');
    }
}
