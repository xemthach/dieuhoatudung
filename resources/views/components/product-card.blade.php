@props([
    'product',
    'showBadges' => true,
])

@php
    $price = app(\App\Services\Product\PromotionPriceResolver::class)->resolve($product);
@endphp

<article {{ $attributes->merge(['class' => 'card group']) }} id="product-card-{{ $product->id }}">
    <a href="{{ route('product.show', $product->slug) }}" class="relative block aspect-square overflow-hidden bg-surface-100">
        <img
            src="{{ $product->card_image_url }}"
            alt="{{ $product->name }}"
            class="h-full w-full object-contain p-4 {{ $product->main_image ? '' : 'opacity-60' }} transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
        >

        @if($showBadges)
            <div class="absolute left-3 top-3">
                <x-product-badges :product="$product" class="flex-col items-start gap-1.5" />
            </div>
        @endif
    </a>

    <div class="p-4">
        @if($product->brand)
            <p class="mb-1 text-xs font-medium uppercase tracking-wider text-surface-400">
                {{ $product->brand->name }}
            </p>
        @endif

        <h3 class="line-clamp-2 min-h-[2.5rem] text-sm font-semibold text-surface-800 transition-colors group-hover:text-primary-600">
            <a href="{{ route('product.show', $product->slug) }}">
                {{ $product->name }}
            </a>
        </h3>

        <div class="mt-2 flex flex-wrap gap-2 text-xs text-surface-500">
            @if($product->btu)
                <span class="rounded bg-surface-100 px-1.5 py-0.5">{{ number_format($product->btu) }} BTU</span>
            @endif
            @if($product->inverter)
                <span class="rounded bg-primary-50 px-1.5 py-0.5 text-primary-700">Inverter</span>
            @endif
        </div>

        <div class="mt-3 flex items-baseline gap-2">
            @if($price['has_discount'])
                <span class="text-lg font-bold text-danger-600">{{ number_format($price['sale_price']) }}₫</span>
                <span class="text-sm text-surface-400 line-through">{{ number_format($price['regular_price']) }}₫</span>
            @elseif($price['final_price'])
                <span class="text-lg font-bold text-surface-900">{{ number_format($price['final_price']) }}₫</span>
            @else
                <span class="text-sm font-semibold text-primary-600">Liên hệ báo giá</span>
            @endif
        </div>

        <div class="mt-3 flex gap-2">
            @if($product->stock_status === 'out_of_stock')
                <a href="{{ route('quote.index') }}?product={{ $product->slug }}" class="flex-1 rounded-lg border border-surface-300 bg-surface-100 px-3 py-2 text-center text-xs font-semibold text-surface-600 transition-colors hover:bg-surface-200">
                    Liên hệ tương đương
                </a>
            @else
                <a href="{{ route('product.show', $product->slug) }}" class="hidden flex-1 rounded-lg bg-primary-600 px-3 py-2 text-center text-xs font-semibold text-white transition-colors hover:bg-primary-700 sm:block">
                    Xem chi tiết
                </a>
                <a href="{{ route('product.show', $product->slug) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-primary-600 bg-primary-600 text-white transition hover:border-primary-700 hover:bg-primary-700 sm:hidden" aria-label="Xem chi tiết" title="Xem chi tiết">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>
            @endif
            <button type="button" onclick="addToCompare('{{ $product->slug }}')" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-surface-200 bg-surface-50 text-surface-600 transition hover:bg-surface-100 hover:text-primary-600 sm:h-auto sm:w-auto sm:px-3 sm:py-2" title="Thêm vào so sánh" aria-label="Thêm vào so sánh">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </button>
        </div>
    </div>
</article>
