@props([
    'title',
    'subtitle' => null,
    'centered' => true,
])

<div {{ $attributes->merge(['class' => $centered ? 'text-center' : '']) }}>
    <h2 class="section-heading">{{ $title }}</h2>
    @if($subtitle)
        <p class="section-subheading {{ $centered ? 'mx-auto max-w-3xl' : '' }}">{{ $subtitle }}</p>
    @endif
</div>
