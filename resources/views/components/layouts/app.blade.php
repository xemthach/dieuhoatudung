@props([
    'seoTitle' => null,
    'seoDescription' => null,
    'robots' => null,
    'canonical' => null,
    'ogType' => null,
    'ogTitle' => null,
    'ogDescription' => null,
    'ogImage' => null,
])
<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta --}}
    <title>{{ $seoTitle ?? setting('seo.default_seo_title', config('seo.defaults.title')) }}</title>
    <meta name="description" content="{{ $seoDescription ?? setting('seo.default_meta_description', config('seo.defaults.meta_description')) }}">
    <meta name="robots" content="{{ $robots ?? setting('seo.default_robots', 'index,follow') }}">
    @if($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:type" content="{{ $ogType ?? config('seo.og.type') }}">
    <meta property="og:title" content="{{ $ogTitle ?? $seoTitle ?? setting('seo.default_seo_title') }}">
    <meta property="og:description" content="{{ $ogDescription ?? $seoDescription ?? setting('seo.default_meta_description') }}">
    <meta property="og:url" content="{{ $canonical ?? request()->url() }}">
    <meta property="og:site_name" content="{{ setting('general.site_name', config('seo.og.site_name')) }}">
    <meta property="og:locale" content="{{ config('seo.og.locale') }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    {{-- Favicon --}}
    @php
        $faviconPath = setting('branding.favicon');
        if (is_array($faviconPath)) $faviconPath = collect($faviconPath)->first();
        $faviconUrl = filled($faviconPath) && !in_array($faviconPath, ['{}', '[]'], true)
            ? media_url($faviconPath)
            : '/favicon.ico';
    @endphp
    <link rel="icon" href="{{ $faviconUrl }}">
    @php
        $appleTouchPath = setting('branding.apple_touch_icon');
        if (is_array($appleTouchPath)) $appleTouchPath = collect($appleTouchPath)->first();
    @endphp
    @if(filled($appleTouchPath) && !in_array($appleTouchPath, ['{}', '[]'], true))
    <link rel="apple-touch-icon" href="{{ media_url($appleTouchPath) }}">
    @endif

    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
    </style>

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- JSON-LD Schema --}}
    @stack('schema')

    {{-- Organization + WebSite Schema (sitewide) --}}
    <script type="application/ld+json">
    [
        {
            "@@context": "https://schema.org",
            "@@type": "Organization",
            "name": "{{ setting('schema_organization.organization_name', setting('general.company_name', config('seo.og.site_name'))) }}",
            "url": "{{ setting('schema_organization.organization_url', config('app.url')) }}",
            "logo": "{{ media_url(setting('branding.logo_image'), setting('schema_organization.organization_logo', config('app.url') . '/images/logo.png')) }}",
            "contactPoint": {
                "@@type": "ContactPoint",
                "telephone": "{{ setting('schema_organization.organization_phone', setting('contact.hotline')) }}",
                "contactType": "customer service",
                "availableLanguage": "Vietnamese"
            },
            "address": {
                "@@type": "PostalAddress",
                "addressCountry": "VN",
                "streetAddress": "{{ setting('schema_organization.organization_address', setting('general.company_address')) }}"
            }
        },
        {
            "@@context": "https://schema.org",
            "@@type": "WebSite",
            "name": "{{ setting('general.site_name', config('seo.og.site_name')) }}",
            "url": "{{ setting('seo.canonical_base_url', config('app.url')) }}",
            "potentialAction": {
                "@@type": "SearchAction",
                "target": {
                    "@@type": "EntryPoint",
                    "urlTemplate": "{{ setting('seo.canonical_base_url', config('app.url')) }}/san-pham?q={search_term_string}"
                },
                "query-input": "required name=search_term_string"
            }
        }
    ]
    </script>

    @if(setting('tracking.google_analytics_id'))
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ setting('tracking.google_analytics_id') }}"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '{{ setting('tracking.google_analytics_id') }}');
        </script>
    @endif

    @if(setting('tracking.google_tag_manager_id'))
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ setting('tracking.google_tag_manager_id') }}');</script>
        <!-- End Google Tag Manager -->
    @endif

    @if(setting('tracking.facebook_pixel_id'))
        <!-- Facebook Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window,document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{{ setting('tracking.facebook_pixel_id') }}');
        fbq('track', 'PageView');
        </script>
        <noscript>
        <img height="1" width="1"
        src="https://www.facebook.com/tr?id={{ setting('tracking.facebook_pixel_id') }}&ev=PageView
        &noscript=1"/>
        </noscript>
        <!-- End Facebook Pixel Code -->
    @endif

    {!! setting('tracking.custom_head_scripts') !!}
    {{-- Additional Head --}}
    @stack('head')
</head>
<body class="min-h-screen bg-surface-50 font-sans text-surface-800 antialiased">
    @if(setting('tracking.google_tag_manager_id'))
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ setting('tracking.google_tag_manager_id') }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
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

    {{-- Compare Bar --}}
    <x-compare-bar />

    {{-- Additional Scripts --}}
    @stack('scripts')
    {!! setting('tracking.custom_body_scripts') !!}
    {!! setting('tracking.custom_footer_scripts') !!}
</body>
</html>
