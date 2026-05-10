<x-layouts.app
    :seo-title="$seoTitle"
    :seo-description="$seoDescription"
    robots="noindex, follow"
>
    <section class="bg-gradient-to-b from-surface-50 to-white py-8 lg:py-12">
        <div class="container-main">

            {{-- Search box --}}
            <div class="mx-auto max-w-2xl">
                <x-search-box variant="compact" />
            </div>

            {{-- Results header --}}
            @if($q)
            <div class="mt-8">
                <h1 class="text-xl font-bold text-surface-900 sm:text-2xl">
                    @if($resultCount > 0)
                        Tìm thấy <span class="text-primary-600">{{ number_format($resultCount) }}</span> sản phẩm cho
                    @else
                        Không tìm thấy kết quả cho
                    @endif
                    <span class="text-primary-600">"{{ $q }}"</span>
                </h1>
            </div>
            @endif

            {{-- Results grid --}}
            @if($products && $products->total() > 0)
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 lg:gap-6">
                @foreach($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $products->appends(['q' => $q])->links() }}
            </div>

            @elseif($q)
            {{-- No results state --}}
            <div class="mx-auto mt-12 max-w-lg text-center">
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-surface-100">
                    <svg class="h-10 w-10 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-surface-800">Không tìm thấy sản phẩm phù hợp</h2>
                <p class="mt-2 text-surface-600">
                    Anh/Chị có thể thử:
                </p>
                <ul class="mt-3 space-y-1 text-sm text-surface-500">
                    <li>• Kiểm tra lại mã model hoặc SKU</li>
                    <li>• Tìm theo thương hiệu (Gree, Daikin, LG...)</li>
                    <li>• Tìm theo công suất (24000 BTU, 48000 BTU...)</li>
                    <li>• Tìm theo loại máy (cassette, giấu trần, tủ đứng...)</li>
                </ul>
                <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('products.index') }}" class="btn-outline px-6 py-3 text-sm">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        Xem tất cả sản phẩm
                    </a>
                    <a href="{{ route('quote.index') }}" class="btn-accent px-6 py-3 text-sm">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        Gửi yêu cầu tư vấn
                    </a>
                </div>
            </div>
            @else
            {{-- Initial state (no query) --}}
            <div class="mx-auto mt-12 max-w-lg text-center">
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-primary-50">
                    <svg class="h-10 w-10 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h1 class="text-lg font-bold text-surface-800">Tìm kiếm sản phẩm</h1>
                <p class="mt-2 text-surface-600">Nhập mã model, SKU, tên sản phẩm, thương hiệu hoặc công suất BTU để tìm nhanh.</p>
            </div>
            @endif

        </div>
    </section>
</x-layouts.app>
