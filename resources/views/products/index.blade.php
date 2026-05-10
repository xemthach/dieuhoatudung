<x-layouts.app
    :seo-title="setting('general.site_name', '') . ' - Danh Sách Sản Phẩm Chính Hãng'"
    :seo-description="'Xem toàn bộ danh sách điều hòa tủ đứng chính hãng Daikin, LG, Panasonic, Gree. So sánh giá, thông số kỹ thuật và chọn model phù hợp.'"
    :canonical="route('products.index')"
    :robots="isset($hasActiveFilters) && $hasActiveFilters ? 'noindex,follow' : setting('seo.default_robots', 'index,follow')"
>
    <x-breadcrumb :items="[
        ['label' => 'Điều hòa tủ đứng'],
    ]" />

    {{-- Inline Search --}}
    <div class="container-main pt-4 pb-2">
        <x-search-box variant="inline" />
    </div>

    @include('products._product-grid', ['currentCategory' => null])
</x-layouts.app>
