@if($featuredProducts->isNotEmpty())
    <section class="py-12 lg:py-16">
        <div class="container-main">
            <x-section-heading
                title="Sản Phẩm Nổi Bật"
                subtitle="Các mẫu điều hòa được khách hàng lựa chọn nhiều nhất"
            />
            <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                @foreach($featuredProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
            <div class="mt-8 text-center">
                <a href="{{ app(\App\Services\Navigation\SiteProductScope::class)->productUrl() }}" class="btn-outline">
                    {{ app(\App\Services\Navigation\SiteProductScope::class)->productCtaLabel() }}
                    <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>
    @endif
