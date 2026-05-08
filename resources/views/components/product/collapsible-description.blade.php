{{-- Collapsible Description Component --}}
{{-- Usage: <x-product.collapsible-description :content="$product->long_description" :settings="$descriptionSettings" /> --}}

@props(['content', 'settings'])

@php
    $isCollapsible = $settings['collapsible'] ?? true;
    $collapsedHeight = $settings['collapsed_height'] ?? 420;
    $showButton = $settings['show_button'] ?? true;
@endphp

@if($content)
    @if($isCollapsible && $showButton)
        <div
            x-data="{
                expanded: false,
                shouldCollapse: false,
                collapsedHeight: {{ $collapsedHeight }}
            }"
            x-init="$nextTick(() => {
                if ($refs.descContent && $refs.descContent.scrollHeight > collapsedHeight) {
                    shouldCollapse = true;
                }
            })"
        >
            <div
                x-ref="descContent"
                :style="!expanded && shouldCollapse ? 'max-height: ' + collapsedHeight + 'px' : 'max-height: none'"
                class="relative overflow-hidden transition-all duration-500 ease-in-out"
            >
                <div class="prose prose-sm max-w-none text-surface-700 sm:prose-base prose-headings:text-surface-900 prose-a:text-primary-600 prose-img:rounded-xl">
                    {!! $content !!}
                </div>

                {{-- Gradient overlay --}}
                <div
                    x-show="!expanded && shouldCollapse"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="pointer-events-none absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-white via-white/80 to-transparent"
                ></div>
            </div>

            {{-- Toggle button --}}
            <div x-show="shouldCollapse" class="mt-4 text-center">
                <button
                    type="button"
                    @click="expanded = !expanded"
                    class="group inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-6 py-2.5 text-sm font-semibold text-primary-600 transition-all hover:border-primary-300 hover:bg-primary-100 hover:shadow-md"
                >
                    <span x-text="expanded ? 'Thu gọn' : 'Xem thêm nội dung'"></span>
                    <svg
                        class="h-4 w-4 transition-transform duration-300"
                        :class="expanded ? 'rotate-180' : ''"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            </div>
        </div>
    @else
        {{-- Non-collapsible fallback --}}
        <div class="prose prose-sm max-w-none text-surface-700 sm:prose-base prose-headings:text-surface-900 prose-a:text-primary-600 prose-img:rounded-xl">
            {!! $content !!}
        </div>
    @endif
@else
    <p class="text-surface-400">Chưa có mô tả chi tiết cho sản phẩm này.</p>
@endif
