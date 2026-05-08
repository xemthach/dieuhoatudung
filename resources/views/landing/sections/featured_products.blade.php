{{-- Featured Products Section --}}
@if(isset($featuredProducts) && $featuredProducts->isNotEmpty())
<section class="bg-surface-50 py-12 lg:py-16" id="landing-products">
    <div class="container-main">
        <x-section-heading
            :title="$section->title ?? 'Sản Phẩm Bán Chạy'"
            :subtitle="$section->subtitle ?? ''"
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
