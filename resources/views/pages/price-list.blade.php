<x-layouts.app
    :seoTitle="$seoTitle"
    :seoDescription="$seoDescription"
    :canonical="$canonical"
    :robots="$robots"
    ogType="website"
>

@push('schema')
@if(! $hasFilter)
{{-- FAQ Schema chỉ xuất khi không filter (trang canonical) --}}
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "FAQPage",
    "mainEntity": [
        @foreach($faqs->take(4) as $i => $faq)
        {
            "@@type": "Question",
            "name": "{{ addslashes(is_object($faq) && isset($faq->question) ? $faq->question : '') }}",
            "acceptedAnswer": {
                "@@type": "Answer",
                "text": "{{ addslashes(is_object($faq) && isset($faq->answer) ? $faq->answer : '') }}"
            }
        }{{ ! $loop->last ? ',' : '' }}
        @endforeach
    ]
}
</script>
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "BreadcrumbList",
    "itemListElement": [
        {"@@type": "ListItem","position": 1,"name": "Trang chủ","item": "{{ url('/') }}"},
        {"@@type": "ListItem","position": 2,"name": "Bảng giá điều hòa tủ đứng"}
    ]
}
</script>
@endif
@endpush

<div class="container-main py-8 lg:py-12">

    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-surface-500" aria-label="Breadcrumb">
        <a href="/" class="hover:text-primary-600">Trang chủ</a>
        <span>/</span>
        <span class="font-medium text-surface-700">Bảng giá điều hòa tủ đứng</span>
    </nav>

    {{-- H1 + Intro --}}
    <div class="mb-8">
        <h1 class="text-2xl font-extrabold text-surface-900 sm:text-3xl lg:text-4xl">
             Bảng Giá Điều Hòa Tủ Đứng {{ date('Y') }}
        </h1>
        <p class="mt-3 max-w-3xl text-base text-surface-600 leading-relaxed">
            Cập nhật bảng giá điều hòa tủ đứng chính hãng mới nhất từ các thương hiệu GREE, Daikin, Panasonic, Mitsubishi, LG.
            So sánh công suất BTU, giá niêm yết, giá khuyến mãi và tình trạng hàng tồn kho.
        </p>
        <div class="mt-3 inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-700">
             Giá tham khảo — có thể thay đổi theo thời gian. Liên hệ để nhận báo giá chính xác.
        </div>
    </div>

    {{-- ── FILTER BAR ─────────────────────────────────── --}}
    <form method="GET" action="{{ route('price-list') }}"
        class="mb-6 rounded-2xl border border-surface-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">

            {{-- Brand --}}
            <div>
                <label class="mb-1 block text-xs font-semibold text-surface-600">Thương hiệu</label>
                <select name="brand" class="w-full rounded-lg border border-surface-300 py-2 px-3 text-sm">
                    <option value="">-- Tất cả --</option>
                    @foreach($brands as $brand)
                    <option value="{{ $brand->slug }}" {{ ($filters['brand'] ?? '') === $brand->slug ? 'selected' : '' }}>
                        {{ $brand->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- BTU --}}
            <div>
                <label class="mb-1 block text-xs font-semibold text-surface-600">Công suất BTU</label>
                <select name="btu" class="w-full rounded-lg border border-surface-300 py-2 px-3 text-sm">
                    <option value="">-- Tất cả --</option>
                    @foreach([24000, 28000, 36000, 48000, 50000, 60000, 100000] as $btu)
                    <option value="{{ $btu }}" {{ ($filters['btu'] ?? '') == $btu ? 'selected' : '' }}>
                        {{ number_format($btu) }} BTU
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Inverter --}}
            <div>
                <label class="mb-1 block text-xs font-semibold text-surface-600">Inverter</label>
                <select name="inverter" class="w-full rounded-lg border border-surface-300 py-2 px-3 text-sm">
                    <option value="">-- Tất cả --</option>
                    <option value="1" {{ ($filters['inverter'] ?? '') === '1' ? 'selected' : '' }}>Có Inverter</option>
                    <option value="0" {{ ($filters['inverter'] ?? '') === '0' ? 'selected' : '' }}>Không Inverter</option>
                </select>
            </div>

            {{-- Giá tối thiểu --}}
            <div>
                <label class="mb-1 block text-xs font-semibold text-surface-600">Giá từ (triệu)</label>
                <input type="number" name="price_min" min="0" step="0.5"
                    value="{{ $filters['price_min'] ?? '' }}"
                    placeholder="vd: 15"
                    class="w-full rounded-lg border border-surface-300 py-2 px-3 text-sm">
            </div>

            {{-- Giá tối đa --}}
            <div>
                <label class="mb-1 block text-xs font-semibold text-surface-600">Giá đến (triệu)</label>
                <input type="number" name="price_max" min="0" step="0.5"
                    value="{{ $filters['price_max'] ?? '' }}"
                    placeholder="vd: 50"
                    class="w-full rounded-lg border border-surface-300 py-2 px-3 text-sm">
            </div>

            {{-- Stock status --}}
            <div>
                <label class="mb-1 block text-xs font-semibold text-surface-600">Tình trạng</label>
                <select name="stock_status" class="w-full rounded-lg border border-surface-300 py-2 px-3 text-sm">
                    <option value="">-- Tất cả --</option>
                    <option value="in_stock" {{ ($filters['stock_status'] ?? '') === 'in_stock' ? 'selected' : '' }}>Còn hàng</option>
                    <option value="pre_order" {{ ($filters['stock_status'] ?? '') === 'pre_order' ? 'selected' : '' }}>Đặt trước</option>
                    <option value="contact" {{ ($filters['stock_status'] ?? '') === 'contact' ? 'selected' : '' }}>Liên hệ</option>
                </select>
            </div>
        </div>

        <div class="mt-3 flex items-center gap-3">
            <button type="submit"
                class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-bold text-white hover:bg-primary-700">
                 Lọc kết quả
            </button>
            @if($hasFilter)
            <a href="{{ route('price-list') }}"
                class="rounded-lg border border-surface-200 px-4 py-2 text-sm text-surface-600 hover:bg-surface-50">
                 Xóa bộ lọc
            </a>
            @endif

            @if($hasFilter)
            <span class="text-xs text-amber-600">
                 Trang đang lọc — không tính vào SEO index
            </span>
            @endif
        </div>
    </form>

    {{-- ── BẢNG GIÁ ───────────────────────────────────── --}}
    <div class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">

        {{-- Results count --}}
        <div class="flex items-center justify-between border-b border-surface-100 px-5 py-3">
            <span class="text-sm font-semibold text-surface-700">
                {{ number_format($products->total()) }} sản phẩm
            </span>
            <span class="text-xs text-surface-400">
                Trang {{ $products->currentPage() }} / {{ $products->lastPage() }}
            </span>
        </div>

        @if($products->isEmpty())
        <div class="py-16 text-center text-surface-500">
            <svg class="mx-auto mb-3 h-12 w-12 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm">Không có sản phẩm phù hợp với bộ lọc.</p>
            <a href="{{ route('price-list') }}" class="mt-2 inline-block text-sm text-primary-600 underline">Xem tất cả</a>
        </div>
        @else

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead class="bg-surface-50 text-xs font-semibold uppercase tracking-wide text-surface-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Sản phẩm</th>
                        <th class="px-4 py-3 text-left">Thương hiệu</th>
                        <th class="px-4 py-3 text-center">BTU</th>
                        <th class="px-4 py-3 text-center">Inverter</th>
                        <th class="px-4 py-3 text-center">Điện áp</th>
                        <th class="px-4 py-3 text-center">Gas</th>
                        <th class="px-4 py-3 text-right">Giá niêm yết</th>
                        <th class="px-4 py-3 text-right">Giá KM</th>
                        <th class="px-4 py-3 text-center">Giảm</th>
                        <th class="px-4 py-3 text-center">Tình trạng</th>
                        <th class="px-4 py-3 text-center">Báo giá</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100">
                    @foreach($products as $product)
                    @php
                        $effectivePrice = $product->sale_price ?? $product->regular_price;
                        $discountPct = $product->discount_percent;
                        if (!$discountPct && $product->sale_price && $product->regular_price && $product->regular_price > 0) {
                            $discountPct = round((1 - $product->sale_price / $product->regular_price) * 100);
                        }
                        $stockStatus = $product->stock_status;
                        $stockLabel = $stockStatus?->label() ?? 'Liên hệ';
                        $stockColor = match($stockStatus?->value ?? 'contact') {
                            'in_stock' => 'bg-green-100 text-green-700',
                            'pre_order' => 'bg-amber-100 text-amber-700',
                            'out_of_stock' => 'bg-red-100 text-red-600',
                            default => 'bg-blue-100 text-blue-700',
                        };
                    @endphp
                    <tr class="transition-colors hover:bg-surface-50">
                        {{-- Sản phẩm --}}
                        <td class="px-4 py-3">
                            <a href="{{ route('product.show', $product->slug) }}"
                                class="flex items-center gap-3 font-medium text-surface-800 hover:text-primary-700">
                                <img src="{{ $product->card_image_url }}"
                                    alt="{{ $product->name }}"
                                    class="h-10 w-10 flex-shrink-0 rounded-lg object-contain border border-surface-200"
                                    loading="lazy">
                                <div class="min-w-0">
                                    <span class="line-clamp-2 text-sm leading-snug">{{ $product->name }}</span>
                                    @if($product->model_code)
                                    <span class="text-xs text-surface-400">{{ $product->model_code }}</span>
                                    @endif
                                    <div class="mt-1">
                                        <x-product-badges :product="$product" :limit="2" />
                                    </div>
                                </div>
                            </a>
                        </td>
                        {{-- Brand --}}
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full bg-surface-100 px-2 py-0.5 text-xs font-medium text-surface-600">
                                {{ $product->brand?->name ?? '—' }}
                            </span>
                        </td>
                        {{-- BTU --}}
                        <td class="px-4 py-3 text-center font-semibold text-primary-700">
                            {{ $product->btu ? number_format($product->btu) : '—' }}
                        </td>
                        {{-- Inverter --}}
                        <td class="px-4 py-3 text-center">
                            @if($product->inverter)
                            <span class="text-green-600 font-bold text-xs"> Có</span>
                            @else
                            <span class="text-surface-400 text-xs">—</span>
                            @endif
                        </td>
                        {{-- Voltage --}}
                        <td class="px-4 py-3 text-center text-xs text-surface-600">
                            {{ $product->voltage ?? '—' }}
                        </td>
                        {{-- Gas --}}
                        <td class="px-4 py-3 text-center text-xs text-surface-600">
                            {{ $product->refrigerant_gas ?? '—' }}
                        </td>
                        {{-- Giá gốc --}}
                        <td class="px-4 py-3 text-right">
                            @if($product->regular_price)
                            <span class="{{ $product->sale_price ? 'text-xs text-surface-400 line-through' : 'font-semibold text-surface-800' }}">
                                {{ number_format($product->regular_price, 0, ',', '.') }}đ
                            </span>
                            @else
                            <span class="text-surface-400">—</span>
                            @endif
                        </td>
                        {{-- Giá KM --}}
                        <td class="px-4 py-3 text-right">
                            @if($product->sale_price)
                            <span class="font-bold text-red-600">
                                {{ number_format($product->sale_price, 0, ',', '.') }}đ
                            </span>
                            @else
                            <span class="text-surface-400 text-xs">—</span>
                            @endif
                        </td>
                        {{-- % Giảm --}}
                        <td class="px-4 py-3 text-center">
                            @if($discountPct > 0)
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-600">
                                -{{ $discountPct }}%
                            </span>
                            @else
                            <span class="text-surface-300 text-xs">—</span>
                            @endif
                        </td>
                        {{-- Tình trạng --}}
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full {{ $stockColor }} px-2 py-0.5 text-xs font-medium whitespace-nowrap">
                                {{ $stockLabel }}
                            </span>
                        </td>
                        {{-- CTA --}}
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('quote.index') }}?product={{ $product->slug }}"
                                class="inline-block rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-700 whitespace-nowrap">
                                Báo giá
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="divide-y divide-surface-100 lg:hidden">
            @foreach($products as $product)
            @php
                $effectivePrice = $product->sale_price ?? $product->regular_price;
                $discountPct = $product->discount_percent;
                if (!$discountPct && $product->sale_price && $product->regular_price && $product->regular_price > 0) {
                    $discountPct = round((1 - $product->sale_price / $product->regular_price) * 100);
                }
                $stockStatus = $product->stock_status;
                $stockLabel = $stockStatus?->label() ?? 'Liên hệ';
            @endphp
            <div class="flex items-center gap-3 p-4">
                <img src="{{ $product->card_image_url }}" alt="{{ $product->name }}"
                    class="h-14 w-14 flex-shrink-0 rounded-lg object-contain border border-surface-200" loading="lazy">

                <div class="min-w-0 flex-1">
                    <a href="{{ route('product.show', $product->slug) }}"
                        class="text-sm font-semibold text-surface-800 line-clamp-1 hover:text-primary-700">
                        {{ $product->name }}
                    </a>
                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-surface-500">
                        @if($product->brand)
                        <span class="font-medium">{{ $product->brand->name }}</span>
                        @endif
                        @if($product->btu)
                        <span>• {{ number_format($product->btu) }} BTU</span>
                        @endif
                        @if($product->inverter)
                        <span class="text-green-600">• Inverter</span>
                        @endif
                    </div>
                    <div class="mt-1.5">
                        <x-product-badges :product="$product" :limit="3" />
                    </div>
                    <div class="mt-1.5 flex items-center gap-2">
                        @if($product->sale_price)
                        <span class="font-bold text-red-600 text-sm">{{ number_format($product->sale_price, 0, ',', '.') }}đ</span>
                        @if($product->regular_price)
                        <span class="text-xs text-surface-400 line-through">{{ number_format($product->regular_price, 0, ',', '.') }}đ</span>
                        @endif
                        @if($discountPct > 0)
                        <span class="rounded bg-red-100 px-1 text-[10px] font-bold text-red-600">-{{ $discountPct }}%</span>
                        @endif
                        @elseif($product->regular_price)
                        <span class="font-bold text-surface-800 text-sm">{{ number_format($product->regular_price, 0, ',', '.') }}đ</span>
                        @else
                        <span class="text-xs text-surface-400">Liên hệ báo giá</span>
                        @endif
                    </div>
                </div>

                <a href="{{ route('quote.index') }}?product={{ $product->slug }}"
                    class="flex-shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-xs font-bold text-white">
                    Báo giá
                </a>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Pagination --}}
        @if($products->hasPages())
        <div class="border-t border-surface-100 px-5 py-4">
            {{ $products->links() }}
        </div>
        @endif
    </div>

    {{-- ── CTA BLOCK ───────────────────────────────────── --}}
    <div class="mt-8 overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 to-primary-800 p-6 text-center text-white sm:p-8">
        <h2 class="text-xl font-bold sm:text-2xl"> Nhận Báo Giá Chính Xác</h2>
        <p class="mt-2 text-sm text-primary-100">
            Bảng giá trên chỉ mang tính tham khảo. Liên hệ để nhận báo giá chính xác theo nhu cầu thực tế của bạn.
        </p>
        <div class="mt-4 flex flex-wrap justify-center gap-3">
            <a href="{{ setting('cta.quote_cta_link', '/bao-gia') }}"
                class="rounded-xl bg-white px-6 py-3 text-sm font-bold text-primary-700 shadow-md transition hover:bg-primary-50">
                {{ setting('cta.quote_cta_text', 'Nhận báo giá') }}
            </a>
            @if(setting('contact.hotline'))
            <a href="tel:{{ setting('contact.hotline') }}"
                class="rounded-xl border-2 border-white/70 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/10">
                 {{ setting('contact.hotline') }}
            </a>
            @endif
        </div>
    </div>

    {{-- ── FAQ ────────────────────────────────────────── --}}
    <div class="mt-10">
        <h2 class="mb-5 text-xl font-bold text-surface-900">Câu Hỏi Thường Gặp Về Bảng Giá</h2>
        <div class="space-y-3">
            @foreach($faqs as $faq)
            <details class="group rounded-xl border border-surface-200 bg-white">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-4 font-medium text-surface-800 hover:text-primary-700 text-sm">
                    {{ is_object($faq) ? $faq->question : '' }}
                    <svg class="h-4 w-4 flex-shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="border-t border-surface-100 px-4 pb-4 pt-3 text-sm text-surface-600 leading-relaxed">
                    {{ is_object($faq) ? $faq->answer : '' }}
                </div>
            </details>
            @endforeach
        </div>
    </div>

    {{-- ── Internal links ──────────────────────────────── --}}
    <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach([
            [route('landing'), '', 'Điều hòa tủ đứng', 'Danh sách sản phẩm'],
            [route('btu-calculator.index'), '', 'Tính BTU miễn phí', 'Chọn đúng công suất'],
            [route('quote.index'), '', 'Nhận báo giá', 'Báo giá theo nhu cầu'],
            [route('faq.dieu-hoa'), '', 'FAQ điều hòa', 'Giải đáp thắc mắc'],
        ] as [$url, $icon, $title, $sub])
        <a href="{{ $url }}"
            class="flex items-center gap-3 rounded-xl border border-surface-200 bg-white p-3 transition hover:border-primary-300 hover:shadow-sm">
            <span class="text-xl">{{ $icon }}</span>
            <div>
                <div class="text-sm font-semibold text-surface-800">{{ $title }}</div>
                <div class="text-xs text-surface-500">{{ $sub }}</div>
            </div>
        </a>
        @endforeach
    </div>

</div>
</x-layouts.app>
