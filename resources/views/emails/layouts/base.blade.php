<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? '' }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .email-wrapper { width: 100%; background-color: #f4f5f7; padding: 32px 0; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .email-header { background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 24px 32px; text-align: center; }
        .email-header h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 600; }
        .email-header p { margin: 4px 0 0; color: rgba(255,255,255,0.8); font-size: 13px; }
        .email-body { padding: 32px; font-size: 15px; line-height: 1.7; color: #374151; }
        .email-body h2 { font-size: 18px; color: #111827; margin: 0 0 12px; }
        .email-body h3 { font-size: 16px; color: #1f2937; margin: 0 0 8px; }
        .email-body p { margin: 0 0 16px; }
        .email-body a { color: #2563eb; text-decoration: underline; }
        .email-body ul, .email-body ol { margin: 0 0 16px; padding-left: 24px; }
        .email-body li { margin-bottom: 6px; }
        .email-body blockquote { margin: 0 0 16px; padding: 12px 16px; border-left: 4px solid #3b82f6; background: #f0f7ff; color: #1e40af; }
        .email-cta { text-align: center; padding: 8px 32px 24px; }
        .email-cta a { display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-weight: 600; font-size: 15px; }
        .email-footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 20px 32px; text-align: center; font-size: 12px; color: #9ca3af; line-height: 1.6; }
        .email-footer a { color: #6b7280; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            {{-- Header --}}
            <div class="email-header">
                <h1>{{ $site_name ?? setting('general.site_name', config('app.name', '')) }}</h1>
                @if(!empty($header_subtitle))
                <p>{{ $header_subtitle }}</p>
                @endif
            </div>

            {{-- Body Content --}}
            <div class="email-body">
                {!! $content !!}
            </div>

            {{-- CTA Button (optional) --}}
            @if(!empty($cta_url) && !empty($cta_text))
            <div class="email-cta">
                <a href="{{ $cta_url }}">{{ $cta_text }}</a>
            </div>
            @endif

            {{-- Footer --}}
            <div class="email-footer">
                <p>{{ $site_name ?? config('app.name') }} — {{ $hotline ?? '' }}</p>
                <p><a href="{{ $website_url ?? config('app.url') }}">{{ $website_url ?? config('app.url') }}</a></p>
                <p style="margin-top:8px; color:#d1d5db;">Email này được gửi tự động, vui lòng không trả lời.</p>
            </div>
        </div>
    </div>
</body>
</html>
