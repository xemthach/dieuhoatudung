@if($latestPosts->isNotEmpty())
    <section class="py-12 lg:py-16">
        <div class="container-main">
            <x-section-heading
                title="Kiến Thức Điều Hòa"
                subtitle="Bài viết hữu ích giúp bạn lựa chọn và sử dụng điều hòa tủ đứng hiệu quả"
            />
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($latestPosts as $post)
                    <x-post-card :post="$post" />
                @endforeach
            </div>
            <div class="mt-8 text-center">
                <a href="{{ route('blog.index') }}" class="btn-outline">
                    Xem tất cả bài viết
                    <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>
    @endif
