{{-- filament/mail-template-preview.blade.php --}}
{{-- Renders template HTML in iframe using sample data. 100% inline styles (no Tailwind). --}}
@php
    /**
     * $template — MailTemplate model
     * $rendered — ['subject' => string, 'html' => string]
     * $sample — array of sample variable values
     *
     * srcdoc encoding: & → &amp;, " → &quot; (HTML spec)
     */
    $renderedHtml = $rendered['html'] ?? $template->body_html ?? '';
    $renderedSubject = $rendered['subject'] ?? $template->subject ?? '';

    $srcdocHtml = str_replace(
        ['&', '"'],
        ['&amp;', '&quot;'],
        $renderedHtml
    );
@endphp

<div
    x-data="{
        mode: 'desktop',
        copyDone: '',
        copyText(text, label) {
            navigator.clipboard.writeText(text).then(() => {
                this.copyDone = label;
                setTimeout(() => this.copyDone = '', 2000);
            }).catch(() => {
                /* fallback */
                const el = document.createElement('textarea');
                el.value = text;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
                this.copyDone = label;
                setTimeout(() => this.copyDone = '', 2000);
            });
        }
    }"
    style="display:flex; flex-direction:column; gap:12px;"
>

    {{-- ── Toolbar ──────────────────────────────────────────────────────── --}}
    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px 14px;">

        {{-- Desktop / Mobile toggle --}}
        <div style="display:inline-flex; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; background:#fff;">
            <button
                @click="mode = 'desktop'"
                :style="mode === 'desktop'
                    ? 'background:#2563eb; color:#fff; border:none; cursor:pointer; display:flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; font-weight:500;'
                    : 'background:#fff; color:#374151; border:none; cursor:pointer; display:flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; font-weight:500;'"
            >
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Desktop
            </button>
            <button
                @click="mode = 'mobile'"
                :style="mode === 'mobile'
                    ? 'background:#2563eb; color:#fff; border:none; border-left:1px solid #e5e7eb; cursor:pointer; display:flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; font-weight:500;'
                    : 'background:#fff; color:#374151; border:none; border-left:1px solid #e5e7eb; cursor:pointer; display:flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; font-weight:500;'"
            >
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Mobile
            </button>
        </div>

        <div style="flex:1;"></div>

        {{-- Copy Subject --}}
        <button
            @click="copyText(@js($renderedSubject), 'subject')"
            :style="copyDone === 'subject'
                ? 'background:#f0fdf4; border:1px solid #86efac; color:#15803d; cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:500;'
                : 'background:#fff; border:1px solid #e5e7eb; color:#374151; cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:500;'"
        >
            <svg x-show="copyDone !== 'subject'" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <svg x-show="copyDone === 'subject'" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0; color:#16a34a;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span x-text="copyDone === 'subject' ? ' Đã copy!' : 'Copy Subject'"></span>
        </button>

        {{-- Copy HTML --}}
        <button
            @click="copyText(@js($renderedHtml), 'html')"
            :style="copyDone === 'html'
                ? 'background:#f0fdf4; border:1px solid #86efac; color:#15803d; cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:500;'
                : 'background:#fff; border:1px solid #e5e7eb; color:#374151; cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:500;'"
        >
            <svg x-show="copyDone !== 'html'" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
            </svg>
            <svg x-show="copyDone === 'html'" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0; color:#16a34a;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span x-text="copyDone === 'html' ? ' Đã copy!' : 'Copy HTML'"></span>
        </button>
    </div>

    {{-- ── Subject preview ─────────────────────────────────────────────── --}}
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:12px 16px;">
        <div style="display:flex; align-items:flex-start; gap:10px;">
            <span style="flex-shrink:0; background:#eff6ff; color:#1d4ed8; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; padding:3px 8px; border-radius:4px;">Subject</span>
            <p style="margin:0; font-size:14px; font-weight:500; color:#111827; line-height:1.5;">{{ $renderedSubject }}</p>
        </div>
    </div>

    {{-- ── Sample data used ────────────────────────────────────────────── --}}
    @if(!empty($sample))
    <details style="border:1px solid #bfdbfe; border-radius:10px; overflow:hidden;">
        <summary style="display:flex; align-items:center; gap:8px; padding:10px 16px; cursor:pointer; background:#eff6ff; font-size:13px; font-weight:500; color:#1e40af; list-style:none; user-select:none;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0; transition:transform 0.2s;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            Biến mẫu đã dùng để render preview ({{ count($sample) }} biến)
        </summary>
        <div style="padding:12px 16px; border-top:1px solid #bfdbfe; background:#fff;">
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:6px;">
                @foreach($sample as $key => $value)
                <div style="display:flex; align-items:baseline; gap:6px; font-size:12px;">
                    <code style="flex-shrink:0; background:#dbeafe; color:#1d4ed8; padding:2px 6px; border-radius:4px; font-family:monospace; font-size:11px;">{{ '{' . '{' . $key . '}' . '}' }}</code>
                    <span style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:160px;" title="{{ $value }}">{{ $value }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </details>
    @endif

    {{-- ── iframe Preview ──────────────────────────────────────────────── --}}
    <div>
        <p style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.07em; color:#9ca3af; margin:0 0 8px 0;">Preview HTML (với dữ liệu mẫu)</p>
        <div
            style="margin:0 auto; overflow:hidden; border-radius:10px; border:1px solid #e5e7eb; box-shadow:0 1px 4px rgba(0,0,0,.08); transition:max-width 0.3s ease;"
            :style="mode === 'mobile' ? 'max-width:430px' : 'max-width:100%'"
        >
            {{-- Browser chrome bar --}}
            <div
                x-show="mode === 'desktop'"
                style="display:flex; align-items:center; gap:6px; padding:8px 12px; background:#f3f4f6; border-bottom:1px solid #e5e7eb;"
            >
                <span style="width:12px; height:12px; border-radius:50%; background:#f87171; display:inline-block;"></span>
                <span style="width:12px; height:12px; border-radius:50%; background:#fbbf24; display:inline-block;"></span>
                <span style="width:12px; height:12px; border-radius:50%; background:#34d399; display:inline-block;"></span>
                <span style="flex:1; background:#fff; border-radius:6px; height:20px; margin-left:8px; display:flex; align-items:center; justify-content:center;">
                    <span style="font-size:11px; color:#9ca3af;">email preview</span>
                </span>
            </div>
            {{-- Mobile notch --}}
            <div
                x-show="mode === 'mobile'"
                style="display:flex; justify-content:center; padding:8px; background:#f3f4f6; border-bottom:1px solid #e5e7eb;"
            >
                <span style="width:48px; height:4px; border-radius:2px; background:#d1d5db; display:inline-block;"></span>
            </div>

            <iframe
                srcdoc="{!! $srcdocHtml !!}"
                style="width:100%; min-height:580px; border:0; display:block;"
                sandbox="allow-same-origin"
                loading="lazy"
                onload="try { this.style.height = Math.max(580, this.contentDocument.documentElement.scrollHeight + 20) + 'px'; } catch(e) {}"
            ></iframe>
        </div>
    </div>

    {{-- ── Raw HTML source (collapsible) ──────────────────────────────── --}}
    <details style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
        <summary style="display:flex; align-items:center; gap:8px; padding:10px 16px; cursor:pointer; background:#f9fafb; font-size:13px; font-weight:500; color:#374151; list-style:none; user-select:none;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
            </svg>
            Xem HTML source (rendered)
        </summary>
        <div style="border-top:1px solid #e5e7eb;">
            <pre style="margin:0; max-height:320px; overflow:auto; white-space:pre-wrap; word-break:break-all; background:#111827; padding:16px; font-size:11.5px; color:#86efac; font-family:monospace; line-height:1.6;"><code>{{ $renderedHtml }}</code></pre>
        </div>
    </details>

</div>
