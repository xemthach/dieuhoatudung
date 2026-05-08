@props([
    'context' => 'header',  // header, footer, mobile, admin
    'mode' => 'auto',       // auto, logo_text, logo_only, text_only
    'theme' => 'light',     // light, dark
    'class' => '',
])

@php
    // Helper: extract string path from a setting that might be an array
    $resolveSetting = function (string $key) {
        $val = setting($key);
        if (is_array($val)) $val = collect($val)->first();
        return is_string($val) && filled($val) && !in_array($val, ['{}', '[]'], true) ? $val : null;
    };

    // Resolve mode: auto → use global setting; otherwise use passed mode
    $displayMode = $mode === 'auto'
        ? setting('branding.logo_display_mode', 'logo_text')
        : $mode;

    // Context-specific overrides
    $headerMode = setting('branding.header_logo_mode');
    if ($context === 'header' && $headerMode && $headerMode !== 'auto') {
        $displayMode = $headerMode;
    }
    $footerMode = setting('branding.footer_logo_mode');
    if ($context === 'footer' && $footerMode && $footerMode !== 'auto') {
        $displayMode = $footerMode;
    }

    // Resolve image source based on context + theme (cascade with fallbacks)
    $logoPath = match(true) {
        $context === 'footer' && !empty($resolveSetting('branding.logo_footer_image'))
            => $resolveSetting('branding.logo_footer_image'),
        $context === 'footer' && $theme === 'dark' && !empty($resolveSetting('branding.logo_dark_image'))
            => $resolveSetting('branding.logo_dark_image'),
        $context === 'mobile' && !empty($resolveSetting('branding.logo_mobile_image'))
            => $resolveSetting('branding.logo_mobile_image'),
        $theme === 'dark' && !empty($resolveSetting('branding.logo_dark_image'))
            => $resolveSetting('branding.logo_dark_image'),
        !empty($resolveSetting('branding.logo_image'))
            => $resolveSetting('branding.logo_image'),
        default => null,
    };

    $logoImage = $logoPath ? media_url($logoPath) : null;

    // Alt text
    $altText = setting('branding.logo_alt_text')
        ?: setting('general.site_name', 'Trang chủ');

    // Text to display
    $logoText = setting('branding.logo_text')
        ?: setting('general.site_name', '');

    // Dimensions
    $maxHeight = (int) setting('branding.logo_height_max', 48);
    $widthDesktop = (int) setting('branding.logo_width_desktop', 160);
    $widthMobile = (int) setting('branding.logo_width_mobile', 120);

    // Text color
    $textColor = setting('branding.logo_text_color', '');
    $textStyle = $textColor ? "color: {$textColor};" : '';

    // If mode requires logo but no image exists → fallback to text_only
    $hasImage = !empty($logoImage);
    if (!$hasImage && in_array($displayMode, ['logo_text', 'logo_only'])) {
        $displayMode = 'text_only';
    }

    // Footer theme defaults
    $textClass = $context === 'footer' ? 'text-white' : 'text-surface-900';
@endphp

<div {{ $attributes->merge(['class' => "site-logo site-logo--{$context} {$class}"]) }}>
    @if($displayMode === 'logo_text')
        {{-- Logo image + Text --}}
        <img
            src="{{ $logoImage }}"
            alt="{{ $altText }}"
            class="site-logo__image"
            style="max-height: {{ $maxHeight }}px; max-width: {{ $context === 'mobile' ? $widthMobile : $widthDesktop }}px; width: auto; object-fit: contain;"
            loading="{{ $context === 'header' ? 'eager' : 'lazy' }}"
        >
        <span class="site-logo__text text-lg font-bold {{ $textClass }}" @if($textStyle) style="{{ $textStyle }}" @endif>
            {{ $logoText }}
        </span>

    @elseif($displayMode === 'logo_only')
        {{-- Logo image only --}}
        <img
            src="{{ $logoImage }}"
            alt="{{ $altText }}"
            class="site-logo__image"
            style="max-height: {{ $maxHeight }}px; max-width: {{ $context === 'mobile' ? $widthMobile : $widthDesktop }}px; width: auto; object-fit: contain;"
            loading="{{ $context === 'header' ? 'eager' : 'lazy' }}"
        >

    @else {{-- text_only --}}
        <span class="site-logo__text text-xl font-bold {{ $textClass }}" @if($textStyle) style="{{ $textStyle }}" @endif>
            {{ $logoText }}
        </span>
    @endif
</div>
