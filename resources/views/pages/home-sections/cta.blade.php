<section class="bg-gradient-to-r from-primary-700 to-primary-900 py-12 lg:py-16">
        <div class="container-main text-center">
            <h2 class="text-2xl font-bold text-white sm:text-3xl">Cần tư vấn điều hòa tủ đứng?</h2>
            <p class="mx-auto mt-3 max-w-2xl text-primary-100">
                Đội ngũ chuyên gia HVAC sẵn sàng hỗ trợ bạn chọn model phù hợp với không gian và ngân sách.
            </p>
            <div class="mt-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="{{ setting('cta.quote_cta_link', route('quote.index')) }}" class="btn-accent px-8 py-4 text-base">
                    {{ setting('cta.global_cta_text', 'Nhận báo giá ngay') }}
                </a>
                @if(setting('contact.hotline'))
                <a href="tel:{{ setting('contact.hotline') }}" class="inline-flex items-center gap-2 text-white transition-colors hover:text-accent-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span class="text-lg font-semibold">{{ setting('cta.phone_cta_text', 'Gọi hotline tư vấn') }}</span>
                </a>
                @endif
            </div>
        </div>
    </section>
