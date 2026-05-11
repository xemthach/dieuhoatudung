<x-layouts.app
    :seo-title="'Blog Kiến Thức ' . setting('general.site_name', '') . config('seo.defaults.title_suffix')"
    :seo-description="'Tổng hợp bài viết kiến thức, hướng dẫn chọn mua, lắp đặt và bảo trì điều hòa tủ đứng. Cập nhật liên tục từ chuyên gia HVAC.'"
    :canonical="route('blog.index')"
>
    <x-breadcrumb :items="[
        ['label' => 'Blog'],
    ]" />

    <section class="py-8 lg:py-12">
        <div class="container-main">
            <div class="lg:flex lg:gap-8">
                {{-- Main content --}}
                <div class="flex-1">
                    <h1 class="mb-6 text-2xl font-bold text-surface-900 sm:text-3xl">Blog Kiến Thức Điều Hòa</h1>

                    @if($posts->isNotEmpty())
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($posts as $post)
                                <x-post-card :post="$post" />
                            @endforeach
                        </div>

                        <div class="mt-8">
                            {{ $posts->links() }}
                        </div>
                    @else
                        <div class="rounded-xl border border-surface-200 bg-white py-16 text-center">
                            <p class="text-surface-500">Chưa có bài viết nào.</p>
                        </div>
                    @endif
                </div>

                {{-- Sidebar --}}
                <aside class="mt-8 lg:mt-0 lg:w-72 lg:flex-shrink-0">
                    {{-- Categories --}}
                    <div class="rounded-xl border border-surface-200 bg-white p-5">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-surface-900">Chuyên mục</h3>
                        <ul class="space-y-2">
                            @foreach($categories as $cat)
                                <li>
                                    <a href="{{ route('blog.index') }}?category={{ $cat->slug }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-surface-600 transition-colors hover:bg-surface-50 hover:text-primary-600">
                                        <span>{{ $cat->name }}</span>
                                        <span class="rounded-full bg-surface-100 px-2 py-0.5 text-xs text-surface-500">{{ $cat->posts_count }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- CTA Box --}}
                    <div class="mt-6 rounded-xl bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-center text-white">
                        <h3 class="text-lg font-bold">Cần tư vấn?</h3>
                        <p class="mt-2 text-sm text-primary-100">Chuyên gia sẵn sàng hỗ trợ bạn chọn điều hòa phù hợp.</p>
                        <a href="{{ route('quote.index') }}" class="mt-4 inline-block rounded-lg bg-white px-6 py-2.5 text-sm font-semibold text-primary-700 transition-colors hover:bg-primary-50">
                            Nhận báo giá
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    {{-- Blog Index Schema --}}
    @push('schema')
    @php
        $schemaService = app(\App\Services\Schema\SchemaService::class);
    @endphp
    {!! \App\Services\Schema\SchemaService::toScript($schemaService->breadcrumbs([
        ['label' => 'Trang chủ', 'url' => route('home')],
        ['label' => 'Blog'],
    ])) !!}
    @endpush
</x-layouts.app>
