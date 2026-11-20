@props(['markers' => []])
<div class="space-y-2">
    <div class="dealer-map relative z-0 h-80 w-full rounded-xl border border-surface-200" data-markers="{{ json_encode($markers, JSON_UNESCAPED_UNICODE) }}" data-tile-url="{{ config('dealers.tile_url') }}" data-attribution="{{ config('dealers.tile_attribution') }}" aria-label="Bản đồ đại lý"></div>
    <p class="dealer-map-status text-sm text-surface-600" role="status"></p>
</div>
@once
    @vite('resources/js/dealers.js')
@endonce
