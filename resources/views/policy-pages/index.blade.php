<x-layouts.app
    :seo-title="'Chính sách | ' . setting('general.site_name', 'Điều Hòa Tủ Đứng')"
    :seo-description="'Các chính sách mua hàng, bảo hành, lắp đặt, vận chuyển và đổi trả tại ' . setting('general.company_name', 'Điều Hòa Tủ Đứng')"
>
    <div class="container-main py-8 lg:py-12">
        {{-- Breadcrumb --}}
        <nav class="mb-6 text-sm text-surface-500" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1">
                <li><a href="/" class="hover:text-primary-600 transition-colors">Trang chủ</a></li>
                <li class="text-surface-400">/</li>
                <li class="text-surface-700 font-medium">Chính sách</li>
            </ol>
        </nav>

        <h1 class="mb-8 text-2xl font-bold text-surface-900 lg:text-3xl">Chính sách</h1>

        @if($pages->count() > 0)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($pages as $page)
            <a href="{{ $page->public_url }}" class="group rounded-2xl bg-white p-6 shadow-sm border border-surface-200 transition-all hover:shadow-md hover:border-primary-200">
                <div class="mb-3">
                    <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">
                        {{ $page->type->label() }}
                    </span>
                </div>
                <h2 class="text-lg font-semibold text-surface-900 group-hover:text-primary-600 transition-colors">
                    {{ $page->title }}
                </h2>
                <p class="mt-2 text-sm text-surface-500 line-clamp-2">
                    {{ Str::limit(strip_tags($page->content), 120) }}
                </p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary-600">
                    Xem chi tiết
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </a>
            @endforeach
        </div>
        @else
        <div class="rounded-2xl bg-white p-12 text-center shadow-sm border border-surface-200">
            <p class="text-surface-500">Chưa có trang chính sách nào.</p>
        </div>
        @endif
    </div>
</x-layouts.app>
