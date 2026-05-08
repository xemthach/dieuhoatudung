@props([
    'product' => null,
])

{{--
    PRODUCT QUOTE MODAL
    Triggered by: $dispatch('open-quote-modal') hoac window.openQuoteModal()
    Desktop: centered modal 480-560px
    Mobile: bottom sheet (slide up)
    AJAX submit — khong reload trang
    lead_type = product, intent_score = 100
--}}
<div
    x-data="{
        open: false,
        sending: false,
        sent: false,
        errors: {},
        formData: { full_name: '', phone: '', message: '' },

        submit() {
            if (this.sending) return;
            this.errors = {};
            if (!this.formData.full_name.trim()) { this.errors.full_name = 'Vui lòng nhập họ tên'; return; }
            if (!this.formData.phone.trim())     { this.errors.phone = 'Vui lòng nhập số điện thoại'; return; }

            this.sending = true;
            const form = this.$refs.qform;
            const fd   = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(d => {
                this.sending = false;
                if (d.success) {
                    this.sent = true;
                    // Push GTM event
                    window.dataLayer = window.dataLayer || [];
                    dataLayer.push({ event: 'quote_submitted', lead_type: 'product', product_id: {{ $product?->id ?? 'null' }} });
                    // Auto close after 3s
                    setTimeout(() => { this.open = false; this.sent = false; this.formData = { full_name: '', phone: '', message: '' }; }, 3000);
                } else {
                    this.errors = d.errors || {};
                }
            })
            .catch(() => {
                this.sending = false;
                // Fallback: submit binh thuong
                form.submit();
            });
        },

        close() {
            this.open = false;
        }
    }"
    @open-quote-modal.window="open = true; $nextTick(() => $refs.nameInput?.focus())"
    @keydown.escape.window="close()"
    class="pqm-root"
>

    {{-- ══ Backdrop ══ --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="pqm-backdrop"
        style="display:none"
        aria-hidden="true"
    ></div>

    {{-- ══ Panel: Desktop centered / Mobile bottom sheet ══ --}}
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="pqm-title"
        x-show="open"

        {{-- Desktop: scale in --}}
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"

        class="pqm-panel"
        style="display:none"
    >
        {{-- ── Header ─────────────────────────────────────── --}}
        <div class="pqm-header">
            <div>
                <h2 id="pqm-title" class="pqm-title">Nhận báo giá nhanh</h2>
                @if($product)
                <p class="pqm-subtitle">Sản phẩm: {{ Str::limit($product->name, 55) }}</p>
                @endif
            </div>
            <button type="button" @click="close()" class="pqm-close" aria-label="Đóng">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- ── Body ────────────────────────────────────────── --}}
        <div class="pqm-body">

            {{-- Success state --}}
            <div x-show="sent" x-transition class="pqm-success">
                <div class="pqm-success-icon">
                    <svg class="h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="pqm-success-title">Đã gửi thành công!</p>
                <p class="pqm-success-sub">Chúng tôi sẽ liên hệ bạn trong vòng <strong>30 phút</strong>.</p>
                <p class="pqm-success-sub" style="margin-top:.25rem">Cửa sổ tự động đóng sau 3 giây...</p>
            </div>

            {{-- Form --}}
            <form
                x-show="!sent"
                x-ref="qform"
                method="POST"
                action="{{ route('quote.quick') }}"
                @submit.prevent="submit()"
                novalidate
            >
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
                {{-- Honeypot --}}
                <div style="position:absolute;left:-9999px;height:0;overflow:hidden" aria-hidden="true">
                    <input type="text" name="website_url" autocomplete="off" tabindex="-1">
                </div>

                <div class="pqm-fields">
                    {{-- Họ tên --}}
                    <div>
                        <label class="pqm-label" for="pqm_name">
                            Họ và tên <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="pqm_name"
                            name="full_name"
                            x-ref="nameInput"
                            x-model="formData.full_name"
                            :class="errors.full_name ? 'pqm-input pqm-input--err' : 'pqm-input'"
                            placeholder="Nguyễn Văn A"
                            autocomplete="name"
                        >
                        <p x-show="errors.full_name" x-text="errors.full_name" class="pqm-error"></p>
                    </div>

                    {{-- Số điện thoại --}}
                    <div>
                        <label class="pqm-label" for="pqm_phone">
                            Số điện thoại <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="tel"
                            id="pqm_phone"
                            name="phone"
                            x-model="formData.phone"
                            :class="errors.phone ? 'pqm-input pqm-input--err' : 'pqm-input'"
                            placeholder="09x xxx xxxx"
                            autocomplete="tel"
                        >
                        <p x-show="errors.phone" x-text="errors.phone" class="pqm-error"></p>
                    </div>

                    {{-- Ghi chu (optional) --}}
                    <div>
                        <label class="pqm-label" for="pqm_msg">
                            Ghi chú
                            <span class="pqm-optional">(không bắt buộc)</span>
                        </label>
                        <textarea
                            id="pqm_msg"
                            name="message"
                            x-model="formData.message"
                            rows="2"
                            class="pqm-input"
                            placeholder="Câu hỏi thêm, yêu cầu cụ thể..."
                        ></textarea>
                    </div>
                </div>

                {{-- Product chip --}}
                @if($product)
                <div class="pqm-product-chip">
                    @if($product->btu)
                    <span class="pqm-chip pqm-chip--blue">{{ number_format($product->btu) }} BTU</span>
                    @endif
                    @if($product->brand?->name)
                    <span class="pqm-chip pqm-chip--gray">{{ $product->brand->name }}</span>
                    @endif
                    @if($product->sku)
                    <span class="pqm-chip pqm-chip--gray">SKU: {{ $product->sku }}</span>
                    @endif
                </div>
                @endif

                {{-- Submit --}}
                <button type="submit" :disabled="sending" class="pqm-submit">
                    <template x-if="!sending">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Gửi yêu cầu báo giá
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

                <p class="pqm-note">
                    Miễn phí tư vấn &mdash; Phản hồi trong 30 phút
                </p>
            </form>

        </div>{{-- /pqm-body --}}
    </div>{{-- /pqm-panel --}}

