<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Product\ProductComparisonService;
use App\Services\Product\ProductCompareSession;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    public function __construct(
        private readonly ProductComparisonService $comparisonService,
        private readonly ProductCompareSession $compareSession
    ) {}

    /**
     * Display the compare page.
     */
    public function index(Request $request)
    {
        $request->validate(['products' => 'sometimes|nullable|string|max:2048']);
        $selection = $request->has('products') ? explode(',', (string) $request->query('products')) : null;
        $products = $this->compareSession->products($request->session(), $selection);

        // 3. SEO Metadata
        $scope = app(\App\Services\Navigation\SiteProductScope::class);
        $compareTitle = $scope->compareTitle();
        if ($products->pluck('product_category_id')->unique()->count() > 1) {
            $compareTitle = 'So sánh sản phẩm';
        }
        $productUrl = $scope->productUrl();
        $seoTitle = $compareTitle;
        if ($products->count() > 0) {
            $names = $products->pluck('name')->join(' vs ');
            $seoTitle = "So sánh: {$names}";
        }
        
        $seoDescription = $scope->compareDescription();
        $canonical = route('compare.index');

        // 4. Build grouped compare specs using service
        $groupedSpecs = [];
        if ($products->count() > 0) {
            $groupedSpecs = $this->comparisonService->buildGroupedSpecs($products);
        }

        return view('pages.compare', compact('products', 'groupedSpecs', 'seoTitle', 'seoDescription', 'canonical', 'compareTitle', 'productUrl'));
    }

    /**
     * Add a product to compare list.
     */
    public function add(Request $request)
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', Rule::exists('products', 'slug')->where('is_active', true)],
        ]);
        $items = $this->compareSession->add($request->session(), $validated['slug']);

        return $this->mutationResponse($request, $items, 'Đã thêm vào danh sách so sánh.');
    }

    public function remove(Request $request)
    {
        $validated = $request->validate([
            'slug' => ['required_without:product_id', 'nullable', 'string', 'max:255', 'exists:products,slug'],
            'product_id' => ['required_without:slug', 'nullable', 'integer', 'min:1', 'exists:products,id'],
        ]);
        $slug = $validated['slug'] ?? Product::query()->findOrFail($validated['product_id'])->slug;
        $items = $this->compareSession->remove($request->session(), $slug);

        return $this->mutationResponse($request, $items, 'Đã xóa sản phẩm khỏi danh sách.');
    }

    public function clear(Request $request)
    {
        $items = $this->compareSession->clear($request->session());

        return $this->mutationResponse($request, $items, 'Đã xóa tất cả sản phẩm so sánh.');
    }

    private function mutationResponse(Request $request, array $items, string $message)
    {
        if (! $request->expectsJson()) {
            return redirect()->route('compare.index')->with('status', $message);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'count' => count($items),
            'items' => $items,
        ]);
    }

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
