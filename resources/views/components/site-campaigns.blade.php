@php
    $campaigns = app(\App\Services\Marketing\SiteCampaignResolver::class)->forRequest(request());
@endphp

@if($campaigns->isNotEmpty())
    <div id="site-campaign-root" aria-live="polite">
        @foreach($campaigns as $campaign)
            @php
                $content = $campaign->content_json ?? [];
                $design = $campaign->design_json ?? [];
                $frequency = $campaign->frequency_json ?? [];
                $image = data_get($content, 'image');
                $image = is_array($image) ? collect($image)->first() : $image;
                $imageUrl = $image ? media_url($image) : null;
                $bg = data_get($design, 'background_color', '#ffffff');
                $color = data_get($design, 'text_color', '#0f172a');
                $type = $campaign->type;
                $position = data_get($design, 'position', 'center');
                $position = in_array($position, ['center', 'bottom_right', 'bottom_left'], true) ? $position : 'center';
                $videoUrl = data_get($content, 'video_url');
                $videoEmbedUrl = null;
                if (is_string($videoUrl) && $videoUrl !== '') {
                    $videoHost = strtolower((string) parse_url($videoUrl, PHP_URL_HOST));
                    $videoPath = (string) parse_url($videoUrl, PHP_URL_PATH);
                    parse_str((string) parse_url($videoUrl, PHP_URL_QUERY), $videoQuery);

                    if (str_contains($videoHost, 'youtube.com') && ! empty($videoQuery['v'])) {
                        $videoEmbedUrl = 'https://www.youtube.com/embed/' . rawurlencode((string) $videoQuery['v']);
                    } elseif (str_contains($videoHost, 'youtu.be')) {
                        $videoEmbedUrl = 'https://www.youtube.com/embed/' . rawurlencode(trim($videoPath, '/'));
                    } elseif (str_contains($videoHost, 'vimeo.com')) {
                        $videoEmbedUrl = 'https://player.vimeo.com/video/' . rawurlencode(trim($videoPath, '/'));
                    }
                }
                $isBar = in_array($type, ['top_bar', 'bottom_bar', 'floating_cta'], true);
                $panelClass = match ($type) {
                    'top_bar' => 'site-campaign__bar site-campaign__bar--top',
                    'bottom_bar' => 'site-campaign__bar site-campaign__bar--bottom',
                    'floating_cta' => 'site-campaign__floating',
                    'slide_in' => 'site-campaign__slide-in',
                    default => 'site-campaign__modal-panel',
                };
            @endphp

            <div
                class="site-campaign site-campaign--{{ $type }} site-campaign--position-{{ $position }} hidden"
                data-site-campaign
                data-campaign-id="{{ $campaign->id }}"
                data-type="{{ $type }}"
                data-device="{{ $campaign->device }}"
                data-delay="{{ (int) data_get($frequency, 'delay_seconds', 5) }}"
                data-scroll="{{ data_get($frequency, 'scroll_percent') }}"
                data-frequency="{{ data_get($frequency, 'frequency', 'session') }}"
                data-exit-intent="{{ data_get($frequency, 'exit_intent') ? '1' : '0' }}"
            >
                @unless($isBar)
                    <div class="site-campaign__backdrop" data-site-campaign-close></div>
                @endunless

                <div class="{{ $panelClass }}" role="{{ $isBar ? 'region' : 'dialog' }}" @unless($isBar) aria-modal="true" @endunless style="background-color: {{ $bg }}; color: {{ $color }};">
                    <button type="button" class="site-campaign__close" data-site-campaign-close aria-label="Đóng thông báo">×</button>

                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ data_get($content, 'title', $campaign->title) }}" class="site-campaign__image" loading="lazy">
                    @endif

                    @if($videoEmbedUrl)
                        <div class="site-campaign__video">
                            <iframe
                                src="{{ $videoEmbedUrl }}"
                                title="{{ data_get($content, 'title', $campaign->title) }}"
                                loading="lazy"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                            ></iframe>
                        </div>
                    @endif

                    <div class="site-campaign__body">
                        @if(data_get($content, 'title'))
                            <p class="site-campaign__title">{{ data_get($content, 'title') }}</p>
                        @endif
                        @if(data_get($content, 'subtitle'))
                            <p class="site-campaign__subtitle">{{ data_get($content, 'subtitle') }}</p>
                        @endif
                        @if(data_get($content, 'content'))
                            <p class="site-campaign__content">{{ data_get($content, 'content') }}</p>
                        @endif

                        <div class="site-campaign__actions">
                            @if(data_get($content, 'button_primary_text') && data_get($content, 'button_primary_url'))
                                <a href="{{ data_get($content, 'button_primary_url') }}" class="site-campaign__button site-campaign__button--primary" data-site-campaign-click="click_primary">
                                    {{ data_get($content, 'button_primary_text') }}
                                </a>
                            @endif
                            @if(data_get($content, 'button_secondary_text') && data_get($content, 'button_secondary_url'))
                                <a href="{{ data_get($content, 'button_secondary_url') }}" class="site-campaign__button site-campaign__button--secondary" data-site-campaign-click="click_secondary">
                                    {{ data_get($content, 'button_secondary_text') }}
                                </a>
                            @endif
                            @if(data_get($content, 'phone'))
                                <a href="tel:{{ data_get($content, 'phone') }}" class="site-campaign__button site-campaign__button--secondary" data-site-campaign-click="click_primary">
                                    Gọi ngay
                                </a>
                            @endif
                            @if(data_get($content, 'zalo_url'))
                                <a href="{{ data_get($content, 'zalo_url') }}" class="site-campaign__button site-campaign__button--secondary" data-site-campaign-click="click_secondary">
                                    Zalo
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <style>
        .site-campaign.hidden { display: none !important; }
        .site-campaign { position: fixed; z-index: 70; }
        .site-campaign__backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .48); }
        .site-campaign__modal-panel,
        .site-campaign__slide-in {
            position: fixed;
            max-height: min(82vh, 680px);
            overflow: auto;
            border-radius: .75rem;
            box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
        }
        .site-campaign__modal-panel {
            left: 50%;
            top: 50%;
            width: min(92vw, 520px);
            transform: translate(-50%, -50%);
        }
        .site-campaign--position-bottom_right .site-campaign__modal-panel {
            left: auto;
            right: 1rem;
            top: auto;
            bottom: 1rem;
            transform: none;
        }
        .site-campaign--position-bottom_left .site-campaign__modal-panel {
            left: 1rem;
            right: auto;
            top: auto;
            bottom: 1rem;
            transform: none;
        }
        .site-campaign__slide-in {
            right: 1rem;
            bottom: 1rem;
            width: min(92vw, 420px);
        }
        .site-campaign--position-bottom_left .site-campaign__slide-in {
            left: 1rem;
            right: auto;
        }
        .site-campaign__bar {
            position: fixed;
            left: 0;
            right: 0;
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: .75rem 3.25rem .75rem 1rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .14);
        }
        .site-campaign__bar--top { top: 0; }
        .site-campaign__bar--bottom { bottom: 0; }
        .site-campaign__floating {
            right: 1rem;
            bottom: 5.25rem;
            width: min(86vw, 320px);
            border-radius: .75rem;
            padding: .875rem;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .2);
        }
        .site-campaign--position-bottom_left .site-campaign__floating {
            left: 1rem;
            right: auto;
        }
        .site-campaign__close {
            position: absolute;
            right: .75rem;
            top: .5rem;
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, .08);
            color: inherit;
            font-size: 1.25rem;
            line-height: 1;
        }
        .site-campaign__image { width: 100%; max-height: 280px; object-fit: cover; border-radius: .75rem .75rem 0 0; }
        .site-campaign__video { position: relative; aspect-ratio: 16 / 9; background: #0f172a; border-radius: .75rem .75rem 0 0; overflow: hidden; }
        .site-campaign__video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .site-campaign__body { padding: 1.25rem; }
        .site-campaign__bar .site-campaign__body { padding: 0; text-align: center; }
        .site-campaign__title { font-weight: 800; font-size: 1.125rem; line-height: 1.3; }
        .site-campaign__subtitle { margin-top: .25rem; font-weight: 600; opacity: .86; }
        .site-campaign__content { margin-top: .5rem; font-size: .925rem; line-height: 1.55; opacity: .86; }
        .site-campaign__actions { display: flex; flex-wrap: wrap; gap: .625rem; margin-top: 1rem; }
        .site-campaign__bar .site-campaign__actions { justify-content: center; margin-top: .5rem; }
        .site-campaign__button {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            border-radius: .5rem;
            padding: .55rem .9rem;
            font-weight: 700;
            font-size: .875rem;
        }
        .site-campaign__button--primary { background: #f97316; color: #fff; }
        .site-campaign__button--secondary { background: rgba(37, 108, 232, .1); color: #1d57d5; }
        @media (max-width: 639px) {
            .site-campaign__modal-panel { width: min(94vw, 420px); }
            .site-campaign__slide-in { left: .75rem; right: .75rem; bottom: 4.75rem; width: auto; }
            .site-campaign__bar { align-items: flex-start; padding-right: 3rem; }
            .site-campaign__floating { left: .75rem; right: .75rem; bottom: 4.75rem; width: auto; }
        }
    </style>

    <script>
    (function() {
        const endpoint = @json(route('site-campaign-events.store'));
        const token = @json(csrf_token());
        const sessionKey = 'site_campaign_session_id';
        let sessionId = sessionStorage.getItem(sessionKey);
        if (!sessionId) {
            sessionId = Date.now().toString(36) + Math.random().toString(36).slice(2);
            sessionStorage.setItem(sessionKey, sessionId);
        }

        function device() {
            return window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop';
        }

        function storageFor(frequency) {
            return frequency === 'day' ? localStorage : sessionStorage;
        }

        function storageKey(el) {
            const suffix = el.dataset.frequency === 'day' ? new Date().toISOString().slice(0, 10) : 'session';
            return 'site_campaign_seen_' + el.dataset.campaignId + '_' + suffix;
        }

        function track(el, eventType) {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'site_campaign_' + eventType,
                campaign_id: el.dataset.campaignId,
                campaign_type: el.dataset.type
            });

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    campaign_id: el.dataset.campaignId,
                    event_type: eventType,
                    page_url: window.location.href,
                    device: device(),
                    session_id: sessionId
                }),
                keepalive: true
            }).catch(function() {});
        }

        function show(el) {
            if (el.dataset.device !== 'both' && el.dataset.device !== device()) return;
            if (el.dataset.frequency !== 'visit' && storageFor(el.dataset.frequency).getItem(storageKey(el))) return;

            el.classList.remove('hidden');
            if (el.dataset.frequency !== 'visit') {
                storageFor(el.dataset.frequency).setItem(storageKey(el), '1');
            }
            track(el, 'impression');
        }

        document.querySelectorAll('[data-site-campaign]').forEach(function(el) {
            const delay = Math.max(0, parseInt(el.dataset.delay || '5', 10)) * 1000;
            const scroll = parseInt(el.dataset.scroll || '', 10);
            let shown = false;
            const showOnce = function() {
                if (shown) return;
                shown = true;
                show(el);
            };

            if (!Number.isNaN(scroll) && scroll > 0) {
                window.addEventListener('scroll', function onScroll() {
                    const max = document.documentElement.scrollHeight - window.innerHeight;
                    const percent = max > 0 ? (window.scrollY / max) * 100 : 0;
                    if (percent >= scroll) {
                        window.removeEventListener('scroll', onScroll);
                        showOnce();
                    }
                }, { passive: true });
            } else if (el.dataset.exitIntent === '1' && device() === 'desktop') {
                document.addEventListener('mouseleave', function onLeave(event) {
                    if (event.clientY <= 0) {
                        document.removeEventListener('mouseleave', onLeave);
                        showOnce();
                    }
                });
                window.setTimeout(showOnce, Math.max(delay, 12000));
            } else {
                window.setTimeout(showOnce, delay);
            }

            el.querySelectorAll('[data-site-campaign-close]').forEach(function(button) {
                button.addEventListener('click', function() {
                    el.classList.add('hidden');
                    track(el, 'close');
                });
            });

            el.querySelectorAll('[data-site-campaign-click]').forEach(function(link) {
                link.addEventListener('click', function() {
                    track(el, link.dataset.siteCampaignClick || 'click_primary');
                });
            });
        });
    })();
    </script>
@endif
