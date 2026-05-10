<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Product\ProductComparisonService;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    private const MAX_PRODUCTS = 4;

    public function __construct(
        private readonly ProductComparisonService $comparisonService
    ) {}

    /**
     * Display the compare page.
     */
    public function index(Request $request)
    {
        // 1. Get slugs from query string OR session
        if ($request->has('products')) {
            $slugs = array_filter(explode(',', $request->query('products')));
            // Save to session
            $slugs = array_slice($slugs, 0, self::MAX_PRODUCTS);
            $request->session()->put('compare.products', $slugs);
        } else {
            $slugs = $request->session()->get('compare.products', []);
        }

        // 2. Fetch products using service
        $products = $this->comparisonService->getProducts($slugs);

        // 3. SEO Metadata
        $seoTitle = 'So sánh sản phẩm điều hòa tủ đứng';
        if ($products->count() > 0) {
            $names = $products->pluck('name')->join(' vs ');
            $seoTitle = "So sánh: {$names}";
        }
        
        $seoDescription = 'Công cụ so sánh thông số kỹ thuật, tính năng, và giá bán các dòng máy lạnh điều hòa tủ đứng. Giúp bạn chọn mua sản phẩm phù hợp nhất.';
        $canonical = url('/so-sanh-san-pham');

        // 4. Build grouped compare specs using service
        $groupedSpecs = [];
        if ($products->count() > 0) {
            $groupedSpecs = $this->comparisonService->buildGroupedSpecs($products);
        }

        return view('pages.compare', compact('products', 'groupedSpecs', 'seoTitle', 'seoDescription', 'canonical'));
    }

    /**
     * Add a product to compare list.
     */
    public function add(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string|exists:products,slug',
        ]);

        $slug = $validated['slug'];
        $slugs = $request->session()->get('compare.products', []);

        if (in_array($slug, $slugs)) {
            return response()->json([
                'success' => true,
                'message' => 'Sản phẩm đã có trong danh sách so sánh.',
                'count'   => count($slugs)
            ]);
        }

        if (count($slugs) >= self::MAX_PRODUCTS) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể so sánh tối đa 4 sản phẩm cùng lúc.'
            ], 400);
        }

        $slugs[] = $slug;
        $request->session()->put('compare.products', $slugs);

        // Optional: tracking compare event here if needed
        $product = Product::where('slug', $slug)->first();

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm vào danh sách so sánh.',
            'count'   => count($slugs),
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'image_url' => $product->compare_image_url
            ]
        ]);
    }

    /**
     * Remove a product from compare list.
     */
    public function remove(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string',
        ]);

        $slug = $validated['slug'];
        $slugs = $request->session()->get('compare.products', []);
        
        $slugs = array_values(array_filter($slugs, fn($s) => $s !== $slug));
        $request->session()->put('compare.products', $slugs);

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa sản phẩm khỏi danh sách.',
            'count'   => count($slugs)
        ]);
    }

    /**
     * Clear all products from compare list.
     */
    public function clear(Request $request)
    {
        $request->session()->forget('compare.products');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã xóa tất cả sản phẩm so sánh.',
                'count'   => 0
            ]);
        }

        return redirect()->route('compare.index');
    }

    // ──────────────────────────────────────────────
    // Export endpoints
    // ──────────────────────────────────────────────

    /**
     * Export comparison to PDF.
     */
    public function exportPdf(Request $request)
    {
        $products = $this->resolveExportProducts($request);

        if ($products->isEmpty()) {
            return back()->with('error', 'Không có sản phẩm nào để xuất.');
        }

        return $this->comparisonService->exportPdf($products);
    }

    /**
     * Export comparison to Excel XLSX.
     */
    public function exportExcel(Request $request)
    {
        $products = $this->resolveExportProducts($request);

        if ($products->isEmpty()) {
            return back()->with('error', 'Không có sản phẩm nào để xuất.');
        }

        return $this->comparisonService->exportExcel($products);
    }

    /**
     * Export comparison to CSV.
     */
    public function exportCsv(Request $request)
    {
        $products = $this->resolveExportProducts($request);

        if ($products->isEmpty()) {
            return back()->with('error', 'Không có sản phẩm nào để xuất.');
        }

        return $this->comparisonService->exportCsv($products);
    }

    /**
     * Resolve products for export from query string or session.
     */
    private function resolveExportProducts(Request $request): \Illuminate\Support\Collection
    {
        // Try query string first
        if ($request->has('products')) {
            $slugs = array_filter(explode(',', $request->query('products')));
        } else {
            $slugs = $request->session()->get('compare.products', []);
        }

        return $this->comparisonService->getProducts($slugs);
    }
}
