<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    {{-- SEO Meta --}}
    <title>{{ $seoTitle ?? config('seo.defaults.meta_description') }}</title>
    <meta name="description" content="{{ $seoDescription ?? config('seo.defaults.meta_description') }}">
    <meta name="robots" content="{{ $robots ?? config('seo.defaults.robots') }}">
    @if(isset($canonical))
        <link rel="canonical" href="{{ $canonical }}">
    @endif

    {{-- Site Verification --}}
    @if(setting('seo.google_site_verification'))
        <meta name="google-site-verification" content="{{ setting('seo.google_site_verification') }}">
    @endif
    @if(setting('seo.bing_site_verification'))
        <meta name="msvalidate.01" content="{{ setting('seo.bing_site_verification') }}">
    @endif

    {{-- Consent Mode (must be before GTM) --}}
    @if(setting('tracking.enable_consent_mode'))
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('consent', 'default', {'ad_storage':'denied','analytics_storage':'denied','ad_user_data':'denied','ad_personalization':'denied'});
    </script>
    @endif

    {{-- Open Graph --}}
    <meta property="og:type" content="{{ $ogType ?? config('seo.og.type') }}">
    <meta property="og:title" content="{{ $ogTitle ?? $seoTitle ?? '' }}">
    <meta property="og:description" content="{{ $ogDescription ?? $seoDescription ?? '' }}">
    <meta property="og:url" content="{{ $canonical ?? request()->url() }}">
    <meta property="og:site_name" content="{{ config('seo.og.site_name') }}">
    <meta property="og:locale" content="{{ config('seo.og.locale') }}">
    @if(isset($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle ?? $seoTitle ?? '' }}">
    <meta name="twitter:description" content="{{ $ogDescription ?? $seoDescription ?? '' }}">
    @if(isset($ogImage))
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif

    {{-- Favicon --}}
    @if(setting('branding.favicon'))
        <link rel="icon" type="image/png" href="{{ media_url(setting('branding.favicon')) }}">
    @else
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
    @endif
    @if(setting('branding.apple_touch_icon'))
        <link rel="apple-touch-icon" href="{{ media_url(setting('branding.apple_touch_icon')) }}">
    @endif

    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Google Tag Manager --}}
    @if(setting('tracking.google_tag_manager_id'))
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ setting('tracking.google_tag_manager_id') }}');</script>
    @endif

    {{-- Google Analytics 4 --}}
    @if(setting('tracking.google_analytics_id') && !setting('tracking.google_tag_manager_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ setting('tracking.google_analytics_id') }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ setting('tracking.google_analytics_id') }}');</script>
    @endif

    {{-- Facebook Pixel --}}
    @if(setting('tracking.facebook_pixel_id'))
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ setting('tracking.facebook_pixel_id') }}');fbq('track','PageView');</script>
    @endif

    {{-- Custom Head Scripts --}}
    @if(setting('tracking.custom_head_scripts'))
    {!! setting('tracking.custom_head_scripts') !!}
    @endif

    @if(setting('seo.enable_schema', true))
        {{-- JSON-LD Schema --}}
        @stack('schema')
    @endif

    {{-- Additional Head --}}
    @stack('head')
</head>
<body class="min-h-screen bg-surface-50 font-sans text-surface-800 antialiased">

    {{-- GTM noscript --}}
    @if(setting('tracking.google_tag_manager_id'))
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ setting('tracking.google_tag_manager_id') }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    {{-- Custom Body Scripts --}}
    @if(setting('tracking.custom_body_scripts'))
    {!! setting('tracking.custom_body_scripts') !!}
    @endif
    {{-- Skip to content --}}
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[999] focus:rounded-lg focus:bg-primary-600 focus:px-4 focus:py-2 focus:text-white">
        Chuyển đến nội dung chính
    </a>

    {{-- Header --}}
    @include('partials.header')

    {{-- Main Content --}}
    <main id="main-content">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    @include('partials.footer')

    {{-- Sticky CTA Mobile --}}
    @include('partials.sticky-cta')

    {{-- Site Campaigns / Popups --}}
    <x-site-campaigns />

    {{-- Additional Scripts --}}
    @stack('scripts')

    {{-- Tracking Events (phone click, zalo click, conversion) --}}
    @include('partials.tracking-events')

    {{-- Custom Footer Scripts --}}
    @if(setting('tracking.custom_footer_scripts'))
    {!! setting('tracking.custom_footer_scripts') !!}
    @endif

</body>
</html>
