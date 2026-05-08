{{-- Hero Section for Landing Page --}}
<section class="relative overflow-hidden bg-gradient-to-br from-primary-900 via-primary-800 to-surface-900" id="landing-hero">
    <div class="absolute inset-0">
        <div class="absolute -right-32 -top-32 h-[500px] w-[500px] rounded-full bg-primary-400/10 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-accent-400/10 blur-3xl"></div>
        <div class="absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5 blur-3xl"></div>
    </div>

    <div class="container-main relative py-20 lg:py-32">
        <div class="mx-auto max-w-4xl text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">
                {{ $section->title ?? 'Điều Hòa Tủ Đứng Chính Hãng' }}
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-primary-100 sm:text-xl">
                {{ $section->subtitle ?? 'Giải pháp làm mát chuyên nghiệp cho không gian lớn. Đa dạng thương hiệu, công suất phù hợp mọi nhu cầu.' }}
            </p>

            {{-- Trust indicators --}}
            <div class="mt-8 flex flex-wrap items-center justify-center gap-6 text-sm text-primary-200">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Chính hãng 100%
                </span>
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Miễn phí lắp đặt
                </span>
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Bảo hành 3-5 năm
                </span>
            </div>

            {{-- CTA --}}
            <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="{{ route('quote.index') }}" class="btn-accent px-10 py-4 text-base shadow-lg shadow-accent-500/25 transition-shadow hover:shadow-xl hover:shadow-accent-500/30">
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Nhận báo giá miễn phí
                </a>
                <a href="#landing-products" class="btn-outline border-white/30 px-10 py-4 text-base text-white hover:bg-white/10">
                    Xem sản phẩm
                </a>
            </div>

            {{-- Brand logos --}}
            @if(isset($brands) && $brands->isNotEmpty())
            <div class="mt-12 border-t border-white/10 pt-8">
                <p class="mb-4 text-xs uppercase tracking-widest text-primary-300">Đại lý ủy quyền chính hãng</p>
                <div class="flex flex-wrap items-center justify-center gap-8">
                    @foreach($brands as $brand)
                        <span class="text-sm font-semibold text-white/60 transition-colors hover:text-white">{{ $brand->name }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
