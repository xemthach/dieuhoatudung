{{-- Sticky CTA Mobile — fully dynamic from Admin settings --}}
<div class="sticky-cta-mobile">
    {{-- Call Button --}}
    @if(setting('cta.mobile_bar_call_enabled', true))
    <a href="tel:{{ setting('contact.hotline', '') }}" class="text-primary-600">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        <span>{{ setting('cta.phone_cta_text', 'Gọi ngay') }}</span>
    </a>
    @endif

    {{-- Zalo Button --}}
    @if(setting('cta.mobile_bar_zalo_enabled', true))
    <a href="{{ setting('contact.zalo_link', '#') }}" target="_blank" rel="noopener" class="text-blue-500">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.329-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.178.121.13.154.305.17.427.016.122.036.4-.003.62z"/></svg>
        <span>{{ setting('cta.zalo_cta_text', 'Zalo') }}</span>
    </a>
    @endif

    {{-- Quote Button --}}
    @if(setting('cta.mobile_bar_quote_enabled', true))
    <a href="{{ setting('cta.quote_cta_link', '/bao-gia') }}" class="text-accent-600">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span>{{ setting('cta.quote_cta_text', 'Báo giá') }}</span>
    </a>
    @endif
</div>
