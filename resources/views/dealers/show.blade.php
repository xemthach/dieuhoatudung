<x-layouts.app :seo-title="$seo->getTitle()" :seo-description="$seo->getDescription()" :canonical="$seo->getCanonical()" :robots="$seo->getRobots()" :og-image="$seo->getOgImage()">
    <main class="bg-surface-50 py-8 sm:py-10">
        <div class="container-main min-w-0 space-y-8 break-words">
            <nav aria-label="Breadcrumb" class="text-sm text-surface-600">
                <a class="hover:text-primary-700 hover:underline" href="{{ route('dealers.index') }}">Đại lý</a>
                <span class="mx-2" aria-hidden="true">/</span><span class="text-surface-900">{{ $dealer->name }}</span>
            </nav>

            <header class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">
                @if($dealer->cover_image)<img class="h-40 w-full object-cover sm:h-56" src="{{ media_url($dealer->cover_image) }}" alt="Ảnh đại lý {{ $dealer->name }}">@endif
                <div class="flex flex-col gap-6 p-5 sm:flex-row sm:items-start sm:p-8">
                    @if($dealerDisplay['logo'] && $dealer->logo_url)
                        <img src="{{ $dealer->logo_url }}" class="h-24 w-24 shrink-0 rounded-2xl border border-surface-100 bg-white object-contain p-2 sm:h-28 sm:w-28" alt="Logo {{ $dealer->name }}">
                    @elseif($dealerDisplay['logo'])
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-700" aria-hidden="true"><x-heroicon-o-building-storefront class="h-9 w-9" /></div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="break-words text-2xl font-bold tracking-tight text-surface-950 sm:text-3xl">{{ $dealer->name }}</h1>
                            @if($dealerDisplay['verified'] && $dealer->is_verified)<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800"><x-heroicon-s-check-badge class="h-5 w-5" /> Đã xác minh</span>@endif
                        </div>
                        @if($dealerDisplay['rating'] && $dealer->review_count > 0)
                            <p class="mt-3 inline-flex items-center gap-2 text-sm text-surface-700"><x-heroicon-s-star class="h-5 w-5 text-amber-500" /><strong>{{ number_format($dealer->average_rating, 1, ',', '.') }}/5</strong><a class="text-primary-700 hover:underline" href="#reviews">{{ $dealer->review_count }} đánh giá</a></p>
                        @endif
                        @if(filled($dealer->short_description))<p class="mt-3 max-w-3xl leading-7 text-surface-600">{{ $dealer->short_description }}</p>@endif
                        @if($dealerDisplay['address'] && filled($dealer->full_address))<p class="mt-4 flex items-start gap-2 text-sm leading-6 text-surface-700"><x-heroicon-o-map-pin class="mt-1 h-5 w-5 shrink-0 text-primary-700" /><span>{{ $dealer->full_address }}</span></p>@endif
                        @if($dealerDisplay['address'] && $dealer->legacyProvince)<p class="mt-2 text-sm text-surface-500">Địa chỉ cũ: {{ implode(', ', array_filter([$dealer->legacy_address_line ?: $dealer->address_line, $dealer->legacyWard?->full_name, $dealer->legacyDistrict?->full_name, $dealer->legacyProvince?->full_name])) }}</p>@endif
                        <div class="mt-5 flex flex-wrap gap-3">
                            @if($dealerDisplay['contact'] && $dealerDisplay['phone'] && ($dealer->phone || $dealer->hotline))<a class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2.5 font-semibold text-white hover:bg-primary-800" href="tel:{{ preg_replace('/[^+0-9]/', '', $dealer->hotline ?: $dealer->phone) }}"><x-heroicon-o-phone class="h-5 w-5" /> Gọi ngay</a>@endif
                            @if($dealer->map_links)<a class="inline-flex items-center gap-2 rounded-lg border border-surface-300 bg-white px-4 py-2.5 font-semibold text-surface-800 hover:bg-surface-50" href="{{ $dealer->map_links['directions'] }}" target="_blank" rel="noopener noreferrer"><x-heroicon-o-map class="h-5 w-5" /> Chỉ đường</a>@endif
                            @if($dealerDisplay['contact'] && $dealer->email)<a class="inline-flex max-w-full items-center gap-2 break-all rounded-lg border border-surface-300 px-4 py-2.5 text-sm font-semibold text-primary-700" href="mailto:{{ $dealer->email }}">{{ $dealer->email }}</a>@endif
                            @if($dealer->website)<a class="inline-flex items-center gap-2 rounded-lg border border-surface-300 bg-white px-4 py-2.5 font-semibold text-surface-800 hover:bg-surface-50" href="{{ $dealer->website }}" target="_blank" rel="noopener noreferrer"><x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /> Website</a>@endif
                        </div>
                    </div>
                </div>
            </header>

            @if(filled($dealer->description))
                <section class="rounded-2xl border border-surface-200 bg-white p-5 sm:p-7">
                    <h2 class="text-xl font-bold text-surface-900">Giới thiệu</h2>
                    <div class="mt-3 whitespace-pre-line leading-7 text-surface-700">{{ $dealer->description }}</div>
                </section>
            @endif

            @if($dealer->brands->isNotEmpty() || $dealer->categories->isNotEmpty() || $dealer->services->isNotEmpty())
                <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @if($dealerDisplay['brands'] && $dealer->brands->isNotEmpty())
                        <div class="rounded-2xl border border-surface-200 bg-white p-5 sm:p-6">
                            <h2 class="text-lg font-bold text-surface-900">Thương hiệu kinh doanh</h2>
                            <div class="mt-4 flex flex-wrap items-center gap-2" data-brand-style="{{ $dealerDisplay['brand_style'] }}">
                                @foreach($dealer->brands->take($dealerDisplay['max_brands']) as $brand)@include('dealers._brand-item', ['brand' => $brand, 'style' => $dealerDisplay['brand_style']])@endforeach
                                @if($dealer->brands->count() > $dealerDisplay['max_brands'])<span class="text-xs text-surface-500">+{{ $dealer->brands->count() - $dealerDisplay['max_brands'] }}</span>@endif
                            </div>
                        </div>
                    @endif
                    @if($dealerDisplay['categories'] && $dealer->categories->isNotEmpty())
                        <div class="rounded-2xl border border-surface-200 bg-white p-5 sm:p-6">
                            <h2 class="text-lg font-bold text-surface-900">Dòng sản phẩm</h2>
                            <div class="mt-4 {{ $dealerDisplay['product_line_style'] === 'grid' ? 'grid grid-cols-2 gap-2' : 'flex flex-wrap gap-2' }}" data-product-line-style="{{ $dealerDisplay['product_line_style'] }}">
                                @foreach($dealer->categories->take($dealerDisplay['max_product_lines']) as $category)@include('dealers._category-item', ['category' => $category, 'style' => $dealerDisplay['product_line_style']])@endforeach
                                @if($dealer->categories->count() > $dealerDisplay['max_product_lines'])<span class="px-1 py-1 text-xs text-surface-500">+{{ $dealer->categories->count() - $dealerDisplay['max_product_lines'] }}</span>@endif
                            </div>
                        </div>
                    @endif
                    @if($dealerDisplay['services'] && $dealer->services->isNotEmpty())
                        <div class="rounded-2xl border border-surface-200 bg-white p-5 sm:p-6">
                            <h2 class="text-lg font-bold text-surface-900">Dịch vụ</h2>
                            <div class="mt-4 {{ $dealerDisplay['service_style'] === 'grid' ? 'grid grid-cols-2 gap-2' : 'flex flex-wrap gap-2' }}" data-service-style="{{ $dealerDisplay['service_style'] }}">
                                @foreach($dealer->services->take($dealerDisplay['max_services']) as $service)@include('dealers._service-item', ['service' => $service, 'style' => $dealerDisplay['service_style']])@endforeach
                                @if($dealer->services->count() > $dealerDisplay['max_services'])<span class="px-1 py-1 text-xs text-surface-500">+{{ $dealer->services->count() - $dealerDisplay['max_services'] }} dịch vụ</span>@endif
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            @if($dealer->map_links)
                <section class="overflow-hidden rounded-2xl border border-surface-200 bg-white p-5 sm:p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-bold text-surface-900">Vị trí đại lý</h2><div class="flex gap-3 text-sm font-semibold text-primary-700"><a class="hover:underline" href="{{ $dealer->map_links['google'] }}" target="_blank" rel="noopener noreferrer">Google Maps</a><a class="hover:underline" href="{{ $dealer->map_links['osm'] }}" target="_blank" rel="noopener noreferrer">OpenStreetMap</a></div></div>
                    <x-dealer-map :markers="[app(\App\Http\Controllers\DealerController::class)->marker($dealer)]" />
                </section>
            @endif

            <section id="reviews" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
                <div class="space-y-4 rounded-2xl border border-surface-200 bg-white p-5 sm:p-6">
                    <div><h2 class="text-xl font-bold text-surface-900">Đánh giá đại lý</h2><p class="mt-1 text-sm text-surface-600">Đánh giá được hiển thị sau khi được kiểm duyệt.</p></div>
                    @if(session('dealer_review_success'))<p role="status" class="rounded-lg bg-emerald-50 p-3 text-emerald-800">{{ session('dealer_review_success') }}</p>@endif
                    @if($errors->any())<p role="alert" class="rounded-lg bg-red-50 p-3 text-red-800">{{ $errors->first() }}</p>@endif
                    @if($dealer->reviews_visible)
                        @if($dealer->review_count > 0)
                            <div class="grid gap-2 border-b border-surface-100 pb-4" aria-label="Phân bố đánh giá">
                                @foreach(range(5, 1) as $star)
                                    <div class="flex items-center gap-3 text-sm"><span class="w-12 shrink-0">{{ $star }} sao</span><progress class="h-2 min-w-0 flex-1 accent-amber-500" max="{{ max(1, $dealer->review_count) }}" value="{{ $ratingDistribution[$star] ?? 0 }}" aria-label="{{ $star }} sao"></progress><span class="w-6 text-right">{{ $ratingDistribution[$star] ?? 0 }}</span></div>
                                @endforeach
                            </div>
                        @endif
                        <p class="text-sm text-surface-600">{{ $dealer->review_count ? number_format($dealer->average_rating, 1, ',', '.').'/5 · '.$dealer->review_count.' đánh giá' : 'Chưa có đánh giá công khai.' }}</p>
                        @forelse($reviews as $review)<article class="rounded-xl border border-surface-200 p-4"><p class="flex items-center gap-2 font-semibold text-surface-900"><x-heroicon-s-star class="h-4 w-4 text-amber-500" />{{ $review->customer_name }} · {{ $review->rating }}/5</p>@if($review->title)<h3 class="mt-2 font-semibold">{{ $review->title }}</h3>@endif<p class="mt-2 leading-6 text-surface-700">{{ $review->content }}</p></article>@empty<p class="text-sm text-surface-600">Hãy là người đầu tiên gửi đánh giá.</p>@endforelse
                        {{ $reviews->links() }}
                    @endif
                </div>
                @if($dealer->review_enabled)
                    @include('dealers._review-form')
                @endif
            </section>
        </div>
    </main>
    @push('schema')
        @php
            $schema = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $dealer->name,
                'url' => route('dealers.show', $dealer->slug), 'address' => ['@type' => 'PostalAddress', 'streetAddress' => $dealer->address_line,
                    'addressLocality' => $dealer->ward?->full_name, 'addressRegion' => $dealer->province?->full_name, 'addressCountry' => 'VN']];
            if ($dealer->phone || $dealer->hotline) $schema['telephone'] = $dealer->hotline ?: $dealer->phone;
            if ($dealer->latitude !== null && $dealer->longitude !== null) $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $dealer->latitude, 'longitude' => (float) $dealer->longitude];
            if ($dealer->review_count > 0 && $dealer->reviews_visible) $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($dealer->average_rating, 1), 'reviewCount' => $dealer->review_count, 'bestRating' => 5, 'worstRating' => 1];
        @endphp
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endpush
</x-layouts.app>
