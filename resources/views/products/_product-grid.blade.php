@php
    $btuLabels = \App\Services\Product\ProductFilterService::CAPACITY_FILTER_OPTIONS;
    $activeChips = [];
    $chipUrl = function (string $key, ?string $value = null): string {
        $query = request()->query(); unset($query['page']);
        if ($value !== null && isset($query[$key]) && is_array($query[$key])) {
            $query[$key] = array_values(array_filter($query[$key], fn ($item) => (string) $item !== $value));
            if ($query[$key] === []) unset($query[$key]);
        } else unset($query[$key]);
        return url()->current().($query === [] ? '' : '?'.http_build_query($query));
    };
    foreach ((array) request('brand', []) as $slug) { $name = optional(($brands ?? collect())->firstWhere('slug', $slug))->name ?? $slug; $activeChips[] = ['label' => $name, 'url' => $chipUrl('brand', (string) $slug)]; }
    foreach ((array) request('btu', []) as $value) if (isset($btuLabels[$value])) $activeChips[] = ['label' => $btuLabels[$value], 'url' => $chipUrl('btu', (string) $value)];
    if (request()->has('inverter') && request('inverter') !== '') $activeChips[] = ['label' => request('inverter') === '1' ? 'Có Inverter' : 'Không Inverter', 'url' => $chipUrl('inverter')];
    if (in_array(request('cooling_type'), ['1_chieu', '2_chieu'], true)) $activeChips[] = ['label' => request('cooling_type') === '1_chieu' ? 'Điều hòa 1 chiều' : 'Điều hòa 2 chiều', 'url' => $chipUrl('cooling_type')];
    if (request('price_min') !== null && request('price_min') !== '') $activeChips[] = ['label' => 'Từ '.number_format((float) request('price_min'), 0, ',', '.').' ₫', 'url' => $chipUrl('price_min')];
    if (request('price_max') !== null && request('price_max') !== '') $activeChips[] = ['label' => 'Đến '.number_format((float) request('price_max'), 0, ',', '.').' ₫', 'url' => $chipUrl('price_max')];
    if (request('is_sale') === '1') $activeChips[] = ['label' => 'Đang khuyến mãi', 'url' => $chipUrl('is_sale')];
@endphp
<section class="py-8 lg:py-12" x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen=false">
<div class="container-main">
    @if($activeChips !== [])<div class="mb-5 flex flex-wrap items-center gap-2" aria-label="Bộ lọc đang áp dụng">@foreach($activeChips as $chip)<a href="{{ $chip['url'] }}" class="rounded-full bg-primary-50 px-3 py-1.5 text-sm text-primary-700">{{ $chip['label'] }} ×</a>@endforeach<a href="{{ url()->current() }}" class="text-sm underline">Xóa tất cả</a></div>@endif
    <div class="lg:flex lg:gap-8">
        <aside class="hidden lg:block lg:w-64 lg:flex-shrink-0"><x-product-filter-sidebar :brands="$brands ?? collect()" :categories="$categories ?? collect()" :currentCategory="$currentCategory ?? null" /></aside>
        <div class="flex-1">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4"><h1 class="text-xl font-bold sm:text-2xl">{{ isset($currentCategory) ? $currentCategory->name : setting('general.site_name', 'Sản phẩm') }}</h1><div class="flex items-center gap-3"><span class="text-sm text-surface-500">{{ $products->total() }} sản phẩm</span><label class="sr-only" for="product-sort">Sắp xếp sản phẩm</label><select id="product-sort" onchange="const url=new URL(window.location.href);url.searchParams.set('sort',this.value);url.searchParams.delete('page');window.location.href=url.href" class="rounded-lg border-surface-300 bg-white py-2 pl-4 pr-8 text-sm"><option value="latest" {{ request('sort', 'latest') === 'latest' ? 'selected' : '' }}>Mới nhất</option><option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Giá: thấp đến cao</option><option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Giá: cao đến thấp</option><option value="btu_asc" {{ request('sort') === 'btu_asc' ? 'selected' : '' }}>BTU marketing: thấp đến cao</option><option value="btu_desc" {{ request('sort') === 'btu_desc' ? 'selected' : '' }}>BTU marketing: cao đến thấp</option></select><button type="button" class="rounded-lg border px-3 py-2 text-sm lg:hidden" @click="filtersOpen=true" :aria-expanded="filtersOpen.toString()" aria-controls="mobileFilterMenu">Lọc</button></div></div>
            <div x-cloak x-show="filtersOpen" class="fixed inset-0 z-50 overflow-y-auto bg-white p-4 lg:hidden" id="mobileFilterMenu" role="dialog" aria-modal="true" aria-label="Bộ lọc sản phẩm"><div class="mb-3 flex justify-end"><button type="button" @click="filtersOpen=false" class="rounded-lg border px-3 py-2">Đóng</button></div><x-product-filter-sidebar :brands="$brands ?? collect()" :categories="$categories ?? collect()" :currentCategory="$currentCategory ?? null" /></div>
            @if($products->isNotEmpty())<div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">@foreach($products as $product)<x-product-card :product="$product" />@endforeach</div><div class="mt-8">{{ $products->links() }}</div>@else<div class="rounded-xl border border-surface-200 bg-white py-16 text-center"><p class="text-surface-600">Không tìm thấy sản phẩm phù hợp.</p><a href="{{ url()->current() }}" class="mt-4 inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Xóa bộ lọc</a></div>@endif
        </div>
    </div>
</div>
</section>
