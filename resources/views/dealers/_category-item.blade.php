@php
    $categoryLabel = $category->name;
    $categoryIcon = $category->image ? media_url($category->image) : null;
    $categoryClass = $style === 'grid' ? 'flex min-h-20 min-w-24 flex-col justify-center text-center' : 'inline-flex items-center';
@endphp
<span class="{{ $categoryClass }} gap-1.5 rounded-lg border border-surface-200 bg-white px-2.5 py-1.5 text-xs font-medium text-surface-700">
    @if(in_array($style, ['icon_badge', 'icon_list', 'grid'], true))
        @if($categoryIcon)<img src="{{ $categoryIcon }}" class="h-5 w-5 object-contain" alt="" loading="lazy">@else<x-heroicon-o-squares-2x2 class="h-4 w-4 text-primary-700" />@endif
    @endif
    {{ $categoryLabel }}
</span>
