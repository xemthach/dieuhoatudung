<x-layouts.app
    :seo-title="($category->seo_title ?? $category->name) . config('seo.defaults.title_suffix')"
    :seo-description="$category->seo_description ?? 'Xem danh sách điều hòa tủ đứng ' . $category->name . '. Liên hệ để được tư vấn sản phẩm phù hợp theo nhu cầu công trình.'"
    :canonical="route('category.show', $category->slug)"
    :robots="isset($hasActiveFilters) && $hasActiveFilters ? 'noindex,follow' : setting('seo.default_robots', 'index,follow')"
>
    <x-breadcrumb :items="[
        ['label' => 'Điều hòa tủ đứng', 'url' => route('products.index')],
        ['label' => $category->name],
    ]" />

    {{-- Category Intro Section --}}
    @if($category->intro || $category->image)
    <section class="bg-gradient-to-br from-surface-50 to-white py-8 lg:py-10">
        <div class="container-main">
            <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                @if($category->image)
                <div class="w-full max-w-xs shrink-0 overflow-hidden rounded-2xl border border-surface-200 bg-white p-4 sm:w-48">
                    <img src="{{ media_url($category->image) }}" alt="{{ $category->name }}" class="w-full object-contain" loading="lazy">
                </div>
                @endif
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-surface-900 sm:text-3xl">{{ $category->name }}</h1>
                    @if($category->intro)
                    <p class="mt-3 text-surface-600 leading-relaxed">{{ $category->intro }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
    @endif

    {{-- Inline Search --}}
    <div class="container-main pt-4 pb-2">
        <x-search-box variant="inline" />
    </div>

    @include('products._product-grid', ['currentCategory' => $category])

    {{-- Category Rich Content (SEO) --}}
    @if(!empty($category->content))
    <section class="border-t border-surface-200 bg-white py-8 lg:py-12">
        <div class="container-main">
            <div class="prose prose-lg prose-surface mx-auto max-w-4xl prose-headings:text-surface-900 prose-a:text-primary-600 prose-a:no-underline hover:prose-a:underline prose-img:rounded-xl">
                {!! $category->content !!}
            </div>
        </div>
    </section>
    @endif

    {{-- FAQ Section --}}
    <x-faq-section :faqs="$category->activeFaqs" />

    {{-- Internal Links --}}
    <x-internal-links
        :source-type="\App\Models\ProductCategory::class"
        :source-id="$category->id"
    />

    {{-- Category Schema --}}
    @push('schema')
    @php
        $schemaService = app(\App\Services\Schema\SchemaService::class);
    @endphp
    {!! \App\Services\Schema\SchemaService::toScript($schemaService->collectionPage($category, $products)) !!}
    @endpush

</x-layouts.app>
