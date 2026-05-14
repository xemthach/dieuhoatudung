<x-layouts.app
    :seo-title="setting('seo.default_seo_title', setting('general.site_name', '') . ' - Điều Hòa Tủ Đứng')"
    :seo-description="setting('seo.default_meta_description', 'Danh sách điều hòa tủ đứng, thông số kỹ thuật và tư vấn lựa chọn theo nhu cầu công trình.')"
    :canonical="config('app.url')"
>
    {{-- Homepage Search Section (above hero, below header) --}}
    <x-home.homepage-search />

    {{-- Hero Slider --}}
    <x-home.hero-slider />

    {{-- Benefit Bar --}}
    <x-home.benefit-bar />

    {{-- Featured Products --}}
    @if($featuredProducts->isNotEmpty())
    <section class="py-12 lg:py-16">
        <div class="container-main">
            <x-section-heading
                title="Sản Phẩm Nổi Bật"
                subtitle="Các mẫu điều hòa tủ đứng được khách hàng lựa chọn nhiều nhất"
            />
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 lg:gap-6">
                @foreach($featuredProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
            <div class="mt-8 text-center">
                <a href="{{ route('products.index') }}" class="btn-outline">
                    Xem tất cả sản phẩm
                    <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>
    @endif

    {{-- Brands --}}
    @if($brands->isNotEmpty())
    <section class="border-y border-surface-200 bg-surface-50 py-12 lg:py-16">
        <div class="container-main">
            <x-section-heading
                title="Thương Hiệu Sản Phẩm"
                subtitle="Đại lý ủy quyền các thương hiệu điều hòa hàng đầu thế giới"
            />
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-{{ min($brands->count(), 6) }}">
                @foreach($brands as $brand)
                    <x-brand-card :brand="$brand" />
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- Latest Blog Posts --}}
    @if($latestPosts->isNotEmpty())
    <section class="py-12 lg:py-16">
        <div class="container-main">
            <x-section-heading
                title="Kiến Thức Điều Hòa"
                subtitle="Bài viết hữu ích giúp bạn lựa chọn và sử dụng điều hòa tủ đứng hiệu quả"
            />
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($latestPosts as $post)
                    <x-post-card :post="$post" />
                @endforeach
            </div>
            <div class="mt-8 text-center">
                <a href="{{ route('blog.index') }}" class="btn-outline">
                    Xem tất cả bài viết
                    <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>
    @endif

    {{-- FAQ --}}
    @if($faqs->isNotEmpty())
    <section class="border-t border-surface-200 bg-surface-50 py-12 lg:py-16">
        <div class="container-main">
            <x-section-heading
                title="Câu Hỏi Thường Gặp"
                subtitle="Những thắc mắc phổ biến về điều hòa tủ đứng"
            />
            <div class="mx-auto mt-8 max-w-3xl space-y-3">
                @foreach($faqs as $index => $faq)
                    <x-faq-item :faq="$faq" :open="$index === 0" />
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- CTA Section --}}
    <section class="bg-gradient-to-r from-primary-700 to-primary-900 py-12 lg:py-16">
        <div class="container-main text-center">
            <h2 class="text-2xl font-bold text-white sm:text-3xl">Cần tư vấn điều hòa tủ đứng?</h2>
            <p class="mx-auto mt-3 max-w-2xl text-primary-100">
                Đội ngũ chuyên gia HVAC sẵn sàng hỗ trợ bạn chọn model phù hợp với không gian và ngân sách.
            </p>
            <div class="mt-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="{{ setting('cta.quote_cta_link', route('quote.index')) }}" class="btn-accent px-8 py-4 text-base">
                    {{ setting('cta.global_cta_text', 'Nhận báo giá ngay') }}
                </a>
                @if(setting('contact.hotline'))
                <a href="tel:{{ setting('contact.hotline') }}" class="inline-flex items-center gap-2 text-white transition-colors hover:text-accent-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span class="text-lg font-semibold">{{ setting('cta.phone_cta_text', 'Gọi hotline tư vấn') }}</span>
                </a>
                @endif
            </div>
        </div>
    </section>

    {{-- Organization + WebSite Schema (homepage only) --}}
    @push('schema')
    @php
        $schemaService = app(\App\Services\Schema\SchemaService::class);
        $orgSchemas = $schemaService->organizationAndWebsite();
    @endphp
    @foreach($orgSchemas as $orgSchema)
    {!! \App\Services\Schema\SchemaService::toScript($orgSchema) !!}
    @endforeach
    @if($faqs->isNotEmpty())
    {!! \App\Services\Schema\SchemaService::toScript($schemaService->faqPage($faqs)) !!}
    @endif
    @endpush

</x-layouts.app>
