<x-layouts.app
    :seo-title="'Liên Hệ' . config('seo.defaults.title_suffix')"
    :seo-description="'Liên hệ với chúng tôi để được tư vấn về điều hòa tủ đứng. Hotline hỗ trợ 24/7.'"
    :canonical="route('contact')"
>
    <x-breadcrumb :items="[['label' => 'Liên hệ']]" />

    <section class="py-12 lg:py-16">
        <div class="container-main">
            <div class="mx-auto max-w-2xl text-center">
                <h1 class="text-3xl font-bold text-surface-900">Liên Hệ Với Chúng Tôi</h1>
                <p class="mt-3 text-surface-500">Đội ngũ tư vấn sẵn sàng hỗ trợ bạn lựa chọn giải pháp điều hòa tủ đứng phù hợp nhất.</p>
            </div>

            <div class="mx-auto mt-10 grid max-w-4xl grid-cols-1 gap-8 sm:grid-cols-3">
                <div class="rounded-xl border border-surface-200 bg-white p-6 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-primary-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <h3 class="font-semibold text-surface-900">Hotline</h3>
                    <p class="mt-2 text-sm text-surface-500">Gọi ngay để được tư vấn</p>
                    <a href="tel:{{ setting('contact.hotline') }}" class="mt-2 inline-block font-bold text-primary-600">{{ setting('contact.hotline', 'Cập nhật SĐT') }}</a>
                </div>
                <div class="rounded-xl border border-surface-200 bg-white p-6 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-accent-100 text-accent-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="font-semibold text-surface-900">Email</h3>
                    <p class="mt-2 text-sm text-surface-500">Gửi yêu cầu qua email</p>
                    <a href="mailto:{{ setting('contact.email') }}" class="mt-2 inline-block font-bold text-primary-600">{{ setting('contact.email', 'Cập nhật Email') }}</a>
                </div>
                <div class="rounded-xl border border-surface-200 bg-white p-6 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-success-500/10 text-success-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-surface-900">Showroom</h3>
                    <p class="mt-2 text-sm text-surface-500">Ghé thăm showroom</p>
                    <p class="mt-2 text-sm font-semibold text-surface-700">{{ setting('contact.contact_address', 'Hồ Chí Minh, Việt Nam') }}</p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
