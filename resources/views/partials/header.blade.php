{{-- Header --}}
<header class="sticky top-0 z-40 border-b border-surface-200 bg-white/95 backdrop-blur-sm">
    {{-- Top Bar --}}
    <div class="hidden border-b border-surface-100 bg-surface-900 text-sm text-surface-300 lg:block">
        <div class="container-main flex items-center justify-between py-1.5">
            <div class="flex items-center gap-4">
                <a href="tel:{{ setting('contact.hotline', '') }}" class="flex items-center gap-1.5 transition-colors hover:text-white">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>Hotline: <strong class="text-accent-400">{{ setting('contact.hotline', 'Đang cập nhật') }}</strong></span>
                </a>
                <span class="text-surface-600">|</span>
                <span>{{ setting('general.working_hours', 'Tư vấn báo giá theo nhu cầu công trình') }}</span>
            </div>
            <div class="flex items-center gap-4">
                <x-policy-links display-location="header_top" variant="inline" />
                <a href="/bang-gia/dieu-hoa-tu-dung" class="transition-colors hover:text-white">Bảng giá</a>
                <a href="/faq/dieu-hoa-tu-dung" class="transition-colors hover:text-white">FAQ</a>
                <a href="/lien-he" class="transition-colors hover:text-white">Liên hệ</a>
            </div>
        </div>
    </div>

    {{-- Main Nav --}}
    <div class="container-main">
        <nav class="flex items-center justify-between py-3 lg:py-4" aria-label="Main navigation">
            {{-- Logo --}}
            <a href="/" class="flex items-center gap-2" aria-label="Trang chủ - {{ setting('general.site_name', '') }}">
                <x-site-logo context="header" theme="light" />
            </a>

            {{-- Desktop Menu --}}
            <div class="hidden items-center gap-1 lg:flex">
                <a href="/" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 hover:text-primary-600">Trang chủ</a>
                <a href="/dieu-hoa-tu-dung" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 hover:text-primary-600">Điều hòa tủ đứng</a>
                <a href="/bang-gia/dieu-hoa-tu-dung" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 hover:text-primary-600">Bảng giá</a>
                <a href="/blog" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 hover:text-primary-600">Blog</a>
                <a href="/faq/dieu-hoa-tu-dung" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 hover:text-primary-600">FAQ</a>
                <a href="/lien-he" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 hover:text-primary-600">Liên hệ</a>
            </div>

            {{-- CTA + Mobile Toggle --}}
            <div class="flex items-center gap-3">
                <a href="{{ setting('cta.global_cta_link', '/bao-gia') }}" class="btn-accent hidden text-sm lg:inline-flex">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ setting('cta.global_cta_text', 'Nhận báo giá') }}
                </a>

                {{-- Mobile hamburger --}}
                <button
                    type="button"
                    class="rounded-lg p-2 text-surface-600 transition-colors hover:bg-surface-100 lg:hidden"
                    aria-label="Mở menu"
                    onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </nav>

        {{-- Mobile Menu --}}
        <div id="mobile-menu" class="hidden border-t border-surface-200 pb-4 lg:hidden">
            <div class="flex flex-col gap-1 pt-3">
                <a href="/" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-100">Trang chủ</a>
                <a href="/dieu-hoa-tu-dung" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-100">Điều hòa tủ đứng</a>
                <a href="/bang-gia/dieu-hoa-tu-dung" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-100">Bảng giá</a>
                <a href="/blog" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-100">Blog</a>
                <a href="/faq/dieu-hoa-tu-dung" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-100">FAQ</a>
                <a href="/lien-he" class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-100">Liên hệ</a>
                <a href="{{ setting('cta.global_cta_link', '/bao-gia') }}" class="btn-accent mt-2 text-center text-sm">{{ setting('cta.global_cta_text', 'Nhận báo giá') }}</a>
            </div>
        </div>
    </div>
</header>
