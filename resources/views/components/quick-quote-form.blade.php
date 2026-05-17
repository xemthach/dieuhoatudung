@props([
    'product' => null, // Product model - required for product lead
])

{{--
    QUICK QUOTE FORM
    Use on: product detail pages, sidebar CTAs
    Only 2 visible fields: name + phone
    Product context passed as hidden fields
    lead_type = product, intent_score = 100
--}}
<div class="quick-quote-wrap" id="quick-quote-form">

    {{-- Success state --}}
    <div x-data="{ sent: false, sending: false }">

        {{-- Success message --}}
        <div x-show="sent" x-transition class="rounded-xl border border-green-200 bg-green-50 p-5 text-center">
            <div class="mb-2 flex justify-center">
                <svg class="h-10 w-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="font-bold text-green-800">Yêu cầu đã gửi thành công!</p>
            <p class="mt-1 text-sm text-green-700">Chúng tôi sẽ liên hệ trong vòng <strong>30 phút</strong>.</p>
        </div>

        {{-- Form --}}
        <form x-show="!sent" method="POST" action="{{ route('quote.quick') }}"
            @submit.prevent="
                if(sending) return;
                sending = true;
                const fd = new FormData($el);
                csrfFetch($el.action, {
                    method: 'POST',
                    body: fd,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(r => r.ok ? (sent = true) : r.json().then(d => { sending = false; alert(Object.values(d.errors || {}).flat().join('\n')); }))
                .catch(() => { sending = false; $el.submit(); });
            "
            novalidate>
            @csrf
            <input type="hidden" name="lead_type"    value="product">
            <input type="hidden" name="intent_score" value="100">
            @if($product)
            <input type="hidden" name="product_id"   value="{{ $product->id }}">
            <input type="hidden" name="product_name" value="{{ $product->name }}">
            <input type="hidden" name="product_sku"  value="{{ $product->sku ?? '' }}">
            <input type="hidden" name="product_url"  value="{{ route('product.show', $product->slug) }}">
            <input type="hidden" name="product_brand"    value="{{ $product->brand?->name ?? '' }}">
            <input type="hidden" name="product_category" value="{{ $product->category?->name ?? '' }}">
            <input type="hidden" name="product_capacity_btu" value="{{ $product->btu ?? '' }}">
            @endif
            <input type="hidden" name="source_page"  value="{{ url()->current() }}">
            <input type="hidden" name="utm_source"   value="{{ request('utm_source') }}">
            <input type="hidden" name="utm_medium"   value="{{ request('utm_medium') }}">
            <input type="hidden" name="utm_campaign" value="{{ request('utm_campaign') }}">
            <input type="hidden" name="gclid"        value="{{ request('gclid') }}">
            <input type="hidden" name="gbraid"       value="{{ request('gbraid') }}">
            <input type="hidden" name="wbraid"       value="{{ request('wbraid') }}">
            {{-- Honeypot --}}
            <div style="position:absolute;left:-9999px;height:0;overflow:hidden" aria-hidden="true">
                <input type="text" name="website_url" autocomplete="off" tabindex="-1">
            </div>

            <div class="space-y-3">
                <div>
                    <label class="qqf-label" for="qq_name">Họ và tên <span class="text-red-500">*</span></label>
                    <input type="text" id="qq_name" name="full_name"
                        class="qqf-input"
                        placeholder="Nguyễn Văn A"
                        required autocomplete="name">
                </div>
                <div>
                    <label class="qqf-label" for="qq_phone">Số điện thoại <span class="text-red-500">*</span></label>
                    <input type="tel" id="qq_phone" name="phone"
                        class="qqf-input"
                        placeholder="09x xxx xxxx"
                        required autocomplete="tel">
                </div>
                <div>
                    <label class="qqf-label" for="qq_email">Email <span class="text-surface-400 font-normal">(không bắt buộc)</span></label>
                    <input type="email" id="qq_email" name="email"
                        class="qqf-input"
                        placeholder="email@example.com"
                        autocomplete="email">
                </div>
                <div>
                    <label class="qqf-label" for="qq_city">Tỉnh / Thành phố <span class="text-surface-400 font-normal">(không bắt buộc)</span></label>
                    <input type="text" id="qq_city" name="province_city"
                        class="qqf-input"
                        placeholder="Hà Nội, TP.HCM, Đà Nẵng..."
                        autocomplete="address-level1">
                </div>
                <button type="submit"
                    :disabled="sending"
                    class="qqf-btn w-full"
                    :class="sending ? 'opacity-70 cursor-not-allowed' : ''">
                    <template x-if="!sending">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Nhận báo giá ngay
                        </span>
                    </template>
                    <template x-if="sending">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Đang gửi...
                        </span>
                    </template>
                </button>
            </div>

            <p class="mt-3 text-center text-xs text-surface-400">
                Phản hồi theo lịch tư vấn &mdash; Báo giá theo nhu cầu
            </p>
        </form>

    </div>
</div>

@once
@push('head')
<style>
.qqf-label { display:block; margin-bottom:.25rem; font-size:.8125rem; font-weight:600; color:#374151; }
.qqf-input { display:block; width:100%; border-radius:.625rem; border:1.5px solid #e2e8f0; padding:.625rem .875rem; font-size:.9375rem; outline:none; transition:border-color .15s, box-shadow .15s; background:#fff; }
.qqf-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.qqf-btn { display:flex; align-items:center; justify-content:center; gap:.5rem; border-radius:.75rem; background:linear-gradient(135deg,#f97316,#ea580c); padding:.75rem 1.5rem; font-size:.9375rem; font-weight:700; color:#fff; box-shadow:0 2px 8px rgba(249,115,22,.35); transition:all .15s; border:none; cursor:pointer; }
.qqf-btn:hover:not(:disabled) { background:linear-gradient(135deg,#ea580c,#c2410c); box-shadow:0 4px 12px rgba(249,115,22,.45); transform:translateY(-1px); }
</style>
@endpush
@endonce
