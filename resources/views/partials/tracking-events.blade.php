{{-- Google Ads Conversion Tracking + DataLayer Events --}}
{{-- Include via @push('scripts') on specific pages --}}

@if(setting('tracking.google_ads_conversion_id'))
<script>
window.trackConversion = function(label, value, currency) {
    if (typeof gtag === 'function') {
        gtag('event', 'conversion', {
            'send_to': '{{ setting("tracking.google_ads_conversion_id") }}/' + label,
            'value': value || 0,
            'currency': currency || 'VND'
        });
    }
};
</script>
@endif

<script>
window.dataLayer = window.dataLayer || [];

(function() {
    const clickIds = ['gclid', 'gbraid', 'wbraid'];
    const params = new URLSearchParams(window.location.search);
    const maxAge = 90 * 24 * 60 * 60;

    clickIds.forEach(function(key) {
        const incoming = params.get(key);

        if (incoming) {
            localStorage.setItem(key, incoming);
            document.cookie = key + '=' + encodeURIComponent(incoming) + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
        }

        const stored = incoming || localStorage.getItem(key) || ((document.cookie.match('(^|;)\\s*' + key + '\\s*=\\s*([^;]+)') || [])[2] ? decodeURIComponent((document.cookie.match('(^|;)\\s*' + key + '\\s*=\\s*([^;]+)') || [])[2]) : '');

        if (stored) {
            document.querySelectorAll('input[name="' + key + '"]').forEach(function(input) {
                if (! input.value) {
                    input.value = stored;
                }
            });
        }
    });
})();

// Phone click tracking
document.querySelectorAll('a[href^="tel:"]').forEach(function(el) {
    el.addEventListener('click', function() {
        dataLayer.push({
            'event': 'phone_click',
            'phone_number': el.getAttribute('href').replace('tel:', '')
        });
        @if(setting('tracking.google_ads_phone_label'))
        if (typeof trackConversion === 'function') {
            trackConversion('{{ setting("tracking.google_ads_phone_label") }}');
        }
        @endif
    });
});

// Zalo click tracking
document.querySelectorAll('a[href*="zalo.me"]').forEach(function(el) {
    el.addEventListener('click', function() {
        dataLayer.push({
            'event': 'zalo_click',
            'zalo_url': el.getAttribute('href')
        });
    });
});
</script>
