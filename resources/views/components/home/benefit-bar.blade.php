{{-- Benefit Bar — dynamic CMS-managed trust badges --}}
@php
    $benefits = \App\Models\HomeBenefitItem::active()->get();
    $hasBenefits = $benefits->isNotEmpty();
    $iconMap = \App\Models\HomeBenefitItem::iconSvgMap();
@endphp

<section class="border-b border-surface-200 bg-white py-6">
    <div class="container-main">
        @if($hasBenefits)
        {{-- ═══ DYNAMIC BENEFITS ═══ --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-{{ min($benefits->count(), 4) }}">
            @foreach($benefits as $item)
            <div class="flex items-center gap-3 rounded-lg p-3">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full {{ $item->bg_color }} {{ $item->icon_color }}">
                    @if($item->icon_type === 'heroicon' && isset($iconMap[$item->icon_name]))
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconMap[$item->icon_name] }}"/>
                        </svg>
                    @elseif($item->icon_type === 'image' && $item->icon_image_url)
                        <img src="{{ $item->icon_image_url }}" alt="{{ $item->title }}" class="h-5 w-5 object-contain" loading="lazy">
                    @elseif($item->icon_type === 'svg' && $item->sanitized_svg)
                        <div class="h-5 w-5 [&>svg]:h-full [&>svg]:w-full">{!! $item->sanitized_svg !!}</div>
                    @else
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconMap['check-circle'] }}"/>
                        </svg>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-semibold text-surface-900">{{ $item->title }}</p>
                    @if($item->subtitle)
                    <p class="text-xs text-surface-500">{{ $item->subtitle }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        {{-- ═══ FALLBACK DEFAULTS ═══ --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="flex items-center gap-3 rounded-lg p-3">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-surface-900">Dữ liệu sản phẩm rõ ràng</p>
                    <p class="text-xs text-surface-500">Đối chiếu theo model</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg p-3">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-surface-900">Tư vấn lắp đặt</p>
                    <p class="text-xs text-surface-500">Theo điều kiện công trình</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg p-3">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-success-500/10 text-success-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-surface-900">Chính sách theo sản phẩm</p>
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
        @endif
    </div>
</section>
