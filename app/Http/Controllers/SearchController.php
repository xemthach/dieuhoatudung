<?php

namespace App\Http\Controllers;

use App\Services\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly ProductSearchService $searchService
    ) {}

    /**
     * Autocomplete suggest API.
     * GET /api/search/suggest?q=...
     */
    public function suggest(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        if (mb_strlen(trim($q)) < ProductSearchService::MIN_QUERY_LENGTH) {
            return response()->json(['data' => []]);
        }

        if (mb_strlen($q) > ProductSearchService::MAX_QUERY_LENGTH) {
            return response()->json(['data' => [], 'error' => 'Từ khóa quá dài'], 400);
        }

        $results = $this->searchService->suggest($q);

        // Strip internal fields for API response
        $data = array_map(function ($item) {
            return [
                'id'       => $item['id'],
                'name'     => $item['name'],
                'model'    => $item['model'] ?? '',
                'sku'      => $item['sku'] ?? '',
                'brand'    => $item['brand'] ?? '',
                'btu'      => $item['btu'],
                'image'    => $item['image'],
                'url'      => $item['url'],
            ];
        }, $results);

        return response()->json(['data' => $data]);
    }

    /**
     * Search results page.
     * GET /tim-kiem?q=...
     */
    public function index(Request $request)
    {
        $rawQuery = $request->input('q', '');
        $q = ProductSearchService::normalizeQuery($rawQuery);

        $products = null;
        $resultCount = 0;

        if (mb_strlen($q) >= ProductSearchService::MIN_QUERY_LENGTH) {
            $products = $this->searchService->search($q, 12);
            $resultCount = $products->total();

            // Log search (async, non-blocking)
            $this->searchService->logSearch(
                $rawQuery,
                $q,
                $resultCount,
                $request->ip(),
                $request->userAgent()
            );
        }

        $seoTitle = $q ? "Tìm kiếm: {$q}" : 'Tìm kiếm sản phẩm';
        $seoDescription = "Kết quả tìm kiếm sản phẩm điều hòa cho từ khóa \"{$q}\". Tìm nhanh theo mã model, SKU, thương hiệu, công suất BTU.";

        return view('pages.search', compact('products', 'q', 'resultCount', 'seoTitle', 'seoDescription'));
    }
}
