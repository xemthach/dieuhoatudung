<x-layouts.app
    :seo-title="'Thương Hiệu ' . setting('general.site_name', '') . ' Chính Hãng' . config('seo.defaults.title_suffix', '')"
    :seo-description="'Khám phá các thương hiệu điều hòa tủ đứng hàng đầu: Daikin, Gree, LG, Panasonic, Midea. Sản phẩm chính hãng, bảo hành đầy đủ.'"
    :canonical="route('brands.index')"
>
    <x-breadcrumb :items="[
        ['label' => 'Thương hiệu'],
    ]" />

    <section class="py-8 lg:py-12">
        <div class="container-main">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-extrabold text-surface-900 sm:text-3xl">Thương Hiệu {{ setting('general.site_name', '') }}</h1>
                <p class="mx-auto mt-3 max-w-2xl text-surface-600">Chúng tôi phân phối chính hãng các thương hiệu điều hòa tủ đứng hàng đầu thế giới, cam kết chất lượng và bảo hành toàn diện.</p>
            </div>

            @if($brands->isNotEmpty())
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 lg:gap-6">
                    @foreach($brands as $brand)
                        <a href="{{ route('brands.show', $brand->slug) }}" class="flex flex-col items-center gap-4 rounded-xl border border-surface-200 bg-white p-6 transition-all duration-300 hover:border-primary-300 hover:shadow-lg group">
                            @if($brand->logo_url)
                                <img
                                    src="{{ $brand->logo_url }}"
                                    alt="{{ $brand->name }}"
                                    class="h-16 w-auto object-contain grayscale transition-all duration-300 group-hover:grayscale-0"
                                    loading="lazy"
                                >
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 text-lg font-bold text-surface-600 transition-colors group-hover:bg-primary-100 group-hover:text-primary-700">
                                    {{ strtoupper(substr($brand->name, 0, 2)) }}
                                </div>
                            @endif
                            <div class="text-center">
                                <h2 class="font-semibold text-surface-800 transition-colors group-hover:text-primary-600">{{ $brand->name }}</h2>
                                @if($brand->description)
                                    <p class="mt-1 line-clamp-2 text-xs text-surface-500">{{ Str::limit($brand->description, 80) }}</p>
                                @endif
                                <span class="mt-2 inline-block text-xs font-medium text-primary-600">Xem sản phẩm →</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-surface-200 bg-white py-16 text-center">
                    <p class="text-surface-500">Chưa có thương hiệu nào.</p>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
