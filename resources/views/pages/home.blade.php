<x-layouts.app
    :seo-title="setting('seo.default_seo_title', setting('general.site_name', '') . ' - Chính Hãng, Giá Tốt')"
    :seo-description="setting('seo.default_meta_description', 'Chuyên cung cấp điều hòa tủ đứng chính hãng. Giá tốt nhất, lắp đặt miễn phí, bảo hành toàn quốc.')"
    :canonical="config('app.url')"
>
    {{-- Hero Section --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary-900 via-primary-800 to-surface-900">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute -right-20 -top-20 h-96 w-96 rounded-full bg-primary-400 blur-3xl"></div>
            <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-accent-400 blur-3xl"></div>
        </div>
        <div class="container-main relative py-16 lg:py-24">
            <div class="mx-auto max-w-3xl text-center">
                <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
                    {{ setting('general.site_name', '') }} <span class="text-accent-400">Chính Hãng</span>
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-primary-100">
                    Giải pháp làm mát chuyên nghiệp cho không gian lớn. Đa dạng thương hiệu, công suất phù hợp mọi nhu cầu.
                    Miễn phí lắp đặt, bảo hành chính hãng toàn quốc.
                </p>
                <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <a href="{{ route('quote.index') }}" class="btn-accent w-full px-8 py-4 text-base sm:w-auto">
                        <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Nhận báo giá miễn phí
                    </a>
                    <a href="{{ route('products.index') }}" class="btn-outline w-full border-white/30 px-8 py-4 text-base text-white hover:bg-white/10 sm:w-auto">
                        Xem sản phẩm
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust Badges --}}
    <section class="border-b border-surface-200 bg-white py-6">
        <div class="container-main">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="flex items-center gap-3 rounded-lg p-3">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-surface-900">Chính hãng 100%</p>
                        <p class="text-xs text-surface-500">Nhập khẩu trực tiếp</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-lg p-3">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-surface-900">Lắp đặt miễn phí</p>
                        <p class="text-xs text-surface-500">Kỹ thuật chuyên nghiệp</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-lg p-3">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-success-500/10 text-success-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-surface-900">Bảo hành 3-5 năm</p>
                        <p class="text-xs text-surface-500">Theo chính sách hãng</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-lg p-3">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-warning-500/10 text-warning-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-surface-900">Giá tốt nhất</p>
                        <p class="text-xs text-surface-500">Cam kết cạnh tranh</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
                title="Thương Hiệu Chính Hãng"
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
                <a href="{{ route('quote.index') }}" class="btn-accent px-8 py-4 text-base">
                    Nhận báo giá ngay
                </a>
                <a href="tel:" class="inline-flex items-center gap-2 text-white transition-colors hover:text-accent-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span class="text-lg font-semibold">Gọi hotline tư vấn</span>
                </a>
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
