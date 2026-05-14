{{-- Quote Commitment Block — CMS-managed sidebar widget --}}
@php
    $block = \App\Models\QuoteCommitmentBlock::active()->with('activeItems')->first();
    $hasBlock = $block && $block->activeItems->isNotEmpty();
    $iconMap = \App\Models\QuoteCommitmentItem::iconSvgMap();
@endphp

<div class="rounded-2xl border border-surface-200 bg-white p-5">
    @if($hasBlock)
    {{-- ═══ DYNAMIC BLOCK ═══ --}}
    <h3 class="mb-3 font-bold text-surface-800">{{ $block->title }}</h3>
    @if($block->description)
    <p class="mb-3 text-sm text-surface-500">{{ $block->description }}</p>
    @endif
    <ul class="space-y-2 text-sm text-surface-600">
        @foreach($block->activeItems as $item)
        <li class="flex items-start gap-2">
            @if($item->icon_type === 'heroicon' && isset($iconMap[$item->icon_name]))
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 {{ $item->icon_color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconMap[$item->icon_name] }}"/>
                </svg>
            @elseif($item->icon_type === 'image' && $item->icon_image_url)
                <img src="{{ $item->icon_image_url }}" alt="" class="mt-0.5 h-4 w-4 flex-shrink-0 object-contain" loading="lazy">
            @elseif($item->icon_type === 'svg' && $item->sanitized_svg)
                <div class="mt-0.5 h-4 w-4 flex-shrink-0 {{ $item->icon_color }} [&>svg]:h-full [&>svg]:w-full">{!! $item->sanitized_svg !!}</div>
            @else
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconMap['check-circle'] }}"/>
                </svg>
            @endif
            {{ $item->title }}
        </li>
        @endforeach
    </ul>
    @else
    {{-- ═══ FALLBACK ═══ --}}
    <h3 class="mb-3 font-bold text-surface-800">Cam kết kỹ thuật & triển khai</h3>
    <ul class="space-y-2 text-sm text-surface-600">
        @foreach([
            'Tư vấn công suất & phương án kỹ thuật theo thực tế công trình',
            'Báo giá chi tiết, minh bạch theo từng hạng mục',
            'Khảo sát & đề xuất giải pháp tối ưu vận hành dài hạn',
            'Thi công đúng tiêu chuẩn kỹ thuật HVAC',
            'Chính sách bảo hành được đối chiếu theo từng sản phẩm hoặc báo giá',
        ] as $fallback)
        <li class="flex items-start gap-2">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $fallback }}
        </li>
        @endforeach
    </ul>
    @endif
</div>
