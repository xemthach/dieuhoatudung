@props(['product', 'limit' => 3])

@if($product->badges && count($product->badges) > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1']) }}>
        @foreach(collect($product->badges)->take($limit) as $badge)
            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-bold uppercase tracking-wider {{ $badge['css_class'] }}">
                {{ $badge['label'] }}
            </span>
        @endforeach
    </div>
@endif
