<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Brand;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Product;

class PageController extends Controller
{
    public function home()
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

        return view('pages.home', compact(
            'featuredProducts',
            'brands',
            'latestPosts',
            'faqs'
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
