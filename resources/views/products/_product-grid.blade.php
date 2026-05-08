{{-- Shared product grid partial used by products/index.blade.php and products/category.blade.php --}}
<section class="py-8 lg:py-12">
    <div class="container-main">
        <div class="lg:flex lg:gap-8">
            {{-- Sidebar Filters --}}
            <aside class="mb-6 lg:mb-0 lg:w-64 lg:flex-shrink-0 hidden lg:block">
                <x-product-filter-sidebar :brands="$brands ?? collect()" :categories="$categories ?? collect()" :currentCategory="$currentCategory ?? null" />
            </aside>

            {{-- Product Grid --}}
            <div class="flex-1">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <h1 class="text-xl font-bold text-surface-900 sm:text-2xl">
                        {{ isset($currentCategory) ? $currentCategory->name : setting('general.site_name', 'Sản phẩm') }}
                    </h1>
                    
                    <div class="flex items-center gap-4">
                        <span class="hidden text-sm text-surface-500 sm:inline-block">{{ $products->total() }} sản phẩm</span>
                        
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
                        
                        {{-- Mobile Filter Toggle Button --}}
                        <button type="button" class="flex items-center gap-2 rounded-lg border border-surface-300 bg-white px-3 py-2 text-sm font-medium text-surface-700 lg:hidden" onclick="document.getElementById('mobileFilterMenu').classList.toggle('hidden')">
                            <x-heroicon-o-funnel class="h-4 w-4" />
                            Lọc
                        </button>
                    </div>
                </div>

                {{-- Mobile Filter Menu --}}
                <div id="mobileFilterMenu" class="hidden mb-6 lg:hidden">
                    <x-product-filter-sidebar :brands="$brands ?? collect()" :categories="$categories ?? collect()" :currentCategory="$currentCategory ?? null" />
                </div>

                @if($products->isNotEmpty())
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
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
                        <p class="mt-4 text-surface-500">Chưa có sản phẩm nào trong danh mục này.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
