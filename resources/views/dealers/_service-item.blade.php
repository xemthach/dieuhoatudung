@php($serviceClass = $style === 'grid' ? 'flex min-h-20 min-w-24 flex-col justify-center text-center' : 'inline-flex items-center')
<span class="{{ $serviceClass }} gap-1.5 rounded-lg border border-surface-200 bg-surface-50 px-2.5 py-1.5 text-xs font-medium text-surface-700">
    @if(in_array($style, ['icon_badge', 'grid'], true))<x-heroicon-o-wrench-screwdriver class="h-4 w-4 text-primary-700" />@endif
    {{ $service->name }}
</span>
