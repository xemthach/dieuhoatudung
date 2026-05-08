@props([
    'brand',
])

<a
    href="{{ route('brands.show', $brand->slug) }}"
    {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 rounded-xl border border-surface-200 bg-white p-6 transition-all duration-300 hover:border-primary-300 hover:shadow-lg group']) }}
>
    @if($brand->logo_url)
        <img
            src="{{ $brand->logo_url }}"
            alt="{{ $brand->name }}"
            class="h-12 w-auto object-contain grayscale transition-all duration-300 group-hover:grayscale-0"
            loading="lazy"
        >
    @else
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-100 text-sm font-bold text-surface-600 transition-colors group-hover:bg-primary-100 group-hover:text-primary-700">
            {{ strtoupper(substr($brand->name, 0, 2)) }}
        </div>
    @endif
    <span class="text-sm font-semibold text-surface-700 transition-colors group-hover:text-primary-600">{{ $brand->name }}</span>
</a>