</div>

@once
@push('head')
<style>
/* ═══ Modal root ═══════════════════════════════════════════ */
.pqm-root { position:relative; z-index:0; }

/* ═══ Backdrop ═════════════════════════════════════════════ */
.pqm-backdrop {
    position:fixed; inset:0; z-index:9998;
    background:rgba(0,0,0,.55);
    backdrop-filter:blur(2px);
}

/* ═══ Panel — Desktop centered ══════════════════════════════ */
.pqm-panel {
    position:fixed; z-index:9999;
    /* Desktop */
    top:50%; left:50%; transform:translate(-50%,-50%);
    width:min(560px, calc(100vw - 2rem));
    max-height:90vh;
    overflow-y:auto;
    border-radius:1.25rem;
    background:#fff;
    box-shadow:0 20px 60px -10px rgba(0,0,0,.35), 0 0 0 1px rgba(0,0,0,.06);
}

/* ═══ Mobile: bottom sheet ══════════════════════════════════ */
@media (max-width: 639px) {
    .pqm-panel {
        top:auto; left:0; right:0; bottom:0;
        transform:none;
        width:100%;
        max-height:92vh;
        border-radius:1.25rem 1.25rem 0 0;
    }
}

/* ═══ Header ════════════════════════════════════════════════ */
.pqm-header {
    display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem;
    padding:1.25rem 1.5rem 1rem;
    border-bottom:1px solid #f1f5f9;
    position:sticky; top:0; background:#fff; z-index:1;
    border-radius:1.25rem 1.25rem 0 0;
}
.pqm-title { margin:0; font-size:1.125rem; font-weight:700; color:#111827; }
.pqm-subtitle { margin:.2rem 0 0; font-size:.8125rem; color:#6b7280; line-height:1.4; }
.pqm-close {
    flex-shrink:0; display:flex; align-items:center; justify-content:center;
    width:2rem; height:2rem; border-radius:.5rem;
    border:none; background:transparent; cursor:pointer; color:#9ca3af;
    transition:background .15s, color .15s;
}
.pqm-close:hover { background:#f3f4f6; color:#374151; }

/* ═══ Body ══════════════════════════════════════════════════ */
.pqm-body { padding:1.25rem 1.5rem 1.5rem; }

/* ═══ Success ═══════════════════════════════════════════════ */
.pqm-success { text-align:center; padding:2.5rem 1rem; }
.pqm-success-icon { display:flex; justify-content:center; margin-bottom:.75rem; }
.pqm-success-title { font-size:1.125rem; font-weight:700; color:#166534; margin:0 0 .25rem; }
.pqm-success-sub { font-size:.875rem; color:#4b5563; margin:0; }

/* ═══ Fields ════════════════════════════════════════════════ */
.pqm-fields { display:flex; flex-direction:column; gap:.875rem; }
.pqm-label { display:block; margin-bottom:.3rem; font-size:.8125rem; font-weight:600; color:#374151; }
.pqm-optional { font-weight:400; color:#9ca3af; }
.pqm-input {
    display:block; width:100%;
    border-radius:.625rem; border:1.5px solid #e2e8f0;
    padding:.625rem .875rem; font-size:.9375rem;
    outline:none; transition:border-color .15s, box-shadow .15s;
    background:#fff; font-family:inherit;
}
.pqm-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.pqm-input--err  { border-color:#ef4444; }
.pqm-input--err:focus { box-shadow:0 0 0 3px rgba(239,68,68,.12); }
.pqm-error { margin:.25rem 0 0; font-size:.75rem; color:#dc2626; }

/* ═══ Product chip ══════════════════════════════════════════ */
.pqm-product-chip { display:flex; flex-wrap:wrap; gap:.375rem; margin-top:.875rem; }
.pqm-chip { display:inline-flex; align-items:center; border-radius:9999px; padding:.2rem .625rem; font-size:.75rem; font-weight:500; }
.pqm-chip--blue { background:#dbeafe; color:#1e40af; }
.pqm-chip--gray { background:#f1f5f9; color:#475569; }

/* ═══ Submit ════════════════════════════════════════════════ */
.pqm-submit {
    display:flex; width:100%; align-items:center; justify-content:center; gap:.5rem;
    margin-top:1.25rem; border-radius:.875rem;
    background:linear-gradient(135deg,#f97316,#ea580c);
    padding:.875rem 1.5rem; font-size:1rem; font-weight:700; color:#fff;
    border:none; cursor:pointer;
    box-shadow:0 2px 10px rgba(249,115,22,.35);
    transition:all .15s;
}
.pqm-submit:hover:not(:disabled) {
    box-shadow:0 4px 16px rgba(249,115,22,.45);
    transform:translateY(-1px);
}
.pqm-submit:disabled { opacity:.65; cursor:not-allowed; }
.pqm-note { margin:.75rem 0 0; text-align:center; font-size:.75rem; color:#9ca3af; }

/* ═══ Mobile bottom sheet drag handle ══════════════════════ */
@media (max-width:639px) {
    .pqm-header::before {
        content:'';
        position:absolute; top:.625rem; left:50%; transform:translateX(-50%);
        width:2.5rem; height:.25rem; border-radius:9999px; background:#d1d5db;
    }
    .pqm-header { padding-top:1.5rem; }
}
</style>
@endpush
@endonce
