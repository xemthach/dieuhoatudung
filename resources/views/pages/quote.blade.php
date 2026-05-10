<x-layouts.app
    :seoTitle="$seoTitle"
    :seoDescription="$seoDescription"
    :canonical="$canonical"
    ogType="website"
>

@push('schema')
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "BreadcrumbList",
    "itemListElement": [
        {"@@type": "ListItem", "position": 1, "name": "Trang chủ", "item": "{{ url('/') }}"},
        {"@@type": "ListItem", "position": 2, "name": "Báo giá"}
    ]
}
</script>
@endpush

<div class="container-main py-8 lg:py-12">

    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-surface-500" aria-label="Breadcrumb">
        <a href="/" class="hover:text-primary-600">Trang chủ</a>
        <span>/</span>
        <span class="text-surface-700 font-medium">Báo giá</span>
    </nav>

    @if($thanks)
    {{-- ══════════════════════════════════
         THANK YOU STATE
         ══════════════════════════════════ --}}
    <div class="mx-auto max-w-2xl text-center">
        <div class="mb-6 inline-flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-green-600">
            <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="mb-3 text-2xl font-extrabold text-surface-900">
            {{ setting('lead.lead_success_message', 'Yêu cầu báo giá của bạn đã được ghi nhận!') }}
        </h1>

        <p class="mb-6 text-surface-600">
            Xin chào <strong>{{ $thanks['full_name'] }}</strong>! Chúng tôi sẽ liên hệ lại trong <strong>1–2 giờ làm việc</strong>.
            Nếu cần hỗ trợ ngay, gọi hotline:
        </p>

        {{-- Hotline từ setting --}}
        @if(setting('contact.hotline'))
        <a href="tel:{{ setting('contact.hotline') }}"
            class="mb-8 inline-flex items-center gap-3 rounded-2xl bg-primary-600 px-8 py-4 text-xl font-bold text-white shadow-lg transition hover:bg-primary-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            {{ setting('contact.hotline') }}
        </a>
        @endif

        {{-- Tham khảo nhanh BTU --}}
        @if($thanks['recommended_btu'] ?? null)
        <div class="mb-8 rounded-xl border border-primary-200 bg-primary-50 p-4 text-left">
            <p class="text-sm font-semibold text-primary-800">
                Dựa trên diện tích bạn nhập, chúng tôi ước tính cần công suất:
                <strong>{{ number_format($thanks['recommended_btu']) }} BTU</strong>
            </p>
        </div>
        @endif

        {{-- Gợi ý sản phẩm --}}
        @if(! empty($thanks['suggested_products']) && count($thanks['suggested_products']) > 0)
        <div class="mt-6 text-left">
            <h2 class="mb-4 text-lg font-bold text-surface-800">Sản phẩm có thể phù hợp</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 md:grid-cols-4">
                @foreach($thanks['suggested_products'] as $p)
                <a href="{{ route('product.show', $p['slug']) }}"
                    class="group overflow-hidden rounded-xl border border-surface-200 bg-white transition hover:border-primary-300 hover:shadow-md">
                    <div class="aspect-square overflow-hidden bg-surface-100">
                        <img
                            src="{{ !empty($p['main_image']) ? media_url($p['main_image']) : (setting('product_detail.default_product_image') ? media_url(setting('product_detail.default_product_image')) : asset('images/placeholders/product-default.jpg')) }}"
                            alt="{{ $p['name'] }}"
                            class="h-full w-full object-contain p-2 transition-transform group-hover:scale-105"
                            loading="lazy">
                    </div>
                    <div class="p-3">
                        @if(! empty($p['btu']))
                        <div class="mb-1 inline-block rounded bg-primary-100 px-2 py-0.5 text-[10px] font-bold text-primary-700">
                            {{ number_format($p['btu']) }} BTU
                        </div>
                        @endif
                        <p class="text-xs font-semibold text-surface-800 line-clamp-2 group-hover:text-primary-700">{{ $p['name'] }}</p>
                        @if(! empty($p['sale_price']))
                        <p class="mt-1 text-sm font-bold text-primary-700">{{ number_format($p['sale_price'], 0, ',', '.') }}đ</p>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif


        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="/" class="btn-ghost">← Về trang chủ</a>
            <a href="/dieu-hoa-tu-dung" class="btn-primary">Xem sản phẩm</a>
        </div>
    </div>

    {{-- Conversion Tracking --}}
    @push('scripts')
    <script>
    window.dataLayer = window.dataLayer || [];
    dataLayer.push({
        'event': 'submit_quote_form',
        'lead_type': '{{ $thanks["lead_type"] ?? "general" }}',
        'product_name': '{{ e($thanks["product_name"] ?? "") }}',
        'product_id': '{{ $thanks["product_id"] ?? "" }}',
        'quote_id': '{{ $thanks["quote_id"] ?? "" }}',
        'intent_score': {{ $thanks["intent_score"] ?? 40 }}
    });

    @if(setting('tracking.google_ads_conversion_id') && setting('tracking.google_ads_quote_label'))
    if (typeof gtag === 'function') {
        gtag('event', 'conversion', {
            'send_to': '{{ setting("tracking.google_ads_conversion_id") }}/{{ setting("tracking.google_ads_quote_label") }}',
            'value': 1.0,
            'currency': 'VND'
        });
    }
    @endif
    </script>
    @endpush

    @else
    {{-- ══════════════════════════════════
         FORM STATE
         ══════════════════════════════════ --}}
    <div class="grid gap-8 lg:grid-cols-3">

        {{-- Left: Form --}}
        <div class="lg:col-span-2">
            <div class="mb-6">
                <h1 class="text-2xl font-extrabold text-surface-900 sm:text-3xl">
                    {{ setting('cta.quote_cta_text', 'Nhận báo giá') }} Điều Hòa Tủ Đứng
                </h1>
                <p class="mt-2 text-surface-600">Điền thông tin để nhận báo giá chính xác và tư vấn miễn phí.</p>
            </div>

            <div class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-lg">
                <div class="bg-gradient-to-r from-primary-600 to-primary-800 px-6 py-4 text-white">
                    <p class="text-sm text-primary-100">Quy trình 5 bước — chỉ mất 2 phút</p>
                </div>
                <div class="p-6">
                    <x-quote-form :product="$product" />
                </div>
            </div>
        </div>

        {{-- Right: Sidebar info --}}
        <div class="space-y-5">
            {{-- Contact box --}}
            <div class="rounded-2xl border border-primary-200 bg-primary-50 p-5">
                <h3 class="mb-3 font-bold text-primary-800">Cần hỗ trợ ngay?</h3>
                @if(setting('contact.hotline'))
                <a href="tel:{{ setting('contact.hotline') }}"
                    class="flex items-center gap-2 text-xl font-bold text-primary-700 hover:text-primary-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    {{ setting('contact.hotline') }}
                </a>
                @endif
                @if(setting('general.working_hours'))
                <p class="mt-2 text-sm text-primary-600">{{ setting('general.working_hours') }}</p>
                @endif
            </div>

            {{-- BTU tool link --}}
            <div class="rounded-2xl border border-surface-200 bg-white p-5">
                <h3 class="mb-2 font-bold text-surface-800">Chưa biết cần bao nhiêu BTU?</h3>
                <p class="mb-3 text-sm text-surface-600">Dùng công cụ tính công suất miễn phí của chúng tôi.</p>
                <a href="{{ route('btu-calculator.index') }}" class="btn-primary inline-block text-sm">
                    Tính BTU ngay →
                </a>
            </div>

            {{-- Commitment block --}}
            <x-quote.commitment-block />
        </div>

    </div>
    @endif

</div>



</x-layouts.app>
