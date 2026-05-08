@props([
    'product',
    'showBadges' => true,
])

@php
    $hasDiscount = $product->sale_price && $product->sale_price < $product->regular_price;
@endphp

<article {{ $attributes->merge(['class' => 'card group']) }} id="product-card-{{ $product->id }}">
    {{-- Image --}}
    <a href="{{ route('product.show', $product->slug) }}" class="relative block aspect-square overflow-hidden bg-surface-100">
        @if($product->main_image)
            <img
                src="{{ $product->card_image_url }}"
                alt="{{ $product->name }}"
                class="h-full w-full object-contain p-4 transition-transform duration-500 group-hover:scale-105"
                loading="lazy"
            >
        @else
            <img
                src="{{ $product->card_image_url }}"
                alt="{{ $product->name }}"
                class="h-full w-full object-contain p-4 opacity-60 transition-transform duration-500 group-hover:scale-105"
                loading="lazy"
            >
        @endif

        @if($showBadges)
            <div class="absolute left-3 top-3">
                <x-product-badges :product="$product" class="flex-col items-start gap-1.5" />
            </div>
        @endif
    </a>

    {{-- Content --}}
    <div class="p-4">
        {{-- Brand --}}
        @if($product->brand)
            <p class="mb-1 text-xs font-medium uppercase tracking-wider text-surface-400">
                {{ $product->brand->name }}
            </p>
        @endif

        {{-- Name --}}
        <h3 class="line-clamp-2 min-h-[2.5rem] text-sm font-semibold text-surface-800 transition-colors group-hover:text-primary-600">
            <a href="{{ route('product.show', $product->slug) }}">
                {{ $product->name }}
            </a>
        </h3>

        {{-- Specs summary --}}
        <div class="mt-2 flex flex-wrap gap-2 text-xs text-surface-500">
            @if($product->btu)
                <span class="rounded bg-surface-100 px-1.5 py-0.5">{{ number_format($product->btu) }} BTU</span>
            @endif
            @if($product->inverter)
                <span class="rounded bg-primary-50 px-1.5 py-0.5 text-primary-700">Inverter</span>
            @endif
        </div>

        {{-- Price --}}
        <div class="mt-3 flex items-baseline gap-2">
            @if($hasDiscount)
                <span class="text-lg font-bold text-danger-600">{{ number_format($product->sale_price) }}₫</span>
                <span class="text-sm text-surface-400 line-through">{{ number_format($product->regular_price) }}₫</span>
            @elseif($product->regular_price)
                <span class="text-lg font-bold text-surface-900">{{ number_format($product->regular_price) }}₫</span>
            @else
                <span class="text-sm font-semibold text-primary-600">Liên hệ báo giá</span>
            @endif
        </div>

        {{-- CTA --}}
        <div class="mt-3 flex gap-2">
            @if($product->stock_status === 'out_of_stock')
                <a href="{{ route('quote.index') }}?product={{ $product->slug }}" class="flex-1 rounded-lg border border-surface-300 bg-surface-100 px-3 py-2 text-center text-xs font-semibold text-surface-600 transition-colors hover:bg-surface-200">
                    Liên hệ tương đương
                </a>
            @else
                <a href="{{ route('product.show', $product->slug) }}" class="flex-1 rounded-lg bg-primary-600 px-3 py-2 text-center text-xs font-semibold text-white transition-colors hover:bg-primary-700">
                    Xem chi tiết
                </a>
            @endif
            <button type="button" onclick="addToCompare('{{ $product->slug }}')" class="rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-surface-600 transition hover:bg-surface-100 hover:text-primary-600" title="Thêm vào so sánh">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </button>
        </div>
    </div>
</article>
