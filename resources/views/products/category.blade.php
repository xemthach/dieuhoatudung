<x-layouts.app
    :seo-title="($category->seo_title ?? $category->name) . config('seo.defaults.title_suffix')"
    :seo-description="$category->seo_description ?? 'Xem danh sách điều hòa tủ đứng ' . $category->name . ' chính hãng, giá tốt nhất.'"
    :canonical="route('category.show', $category->slug)"
    :robots="isset($hasActiveFilters) && $hasActiveFilters ? 'noindex,follow' : setting('seo.default_robots', 'index,follow')"
>
    <x-breadcrumb :items="[
        ['label' => 'Điều hòa tủ đứng', 'url' => route('products.index')],
        ['label' => $category->name],
    ]" />

    @include('products._product-grid', ['currentCategory' => $category])

    <x-faq-section :faqs="$category->activeFaqs" />
</x-layouts.app>
