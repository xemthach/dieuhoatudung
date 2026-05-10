{{-- Hero Slider Component — Alpine.js powered, admin-managed slides --}}
@php
    $slides = \App\Models\HeroSlide::active()->get();
    $hasSlides = $slides->isNotEmpty();
    $slideCount = $slides->count();
    $autoplayInterval = $hasSlides ? ($slides->first()->duration_ms ?? 6000) : 6000;
@endphp

<section class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-surface-900"
         x-data="heroSlider({{ $slideCount }}, {{ $autoplayInterval }})"
         x-init="init()"
         @mouseenter="pause()" @mouseleave="resume()">

    @if($hasSlides)
    {{-- ═══ DYNAMIC SLIDES ═══ --}}
    <div class="relative min-h-[360px] sm:min-h-[460px] lg:min-h-[540px]">
        @foreach($slides as $index => $slide)
        <div x-show="current === {{ $index }}"
             x-transition:enter="transition ease-out duration-700"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-500"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0" x-cloak="{{ $index > 0 ? 'true' : '' }}">

            {{-- Background layer --}}
            @if($slide->background_type === 'image' && $slide->background_image_url)
                @if($index === 0)
                    <img src="{{ $slide->background_image_url }}" alt="{{ $slide->title ?? 'Hero background' }}"
                         class="absolute inset-0 h-full w-full object-cover" fetchpriority="high">
                @else
                    <img src="{{ $slide->background_image_url }}" alt="{{ $slide->title ?? 'Hero background' }}"
                         class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                @endif
            @elseif($slide->background_type === 'video' && $slide->background_video_url)
                <video class="absolute inset-0 h-full w-full object-cover" autoplay muted loop playsinline
                       poster="{{ $slide->background_image_url }}">
                    <source src="{{ $slide->background_video_url }}" type="video/mp4">
                </video>
            @elseif($slide->background_type === 'embed' && $slide->embed_url)
                @php
                    $safeEmbed = preg_match('#^https://(www\.)?(youtube\.com/embed|player\.vimeo\.com/video)/#', $slide->embed_url);
                @endphp
                @if($safeEmbed)
                <iframe src="{{ $slide->embed_url }}?autoplay=1&mute=1&loop=1&controls=0&showinfo=0"
                        class="absolute inset-0 h-full w-full object-cover pointer-events-none"
                        frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                @endif
            @else
                <div class="absolute inset-0" style="{{ $slide->background_css }}"></div>
            @endif

            {{-- Overlay --}}
            @if($slide->overlay_enabled)
            <div class="absolute inset-0" style="background-color: {{ $slide->overlay_color }}; opacity: {{ ($slide->overlay_opacity ?? 20) / 100 }};"></div>
            @endif

            {{-- Decorative blur orbs --}}
            <div class="pointer-events-none absolute inset-0 overflow-hidden opacity-10">
                <div class="absolute -right-20 -top-20 h-96 w-96 rounded-full bg-primary-400 blur-3xl"></div>
                <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-accent-400 blur-3xl"></div>
            </div>

            {{-- Content --}}
            <div class="container-main relative flex min-h-[360px] sm:min-h-[460px] lg:min-h-[540px] items-center py-8 sm:py-12 lg:py-16">
                <div class="w-full {{ match($slide->content_position) {
                    'left'   => 'max-w-2xl',
                    'right'  => 'max-w-2xl ml-auto',
                    default  => 'max-w-3xl mx-auto text-center',
                } }}"
                     x-show="current === {{ $index }}"
                     x-transition:enter="transition ease-out duration-700 delay-200"
                     x-transition:enter-start="{{ match($slide->animation_type) {
                         'slide-up'   => 'opacity-0 translate-y-8',
                         'slide-left' => 'opacity-0 translate-x-8',
                         'zoom-in'    => 'opacity-0 scale-95',
                         default      => 'opacity-0',
                     } }}"
                     x-transition:enter-end="opacity-100 translate-y-0 translate-x-0 scale-100">

                    @if($slide->title)
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl lg:text-5xl"
                        style="color: {{ $slide->text_color }}; text-align: {{ $slide->text_align }};">
                        {{ $slide->title }}
                        @if($slide->highlight_text)
                        <span class="text-accent-400"> {{ $slide->highlight_text }}</span>
                        @endif
                    </h1>
                    @endif

                    @if($slide->subtitle || $slide->description)
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-primary-100"
                       style="text-align: {{ $slide->text_align }};">
                        {{ $slide->subtitle ?? $slide->description }}
                    </p>
                    @endif


                    {{-- CTAs --}}
                    @if($slide->cta_primary_text || $slide->cta_secondary_text)
                    <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row
                                {{ $slide->text_align === 'left' ? 'sm:justify-start' : ($slide->text_align === 'right' ? 'sm:justify-end' : 'sm:justify-center') }}">
                        @if($slide->cta_primary_text)
                        <a href="{{ $slide->cta_primary_url ?? '#' }}"
                           class="{{ match($slide->cta_primary_style) {
                               'primary' => 'btn-primary',
                               'outline' => 'btn-outline border-white/30 text-white hover:bg-white/10',
                               default   => 'btn-accent',
                           } }} w-full px-8 py-4 text-base sm:w-auto"
                           @if($slide->open_in_new_tab) target="_blank" rel="noopener" @endif
                           aria-label="{{ $slide->cta_primary_text }}">
                            {{ $slide->cta_primary_text }}
                        </a>
                        @endif
                        @if($slide->cta_secondary_text)
                        <a href="{{ $slide->cta_secondary_url ?? '#' }}"
                           class="{{ match($slide->cta_secondary_style) {
                               'accent'  => 'btn-accent',
                               'primary' => 'btn-primary',
                               default   => 'btn-outline border-white/30 text-white hover:bg-white/10',
                           } }} w-full px-8 py-4 text-base sm:w-auto"
                           @if($slide->open_in_new_tab) target="_blank" rel="noopener" @endif
                           aria-label="{{ $slide->cta_secondary_text }}">
                            {{ $slide->cta_secondary_text }}
                        </a>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        {{-- Navigation dots --}}
        @if($slideCount > 1)
        <div class="absolute bottom-6 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2">
            @for($i = 0; $i < $slideCount; $i++)
            <button @click="goTo({{ $i }})"
                    :class="current === {{ $i }} ? 'bg-white w-8' : 'bg-white/40 w-3 hover:bg-white/70'"
                    class="h-3 rounded-full transition-all duration-300"
                    aria-label="Slide {{ $i + 1 }}"></button>
            @endfor
        </div>

        {{-- Arrow navigation --}}
        <button @click="prev()" class="absolute left-4 top-1/2 z-20 -translate-y-1/2 rounded-full bg-black/20 p-2 text-white/80 backdrop-blur-sm transition hover:bg-black/40 hover:text-white" aria-label="Slide trước">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button @click="next()" class="absolute right-4 top-1/2 z-20 -translate-y-1/2 rounded-full bg-black/20 p-2 text-white/80 backdrop-blur-sm transition hover:bg-black/40 hover:text-white" aria-label="Slide tiếp">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
        @endif
    </div>

    @else
    {{-- ═══ DEFAULT FALLBACK HERO ═══ --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden opacity-10">
        <div class="absolute -right-20 -top-20 h-96 w-96 rounded-full bg-primary-400 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-accent-400 blur-3xl"></div>
    </div>
    <div class="container-main relative py-16 lg:py-24">
        <div class="mx-auto max-w-3xl text-center">
            <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl lg:text-5xl">
                {{ setting('general.site_name', '') }} <span class="text-accent-400">Chính Hãng</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-primary-100">
                Giải pháp làm mát chuyên nghiệp cho không gian lớn. Đa dạng thương hiệu, công suất phù hợp mọi nhu cầu.
                Miễn phí lắp đặt, bảo hành chính hãng toàn quốc.
            </p>



            <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="{{ route('quote.index') }}" class="btn-accent w-full px-8 py-4 text-base sm:w-auto">
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Nhận báo giá miễn phí
                </a>
                <a href="{{ route('products.index') }}" class="btn-outline w-full border-white/30 px-8 py-4 text-base text-white hover:bg-white/10 sm:w-auto">
                    Xem sản phẩm
                </a>
            </div>
        </div>
    </div>
    @endif
</section>

@once
@push('scripts')
<script>
function heroSlider(count, interval) {
    return {
        current: 0,
        total: count,
        timer: null,
        interval: interval,

        init() {
            if (this.total > 1) this.startAutoplay();
        },

        startAutoplay() {
            this.timer = setInterval(() => this.next(), this.interval);
        },

        next() {
            this.current = (this.current + 1) % this.total;
        },

        prev() {
            this.current = (this.current - 1 + this.total) % this.total;
        },

        goTo(index) {
            this.current = index;
            this.resetTimer();
        },

        pause() {
            clearInterval(this.timer);
        },

        resume() {
            if (this.total > 1) this.startAutoplay();
        },

        resetTimer() {
            clearInterval(this.timer);
            if (this.total > 1) this.startAutoplay();
        }
    }
}
</script>
@endpush
@endonce
