<x-layouts.app
    :seo-title="setting('general.site_name', '') . ' - Danh Sách Sản Phẩm'"
    :seo-description="'Xem danh sách điều hòa, so sánh giá và thông số kỹ thuật để chọn sản phẩm phù hợp.'"
    :canonical="route('products.index')"
    :robots="isset($hasActiveFilters) && $hasActiveFilters ? 'noindex,follow' : setting('seo.default_robots', 'index,follow')"
>
    <x-breadcrumb :items="[
        ['label' => app(\App\Services\Navigation\SiteProductScope::class)->productLabel()],
    ]" />

    {{-- Inline Search --}}
    <div class="container-main pt-4 pb-2">
        <x-search-box variant="inline" />
    </div>

    @include('products._product-grid', ['currentCategory' => null])

    {{-- ItemList Schema --}}
    @push('schema')
    @php
        $schemaService = app(\App\Services\Schema\SchemaService::class);
    @endphp
    {!! \App\Services\Schema\SchemaService::toScript($schemaService->itemListPage(
        'Danh sách sản phẩm điều hòa',
        route('products.index'),
        $products
    )) !!}
    @endpush
</x-layouts.app>
