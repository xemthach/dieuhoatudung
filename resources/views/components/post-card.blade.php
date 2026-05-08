@props([
    'post',
])

<article {{ $attributes->merge(['class' => 'card group']) }} id="post-card-{{ $post->id }}">
    {{-- Cover Image --}}
    <a href="{{ route('blog.show', $post->slug) }}" class="relative block aspect-video overflow-hidden bg-surface-100">
        @if($post->cover_image)
            <img
                src="{{ media_url($post->cover_image) }}"
                alt="{{ $post->title }}"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                loading="lazy"
            >
        @else
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-100 to-primary-50 text-primary-300">
                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </div>
        @endif

        {{-- Category Badge --}}
        @if($post->category)
            <span class="absolute left-3 top-3 rounded-full bg-primary-600/90 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-sm">
                {{ $post->category->name }}
            </span>
        @endif
    </a>

    {{-- Content --}}
    <div class="p-5">
        {{-- Date --}}
        <div class="mb-2 flex items-center gap-2 text-xs text-surface-400">
            <time datetime="{{ $post->published_at?->toDateString() }}">
                {{ $post->published_at?->format('d/m/Y') ?? 'Chưa xuất bản' }}
            </time>
            @if($post->author)
                <span>&bull;</span>
                <span>{{ $post->author->name }}</span>
            @endif
        </div>

        {{-- Title --}}
        <h3 class="line-clamp-2 text-base font-semibold text-surface-800 transition-colors group-hover:text-primary-600">
            <a href="{{ route('blog.show', $post->slug) }}">
                {{ $post->title }}
            </a>
        </h3>

        {{-- Excerpt --}}
        @if($post->excerpt)
            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-surface-500">
                {{ $post->excerpt }}
            </p>
        @endif

        {{-- Read More --}}
        <a href="{{ route('blog.show', $post->slug) }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 transition-colors hover:text-primary-800">
            Đọc tiếp
            <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</article>
