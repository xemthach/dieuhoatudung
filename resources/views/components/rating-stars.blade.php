{{-- Reusable Rating Stars Component --}}
{{-- Usage: <x-rating-stars :rating="4.5" size="md" /> --}}
{{-- Interactive: <x-rating-stars :rating="5" size="lg" interactive /> --}}

@props([
    'rating' => 0,
    'max' => 5,
    'size' => 'sm',
    'interactive' => false,
])

@php
    $sizeClasses = match($size) {
        'xs' => 'h-3.5 w-3.5',
        'sm' => 'h-4 w-4',
        'md' => 'h-5 w-5',
        'lg' => 'h-6 w-6',
        'xl' => 'h-8 w-8',
        default => 'h-4 w-4',
    };
    $filledClass = 'text-yellow-400';
    $emptyClass = 'text-slate-300';
@endphp

@if($interactive)
    {{-- Interactive star rating (Alpine.js) --}}
    <div
        x-data="{ rating: {{ (int) $rating }}, hoverRating: 0 }"
        class="flex items-center gap-0.5"
        role="radiogroup"
        aria-label="Chọn số sao đánh giá"
    >
        @for($i = 1; $i <= $max; $i++)
            <button
                type="button"
                @click="rating = {{ $i }}"
                @mouseenter="hoverRating = {{ $i }}"
                @mouseleave="hoverRating = 0"
                class="focus:outline-none focus:ring-2 focus:ring-yellow-300 rounded-sm transition-transform hover:scale-110"
                :aria-label="'{{ $i }} sao'"
                role="radio"
                :aria-checked="rating === {{ $i }}"
            >
                <svg
                    class="{{ $sizeClasses }} transition-colors duration-150"
                    :class="(hoverRating || rating) >= {{ $i }} ? '{{ $filledClass }}' : '{{ $emptyClass }}'"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
            </button>
        @endfor
        <span class="ml-2 text-sm text-surface-500" x-text="rating + ' sao'"></span>
        <input type="hidden" name="rating" :value="rating">
    </div>
@else
    {{-- Static star display --}}
    <div
        class="flex items-center gap-0.5"
        role="img"
        aria-label="Đánh giá {{ $rating }} trên {{ $max }} sao"
    >
        @for($i = 1; $i <= $max; $i++)
            <svg
                class="{{ $sizeClasses }} {{ $i <= round($rating) ? $filledClass : $emptyClass }}"
                fill="currentColor"
                viewBox="0 0 20 20"
            >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
        @endfor
    </div>
@endif
