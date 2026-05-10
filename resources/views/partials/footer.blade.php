{{-- Footer --}}
<footer class="mt-16 border-t border-surface-200 bg-surface-900 text-surface-300">
    <div class="container-main py-12 lg:py-16">
        <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4">

            {{-- Company Info --}}
            <div>
                <a href="/" class="mb-4 flex items-center gap-2">
                    <x-site-logo context="footer" theme="dark" />
                </a>
                @if(setting('branding.footer_show_slogan', true))
                <p class="mb-4 text-sm leading-relaxed">
                    {{ setting('general.site_slogan', '') }}
                </p>
                @endif
                <div class="flex items-center gap-3">
                    @if(setting('contact.facebook_link', '#') !== '#')
                    <a href="{{ setting('contact.facebook_link', '#') }}" target="_blank" rel="noopener" class="flex h-9 w-9 items-center justify-center rounded-full bg-surface-800 text-surface-400 transition-colors hover:bg-primary-600 hover:text-white" aria-label="Facebook">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    @endif
                    @if(setting('contact.youtube_link', '#') !== '#')
                    <a href="{{ setting('contact.youtube_link', '#') }}" target="_blank" rel="noopener" class="flex h-9 w-9 items-center justify-center rounded-full bg-surface-800 text-surface-400 transition-colors hover:bg-primary-600 hover:text-white" aria-label="YouTube">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Quick Links --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">Sản phẩm</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/dieu-hoa-tu-dung" class="transition-colors hover:text-white">Điều hòa tủ đứng</a></li>
                    <li><a href="/bang-gia/dieu-hoa-tu-dung" class="transition-colors hover:text-white">Bảng giá</a></li>
                    <li><a href="/blog" class="transition-colors hover:text-white">Kiến thức</a></li>
                    <li><a href="/faq/dieu-hoa-tu-dung" class="transition-colors hover:text-white">FAQ</a></li>
                </ul>
            </div>

            {{-- Policies --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">Chính sách</h3>
                <ul class="space-y-2 text-sm">
                    <x-policy-links display-location="footer" variant="list" />
                </ul>
            </div>

            {{-- Contact --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">Liên hệ</h3>
                <ul class="space-y-3 text-sm">
                    @if(setting('contact.contact_address'))
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>{{ setting('contact.contact_address') }}</span>
                    </li>
                    @endif
                    @if(setting('contact.hotline'))
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <a href="tel:{{ setting('contact.hotline') }}" class="transition-colors hover:text-white">{{ setting('contact.hotline') }}</a>
                    </li>
                    @endif
                    @if(setting('contact.email'))
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <a href="mailto:{{ setting('contact.email') }}" class="transition-colors hover:text-white">{{ setting('contact.email') }}</a>
                    </li>
                    @endif
                </ul>
            </div>

        </div>
    </div>

    {{-- Bottom Bar --}}
    <div class="border-t border-surface-800">
        <div class="container-main flex flex-col items-center justify-between gap-2 py-4 text-xs text-surface-500 sm:flex-row">
            <p>&copy; {{ date('Y') }} {{ setting('general.company_name', setting('general.site_name', '')) }}. Tất cả quyền được bảo lưu.</p>
            <p>{{ setting('general.site_slogan', '') }}</p>
            <p>v{{ trim(file_get_contents(base_path('VERSION'))) }}</p>
        </div>
    </div>
</footer>

