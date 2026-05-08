@props([
    'sourceType',  // Full class name e.g. App\Models\Post
    'sourceId',    // integer
])

@php
    $suggestions = \App\Models\InternalLinkSuggestion::forSource($sourceType, $sourceId)
        ->approved()
        ->with('target')
        ->orderByDesc('score')
        ->limit(6)
        ->get()
        ->filter(fn ($s) => $s->target !== null && $s->target_url !== null);
@endphp

@if ($suggestions->isNotEmpty())
    <aside class="mt-12 rounded-2xl border border-surface-200 bg-surface-50 p-6 sm:p-8">
        <h3 class="text-lg font-bold text-surface-900 mb-5 flex items-center gap-2">
            <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
            Nội dung liên quan
        </h3>

        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach ($suggestions as $suggestion)
                @php
                    $typeLabel = match(class_basename($suggestion->target_type)) {
                        'Post'            => 'Bài viết',
                        'Product'         => 'Sản phẩm',
                        'ProductCategory' => 'Danh mục',
                        'CaseStudy'       => 'Dự án',
                        default           => 'Trang',
                    };
                    $typeColor = match(class_basename($suggestion->target_type)) {
                        'Post'    => 'bg-blue-100 text-blue-700',
                        'Product' => 'bg-green-100 text-green-700',
                        'CaseStudy' => 'bg-purple-100 text-purple-700',
                        default   => 'bg-surface-100 text-surface-700',
                    };
                @endphp
                <li>
                    <a href="{{ $suggestion->target_url }}"
                       class="group flex items-start gap-3 rounded-xl bg-white border border-surface-200 p-3 transition-all hover:border-primary-400 hover:shadow-sm">
                        <span class="mt-0.5 shrink-0 inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $typeColor }}">
                            {{ $typeLabel }}
                        </span>
                        <span class="text-sm font-medium text-surface-800 group-hover:text-primary-600 line-clamp-2">
                            {{ $suggestion->anchor_text ?: $suggestion->target_title }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </aside>
@endif
