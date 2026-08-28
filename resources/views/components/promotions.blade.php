@props(['promotions' => null])

@php
    $promotions = $promotions ?? app(\App\Services\Marketing\PromotionDisplayResolver::class)->forRequest(request());
    $sanitizer = app(\App\Services\Content\RichHtmlSanitizer::class);
@endphp

@foreach($promotions as $promotion)
    @if(app(\App\Services\Marketing\PromotionDisplayResolver::class)->isRenderable($promotion))
        @switch($promotion->placement)
            @case('announcement_bar')
                <aside class="promotion-display promotion-display--announcement" data-promotion-id="{{ $promotion->id }}" data-promotion-placement="announcement_bar" aria-label="Khuyến mãi">
                    <strong>{{ $promotion->banner_copy ?: $promotion->title }}</strong>
                    @if($promotion->cta_content)<span>{{ $promotion->cta_content }}</span>@endif
                </aside>
                @break

            @case('banner')
            @case('landing')
                <section class="promotion-display promotion-display--{{ $promotion->placement }}" data-promotion-id="{{ $promotion->id }}" data-promotion-placement="{{ $promotion->placement }}">
                    <p class="promotion-display__eyebrow">Chương trình đang áp dụng</p>
                    <h2>{{ $promotion->banner_copy ?: $promotion->title }}</h2>
                    @if($promotion->description)<p>{{ $promotion->description }}</p>@endif
                    @if($promotion->content)<div class="promotion-display__content">{!! $sanitizer->sanitize((string) $promotion->content) !!}</div>@endif
                    @if($promotion->cta_content)<p class="promotion-display__cta">{{ $promotion->cta_content }}</p>@endif
                </section>
                @break

            @case('popup')
                <aside class="promotion-display promotion-display--popup" data-promotion-id="{{ $promotion->id }}" data-promotion-placement="popup" role="dialog" aria-modal="false" aria-label="{{ $promotion->title }}">
                    <button type="button" class="promotion-display__close" data-promotion-close aria-label="Đóng">×</button>
                    <h2>{{ $promotion->title }}</h2>
                    @if($promotion->description)<p>{{ $promotion->description }}</p>@endif
                    @if($promotion->content)<div class="promotion-display__content">{!! $sanitizer->sanitize((string) $promotion->content) !!}</div>@endif
                    @if($promotion->cta_content)<p class="promotion-display__cta">{{ $promotion->cta_content }}</p>@endif
                </aside>
                @break
        @endswitch
    @endif
@endforeach

@if($promotions->isNotEmpty())
    <style>
        .promotion-display { border: 1px solid #fed7aa; background: #fff7ed; color: #7c2d12; }
        .promotion-display--announcement { display: flex; gap: .75rem; justify-content: center; padding: .65rem 1rem; }
        .promotion-display--banner, .promotion-display--landing { margin: 1.5rem auto; max-width: 76rem; border-radius: .75rem; padding: 1.5rem; }
        .promotion-display--popup { position: fixed; z-index: 65; right: 1rem; bottom: 5rem; width: min(92vw, 26rem); border-radius: .75rem; padding: 1.25rem; box-shadow: 0 18px 50px rgba(15,23,42,.2); }
        .promotion-display__eyebrow { font-size: .75rem; font-weight: 700; text-transform: uppercase; }
        .promotion-display h2 { margin-top: .25rem; font-size: 1.25rem; font-weight: 800; }
        .promotion-display__content, .promotion-display__cta { margin-top: .75rem; }
        .promotion-display__close { float: right; font-size: 1.25rem; }
    </style>
    <script>
        document.querySelectorAll('[data-promotion-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                button.closest('[data-promotion-placement="popup"]')?.remove();
            });
        });
    </script>
@endif
