<?php

namespace App\Http\Controllers;

use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Faq;
use App\Models\Product;
use Illuminate\Http\Request;

use App\Services\Product\ProductFilterService;

class PriceListController extends Controller
{
    public function index(Request $request, ProductFilterService $filterService)
    {
        $perPage = (int) setting('display.products_per_page', 12);

        // ── Base query ─────────────────────────────────────────
        $query = Product::with(['brand', 'category'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('regular_price')
                  ->orWhereNotNull('sale_price');
            });

        $query = $filterService->apply($query, $request);

        // ── Sort overrides for PriceList ───────────────
        if (!$request->has('sort')) {
            $query->orderBy('brand_id')->orderBy('btu')->orderByRaw('COALESCE(sale_price, regular_price) ASC');
        }

        $products = $query->paginate($perPage)->withQueryString();

        // ── Filter options (for dropdowns) ────────────────────
        $filters = $request->all();
        $hasFilter = $filterService->hasActiveFilters($request);

        // ── Filter options (for dropdowns) ────────────────────
        $brands = Brand::where('is_active', true)
            ->whereHas('products', fn($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        $btuOptions = Product::where('is_active', true)
            ->whereNotNull('btu')
            ->distinct()
            ->orderBy('btu')
            ->pluck('btu');

        // ── FAQs bảng giá ─────────────────────────────────────
        $faqs = Faq::where('is_active', true)
            ->where('group', 'bang_gia')
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        // Fallback nếu không có FAQ theo group
        if ($faqs->isEmpty()) {
            $faqs = collect([
                (object)[
                    'question' => 'Giá điều hòa tủ đứng trên trang có phải giá chính thức không?',
                    'answer'   => 'Giá niêm yết trên trang là giá tham khảo từ nhà sản xuất, giá thực tế có thể thay đổi theo chương trình khuyến mãi. Vui lòng liên hệ để nhận báo giá chính xác nhất.',
                ],
                (object)[
                    'question' => 'Điều hòa tủ đứng bao nhiêu BTU phù hợp với văn phòng 50m²?',
                    'answer'   => 'Văn phòng 50m² với trần cao 3m thường cần khoảng 24.000–28.000 BTU. Sử dụng công cụ tính BTU để có kết quả chính xác theo điều kiện thực tế.',
                ],
                (object)[
                    'question' => 'Có bao gồm chi phí lắp đặt không?',
                    'answer'   => 'Giá trên bảng giá là giá máy chưa bao gồm lắp đặt. Chúng tôi hỗ trợ lắp đặt trọn gói, vui lòng liên hệ để nhận báo giá lắp đặt.',
                ],
                (object)[
                    'question' => 'Chênh lệch giữa giá niêm yết và giá bán thực tế là bao nhiêu?',
                    'answer'   => 'Tùy thương hiệu và chương trình khuyến mãi, giá bán có thể thấp hơn 5–20% so với giá niêm yết. Liên hệ trực tiếp để nhận ưu đãi tốt nhất.',
                ],
            ]);
        }

        // ── SEO ───────────────────────────────────────────────
        $seoTitle       = 'Bảng Giá Điều Hòa Tủ Đứng Mới Nhất ' . date('Y') . ' - Tất Cả Thương Hiệu';
        $seoDescription = 'Xem bảng giá điều hòa tủ đứng ' . date('Y') . ' đầy đủ theo BTU, thương hiệu GREE, Daikin, Panasonic. So sánh giá, tình trạng hàng, nhận báo giá ngay.';
        $canonical      = url('/bang-gia/dieu-hoa-tu-dung');

        // Filtered page → noindex để không gây duplicate content
        $robots = $hasFilter ? 'noindex,follow' : setting('seo.default_robots', 'index,follow');

        return view('pages.price-list', compact(
            'products', 'brands', 'btuOptions', 'faqs', 'filters',
            'hasFilter', 'seoTitle', 'seoDescription', 'canonical', 'robots'
        ));
    }
}
