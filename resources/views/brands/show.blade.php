<x-layouts.app
    :seo-title="($brand->seo_title ?? 'Điều hòa ' . $brand->name . ' chính hãng') . config('seo.defaults.title_suffix', '')"
    :seo-description="$brand->seo_description ?? 'Xem danh sách điều hòa tủ đứng ' . $brand->name . ' chính hãng, giá tốt nhất. Bảo hành chính hãng, lắp đặt miễn phí.'"
    :canonical="$brand->canonical_url ?? route('brands.show', $brand->slug)"
    :robots="$brand->robots ?? setting('seo.default_robots', 'index,follow')"
>
    <x-breadcrumb :items="[
        ['label' => 'Thương hiệu', 'url' => route('brands.index')],
        ['label' => $brand->name],
    ]" />

    {{-- Brand Header --}}
    <section class="border-b border-surface-100 bg-gradient-to-br from-surface-50 to-white py-8 lg:py-10">
        <div class="container-main">
            <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                {{-- Brand Logo --}}
                <div class="flex h-24 w-32 shrink-0 items-center justify-center rounded-xl border border-surface-200 bg-white p-4">
                    @if($brand->logo_url)
                        <img
                            src="{{ $brand->logo_url }}"
                            alt="Logo {{ $brand->name }}"
                            class="h-full w-full object-contain"
                        >
                    @else
                        <span class="text-2xl font-bold text-surface-400">{{ strtoupper(substr($brand->name, 0, 2)) }}</span>
                    @endif
                </div>

                {{-- Brand Info --}}
                <div class="flex-1 text-center sm:text-left">
                    <h1 class="text-2xl font-extrabold text-surface-900 sm:text-3xl">
                        Điều hòa {{ $brand->name }} chính hãng
                    </h1>
                    @if($brand->description)
                        <p class="mt-2 max-w-3xl text-surface-600">{{ $brand->description }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Chính hãng 100%
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Bảo hành đầy đủ
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                            {{ $products->total() }} sản phẩm
                        </span>
                    </div>
                </div>

                {{-- CTA --}}
                <div class="shrink-0">
                    <a href="{{ route('quote.index') }}?brand={{ $brand->slug }}" class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-primary-600/20 transition-all hover:bg-primary-700 hover:shadow-xl">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        Nhận báo giá {{ $brand->name }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Product Listing --}}
    <section class="py-8 lg:py-12">
        <div class="container-main">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-bold text-surface-900">
                    Sản phẩm {{ $brand->name }}
                    <span class="text-base font-normal text-surface-500">({{ $products->total() }} sản phẩm)</span>
                </h2>

                <div class="relative">
                    <select onchange="const url = new URL(window.location.href); url.searchParams.set('sort', this.value); window.location.href = url.href;" class="block w-full appearance-none rounded-lg border border-surface-300 bg-white py-2 pl-4 pr-10 text-sm font-medium text-surface-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="latest" {{ request('sort', 'latest') === 'latest' ? 'selected' : '' }}>Mới nhất</option>
                        <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Giá: Thấp đến Cao</option>
                        <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Giá: Cao đến Thấp</option>
                        <option value="btu_asc" {{ request('sort') === 'btu_asc' ? 'selected' : '' }}>Công suất: Thấp đến Cao</option>
                        <option value="btu_desc" {{ request('sort') === 'btu_desc' ? 'selected' : '' }}>Công suất: Cao đến Thấp</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-surface-500">
                        <x-heroicon-s-chevron-down class="h-4 w-4" />
                    </div>
                </div>
            </div>

            @if($products->isNotEmpty())
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @else
                <div class="rounded-xl border border-surface-200 bg-white py-16 text-center">
                    <svg class="mx-auto h-12 w-12 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    <p class="mt-4 text-surface-500">Chưa có sản phẩm {{ $brand->name }} nào.</p>
                    <a href="{{ route('quote.index') }}?brand={{ $brand->slug }}" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700">
                        Liên hệ để được tư vấn
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
            @endif
        </div>
    </section>

    {{-- Brand Content (if has rich content from admin) --}}
    @if(!empty($brand->content))
    <section class="border-t border-surface-100 bg-surface-50 py-8 lg:py-12">
        <div class="container-main">
            <div class="prose prose-surface mx-auto max-w-4xl">
                {!! $brand->content !!}
            </div>
        </div>
    </section>
    @endif
</x-layouts.app>
