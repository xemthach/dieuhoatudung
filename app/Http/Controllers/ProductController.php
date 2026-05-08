<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Brand;
use App\Services\Product\ProductFilterService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Trang danh sách / landing sản phẩm điều hòa tủ đứng.
     */
    public function index(Request $request, ProductFilterService $filterService)
    {
        $perPage = (int) setting('display.products_per_page', 12);
        
        $query = Product::with(['brand', 'category'])
            ->where('is_active', true);
            
        $query = $filterService->apply($query, $request);
        
        $products = $query->paginate($perPage)->withQueryString();

        $categories = ProductCategory::where('is_active', true)
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        $brands = Brand::where('is_active', true)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $hasActiveFilters = $filterService->hasActiveFilters($request);

        return view('products.index', compact('products', 'categories', 'brands', 'hasActiveFilters'));
    }

    /**
     * Trang danh mục sản phẩm theo category slug.
     */
    public function category(string $categorySlug, Request $request, ProductFilterService $filterService)
    {
        $category = ProductCategory::with('activeFaqs')
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $perPage = (int) setting('display.products_per_page', 12);

        $query = Product::with(['brand', 'category'])
            ->where('product_category_id', $category->id)
            ->where('is_active', true);

        $query = $filterService->apply($query, $request);
        
        $products = $query->paginate($perPage)->withQueryString();

        $categories = ProductCategory::where('is_active', true)
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        $brands = Brand::where('is_active', true)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $hasActiveFilters = $filterService->hasActiveFilters($request);

        return view('products.category', compact('category', 'products', 'categories', 'brands', 'hasActiveFilters'));
    }

    /**
     * Trang chi tiết sản phẩm.
     */
    public function show(string $slug)
    {
        $product = Product::with(['brand', 'category', 'tags', 'activeFaqs', 'publicDocuments', 'activeTestimonials', 'relatedProducts.brand'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $relatedProducts = $product->relatedProducts()
            ->where('is_active', true)
            ->take(4)
            ->get();

        // Nếu không có related products thì lấy cùng category
        if ($relatedProducts->isEmpty()) {
            $relatedProducts = Product::with('brand')
                ->where('product_category_id', $product->product_category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->take(4)
                ->get();
        }

        // Reviews
        $enableReviews = setting('product_detail.enable_reviews', true);
        $reviews = collect();
        $ratingStats = null;

        if ($enableReviews) {
            $reviews = $product->approvedReviews()->take(5)->get();

            // Rating statistics
            $allApproved = $product->approvedReviews();
            $totalReviews = $allApproved->count();
            $avgRating = $totalReviews > 0 ? round($allApproved->avg('rating'), 1) : 0;

            $ratingBreakdown = [];
            if ($totalReviews > 0) {
                for ($i = 5; $i >= 1; $i--) {
                    $count = $product->approvedReviews()->where('rating', $i)->count();
                    $ratingBreakdown[$i] = [
                        'count' => $count,
                        'percent' => round(($count / $totalReviews) * 100),
                    ];
                }
            }

            $ratingStats = [
                'average' => $avgRating,
                'total' => $totalReviews,
                'breakdown' => $ratingBreakdown,
            ];
        }

        // Questions
        $enableQuestions = setting('product_detail.enable_questions', true);
        $questions = collect();

        if ($enableQuestions) {
            $questionsQuery = $product->publicQuestions();

            if (setting('product_detail.question_show_only_answered', false)) {
                $questionsQuery->whereNotNull('answer');
            }

            $questions = $questionsQuery->take(5)->get();
        }

        // Settings for frontend
        $reviewSettings = [
            'enabled' => $enableReviews,
            'require_phone' => setting('product_detail.review_require_phone', false),
            'allow_images' => setting('product_detail.review_allow_images', true),
            'max_images' => (int) setting('product_detail.review_max_images', 3),
            'show_verified_badge' => setting('product_detail.review_show_verified_badge', true),
        ];

        $questionSettings = [
            'enabled' => $enableQuestions,
            'require_phone' => setting('product_detail.question_require_phone', false),
        ];

        $descriptionSettings = [
            'collapsible' => setting('product_detail.enable_collapsible_description', true),
            'collapsed_height' => (int) setting('product_detail.description_collapsed_height', 420),
            'show_button' => setting('product_detail.show_read_more_button', true),
        ];

        return view('products.show', compact(
            'product',
            'relatedProducts',
            'reviews',
            'ratingStats',
            'questions',
            'reviewSettings',
            'questionSettings',
            'descriptionSettings'
        ));
    }
}
