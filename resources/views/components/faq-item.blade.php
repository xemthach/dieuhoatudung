@props([
    'faq',
    'open' => false,
])

<div
    x-data="{ open: {{ $open ? 'true' : 'false' }} }"
    {{ $attributes->merge(['class' => 'rounded-xl border border-surface-200 bg-white transition-all duration-200']) }}
    :class="{ 'ring-2 ring-primary-100 border-primary-300': open }"
>
    <button
        type="button"
        @click="open = !open"
        class="flex w-full items-center justify-between px-5 py-4 text-left"
        :aria-expanded="open"
    >
        <span class="text-sm font-semibold text-surface-800 sm:text-base">{{ $faq->question }}</span>
        <svg
            class="h-5 w-5 flex-shrink-0 text-surface-400 transition-transform duration-200"
            :class="{ 'rotate-180': open }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-collapse x-cloak>
        <div class="border-t border-surface-100 px-5 py-4 text-sm leading-relaxed text-surface-600">
            {!! nl2br(e($faq->answer)) !!}
        </div>
    </div>
</div>
