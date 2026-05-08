<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
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

    public function show(Request $request, string $slug)
    {
        $brand = Brand::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $query = Product::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->with(['brand', 'category']);

        // Apply sorting
        $query = match($request->query('sort')) {
            'price_asc'  => $query->orderByRaw('COALESCE(sale_price, regular_price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, regular_price) DESC'),
            'btu_asc'    => $query->orderBy('btu', 'asc'),
            'btu_desc'   => $query->orderBy('btu', 'desc'),
            default      => $query->latest(),
        };

        $products = $query->paginate(setting('display.products_per_page', 12));

        return view('brands.show', compact('brand', 'products'));
    }
}
