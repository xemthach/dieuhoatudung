<x-layouts.app
    :seo-title="($post->seo_title ?? $post->title) . config('seo.defaults.title_suffix')"
    :seo-description="$post->seo_description ?? $post->excerpt ?? ''"
    :canonical="route('blog.show', $post->slug)"
    :og-title="$post->og_title ?? $post->title"
    :og-description="$post->og_description ?? $post->excerpt ?? ''"
    :og-image="$post->og_image ? media_url($post->og_image) : ($post->cover_image ? media_url($post->cover_image) : null)"
    og-type="article"
>
    <x-breadcrumb :items="[
        ['label' => 'Blog', 'url' => route('blog.index')],
        ['label' => $post->category?->name ?? 'Bài viết', 'url' => route('blog.index') . '?category=' . ($post->category?->slug ?? '')],
        ['label' => $post->title],
    ]" />

    <article class="py-8 lg:py-12">
        <div class="container-main">
            <div class="lg:flex lg:gap-10">
                {{-- Article content --}}
                <div class="flex-1">
                    {{-- Header --}}
                    <header class="mb-8">
                        @if($post->category)
                            <a href="{{ route('blog.index') }}?category={{ $post->category->slug }}" class="mb-3 inline-block rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-700 transition-colors hover:bg-primary-200">
                                {{ $post->category->name }}
                            </a>
                        @endif

                        <h1 class="text-2xl font-bold text-surface-900 sm:text-3xl lg:text-4xl">{{ $post->title }}</h1>

                        <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-surface-500">
                            @if($post->author)
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700">
                                        {{ strtoupper(substr($post->author->name, 0, 1)) }}
                                    </div>
                                    <span>{{ $post->author->name }}</span>
                                </div>
                            @endif
                            @if($post->published_at)
                                <time datetime="{{ $post->published_at->toDateString() }}">
                                    {{ $post->published_at->format('d/m/Y') }}
                                </time>
                            @endif
                        </div>
                    </header>

                    {{-- Cover image --}}
                    @if($post->cover_image)
                        <div class="mb-8 overflow-hidden rounded-2xl">
                            <img
                                src="{{ media_url($post->cover_image) }}"
                                alt="{{ $post->title }}"
                                class="w-full object-cover"
                            >
                        </div>
                    @endif

                    {{-- Content --}}
                    <div class="prose prose-lg max-w-none text-surface-700 prose-headings:text-surface-900 prose-a:text-primary-600 prose-a:no-underline hover:prose-a:underline prose-img:rounded-xl">
                        {!! $post->content !!}
                    </div>

                    {{-- Tags --}}
                    @if($post->tags->isNotEmpty())
                        <div class="mt-8 flex flex-wrap gap-2 border-t border-surface-200 pt-6">
                            <span class="text-sm font-medium text-surface-500">Tags:</span>
                            @foreach($post->tags as $tag)
                                <span class="rounded-full bg-surface-100 px-3 py-1 text-xs font-medium text-surface-600">{{ $tag->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Related Products --}}
                    @if($post->products->isNotEmpty())
                        <div class="mt-8 rounded-xl border border-surface-200 bg-surface-50 p-6">
                            <h3 class="mb-4 text-lg font-bold text-surface-900">Sản phẩm liên quan</h3>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                @foreach($post->products as $product)
                                    <x-product-card :product="$product" :show-badges="false" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- FAQ --}}
                    @if($post->activeFaqs->isNotEmpty())
                        <div class="mt-8">
                            <h3 class="mb-4 text-lg font-bold text-surface-900">Câu hỏi thường gặp</h3>
                            <div class="space-y-3">
                                @foreach($post->activeFaqs as $index => $faq)
                                    <x-faq-item :faq="$faq" :open="$index === 0" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Internal Link Suggestions (approved only) --}}
                    <x-internal-links
                        :source-type="\App\Models\Post::class"
                        :source-id="$post->id"
                    />
                </div>

                {{-- Sidebar --}}
                <aside class="mt-8 lg:mt-0 lg:w-72 lg:flex-shrink-0">
                    {{-- Related Posts --}}
                    @if($relatedPosts->isNotEmpty())
                        <div class="rounded-xl border border-surface-200 bg-white p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-surface-900">Bài viết liên quan</h3>
                            <div class="space-y-4">
                                @foreach($relatedPosts as $related)
                                    <a href="{{ route('blog.show', $related->slug) }}" class="group block">
                                        <h4 class="line-clamp-2 text-sm font-medium text-surface-700 transition-colors group-hover:text-primary-600">{{ $related->title }}</h4>
                                        <p class="mt-1 text-xs text-surface-400">{{ $related->published_at?->format('d/m/Y') }}</p>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- CTA --}}
                    <div class="mt-6 rounded-xl bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-center text-white">
                        <h3 class="text-lg font-bold">Cần tư vấn?</h3>
                        <p class="mt-2 text-sm text-primary-100">Chuyên gia sẵn sàng hỗ trợ bạn.</p>
                        <a href="{{ route('quote.index') }}" class="mt-4 inline-block rounded-lg bg-white px-6 py-2.5 text-sm font-semibold text-primary-700 transition-colors hover:bg-primary-50">
                            Nhận báo giá
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </article>

    {{-- Article Schema.org JSON-LD --}}
    @push('schema')
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Article",
        "headline": "{{ e($post->title) }}",
        "description": "{{ e($post->excerpt ?? $post->seo_description ?? '') }}",
        "url": "{{ route('blog.show', $post->slug) }}",
        @if($post->cover_image)
        "image": "{{ media_url($post->cover_image) }}",
        @endif
        @if($post->author)
        "author": {
            "@@type": "Person",
            "name": "{{ e($post->author->name) }}"
        },
        @endif
        @if($post->published_at)
        "datePublished": "{{ $post->published_at->toIso8601String() }}",
        @endif
        "dateModified": "{{ $post->updated_at->toIso8601String() }}",
        "publisher": {
            "@@type": "Organization",
            "name": "{{ config('seo.og.site_name') }}"
        }
    }
    </script>
    @if($post->activeFaqs->isNotEmpty())
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "FAQPage",
        "mainEntity": [
            @foreach($post->activeFaqs as $index => $faq)
            {
                "@@type": "Question",
                "name": "{{ e($faq->question) }}",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "{{ e(strip_tags($faq->answer)) }}"
                }
            }{{ !$loop->last ? ',' : '' }}
            @endforeach
        ]
    }
    </script>
    @endif
    @endpush

</x-layouts.app>

