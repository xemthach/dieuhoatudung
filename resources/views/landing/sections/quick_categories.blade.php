{{-- Quick Categories Section --}}
@if(isset($categories) && $categories->isNotEmpty())
<section class="border-b border-surface-200 bg-white py-12 lg:py-16" id="landing-categories">
    <div class="container-main">
        <x-section-heading
            :title="$section->title ?? 'Danh Mục Điều Hòa Tủ Đứng'"
            :subtitle="$section->subtitle ?? ''"
        />
        <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 lg:gap-6">
            @foreach($categories as $cat)
                <a href="{{ route('category.show', $cat->slug) }}" class="group flex flex-col items-center gap-3 rounded-xl border border-surface-200 bg-surface-50 p-5 text-center transition-all duration-300 hover:border-primary-300 hover:bg-white hover:shadow-lg">
                    <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary-100 text-primary-600 transition-colors group-hover:bg-primary-600 group-hover:text-white">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-surface-800 transition-colors group-hover:text-primary-600">{{ $cat->name }}</h3>
                        <p class="mt-0.5 text-xs text-surface-500">{{ $cat->products_count }} sản phẩm</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
