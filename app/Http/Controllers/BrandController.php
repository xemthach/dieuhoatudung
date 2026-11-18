<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Services\Product\ProductFilterService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return view('brands.index', compact('brands'));
    }

    public function show(Request $request, string $slug, ProductFilterService $filterService)
    {
        $brand = Brand::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $query = Product::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->with(['brand', 'category']);

        $sort = $filterService->normalizeSort($request->query('sort'));
        $filterService->applySort($query, $sort);

        $products = $query->paginate(setting('display.products_per_page', 12))->withQueryString();
        $hasActiveSort = $sort !== 'latest';

        return view('brands.show', compact('brand', 'products', 'hasActiveSort'));
    }
}
