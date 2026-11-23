@php
    $cardOptions = array_merge([
        'address'=>true, 'contact'=>true, 'logo' => true, 'verified' => true, 'distance' => true, 'rating' => true, 'review_count' => true,
        'brands' => true, 'categories' => true, 'services' => true, 'phone' => true,
        'brand_style' => 'logo_name', 'product_line_style' => 'icon_badge', 'service_style' => 'icon_badge',
        'card_style' => 'standard', 'max_brands' => 4, 'max_product_lines' => 4, 'max_services' => 4,
    ], $cardOptions ?? []);
    $cardClass = match ($cardOptions['card_style']) {
        'compact' => 'p-4', 'detailed' => 'p-6', default => 'p-5',
    };
    $showDetails = $cardOptions['card_style'] !== 'compact';
@endphp
<div class="grid min-w-0 gap-3 min-[360px]:grid-cols-2 lg:grid-cols-4">
@forelse($dealers as $dealer)
    <article class="min-w-0 break-words flex h-full flex-col rounded-2xl border border-surface-200 bg-white {{ $cardClass }} shadow-sm transition-shadow hover:shadow-md" data-dealer-id="{{ $dealer->id }}" data-card-style="{{ $cardOptions['card_style'] }}">
        <div class="flex items-start gap-4">
            @if($cardOptions['logo'] && $dealer->logo_url)
                <img src="{{ $dealer->logo_url }}" class="h-14 w-14 shrink-0 rounded-xl border border-surface-100 bg-white object-contain p-1" alt="Logo {{ $dealer->name }}" loading="lazy">
            @endif
            <div class="min-w-0 flex-1">
                <h2 class="break-words text-lg font-bold leading-snug text-surface-900"><a class="hover:text-primary-700" href="{{ route('dealers.show', $dealer->slug) }}">{{ $dealer->name }}</a></h2>
                @if($cardOptions['verified'] && $dealer->is_verified)
                    <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800"><x-heroicon-s-check-badge class="h-4 w-4" /> Đã xác minh</span>
                @endif
            </div>
        </div>

        @if($cardOptions['address'] && filled($dealer->full_address))
            <p class="mt-4 flex gap-2 text-sm leading-6 text-surface-600"><x-heroicon-o-map-pin class="mt-0.5 h-4 w-4 shrink-0" /><span>{{ $dealer->full_address }}</span></p>
        @endif
        @if($cardOptions['distance'] && $dealer->distance_km !== null)
            <p class="mt-2 text-sm font-semibold text-primary-700">Cách bạn {{ number_format($dealer->distance_km, 1, ',', '.') }} km</p>
        @endif
        @if($showDetails && $cardOptions['rating'] && $dealer->review_count > 0)
            <p class="mt-2 flex items-center gap-1.5 text-sm"><x-heroicon-s-star class="h-4 w-4 text-amber-500" /><strong>{{ number_format($dealer->average_rating, 1, ',', '.') }}/5</strong>@if($cardOptions['review_count'])<span class="text-surface-500">({{ $dealer->review_count }} đánh giá)</span>@endif</p>
        @endif

        @if($cardOptions['card_style'] === 'detailed' && filled($dealer->short_description))<p class="mt-3 line-clamp-3 text-sm leading-6 text-surface-600">{{ $dealer->short_description }}</p>@endif

        @if($showDetails && $cardOptions['brands'] && $dealer->brands->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Thương hiệu" data-brand-style="{{ $cardOptions['brand_style'] }}">
                @foreach($dealer->brands->take($cardOptions['max_brands']) as $brand)
                    @include('dealers._brand-item', ['brand' => $brand, 'style' => $cardOptions['brand_style']])
                @endforeach
                @if($dealer->brands->count() > $cardOptions['max_brands'])<span class="text-xs text-surface-500">+{{ $dealer->brands->count() - $cardOptions['max_brands'] }}</span>@endif
            </div>
        @endif

        @if($showDetails && $cardOptions['categories'] && $dealer->categories->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-2" aria-label="Dòng sản phẩm" data-product-line-style="{{ $cardOptions['product_line_style'] }}">
                @foreach($dealer->categories->take($cardOptions['max_product_lines']) as $category)
                    @include('dealers._category-item', ['category' => $category, 'style' => $cardOptions['product_line_style']])
                @endforeach
                @if($dealer->categories->count() > $cardOptions['max_product_lines'])<span class="px-1 py-1 text-xs text-surface-500">+{{ $dealer->categories->count() - $cardOptions['max_product_lines'] }}</span>@endif
            </div>
        @endif

        @if($showDetails && $cardOptions['services'] && $dealer->services->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-2" aria-label="Dịch vụ" data-service-style="{{ $cardOptions['service_style'] }}">
                @foreach($dealer->services->take($cardOptions['max_services']) as $service)
                    @include('dealers._service-item', ['service' => $service, 'style' => $cardOptions['service_style']])
                @endforeach
                @if($dealer->services->count() > $cardOptions['max_services'])<span class="px-1 py-1 text-xs text-surface-500">+{{ $dealer->services->count() - $cardOptions['max_services'] }} dịch vụ</span>@endif
            </div>
        @endif

        <div class="mt-auto flex flex-wrap items-center gap-3 border-t border-surface-100 pt-4 text-sm font-semibold text-primary-700">
            <a class="hover:underline" href="{{ route('dealers.show', $dealer->slug) }}">Xem chi tiết</a>
            @if($cardOptions['contact'] && $cardOptions['phone'] && ($dealer->phone || $dealer->hotline))<a class="inline-flex items-center gap-1 hover:underline" href="tel:{{ preg_replace('/[^+0-9]/', '', $dealer->hotline ?: $dealer->phone) }}"><x-heroicon-o-phone class="h-4 w-4" /> Gọi</a>@endif
            @if($dealer->map_links)<a class="inline-flex items-center gap-1 hover:underline" href="{{ $dealer->map_links['directions'] }}" target="_blank" rel="noopener noreferrer"><x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /> Chỉ đường</a>@endif
        </div>
    </article>
@empty
    <p class="rounded-xl border border-surface-200 bg-white p-6 text-surface-600 sm:col-span-2 lg:col-span-3">Không tìm thấy đại lý phù hợp. Bạn có thể đổi bộ lọc hoặc bán kính.</p>
@endforelse
</div>
