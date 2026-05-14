{{-- Policies Section --}}
<section class="border-t border-surface-200 bg-white py-12 lg:py-16" id="landing-policies">
    <div class="container-main">
        <x-section-heading
            :title="$section->title ?? 'Cam Kết Dịch Vụ'"
            :subtitle="$section->subtitle ?? ''"
        />

        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-surface-200 bg-surface-50 p-6 text-center transition-all hover:shadow-md">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-surface-900">Chính Sách Bảo Hành</h3>
                <p class="mt-2 text-xs leading-relaxed text-surface-500">Thông tin bảo hành được đối chiếu theo từng sản phẩm, hãng hoặc chính sách admin đã công bố.</p>
            </div>
            <div class="rounded-xl border border-surface-200 bg-surface-50 p-6 text-center transition-all hover:shadow-md">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-accent-100 text-accent-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-surface-900">Tư Vấn Lắp Đặt</h3>
                <p class="mt-2 text-xs leading-relaxed text-surface-500">Phương án lắp đặt cần kiểm tra theo vị trí máy, đường ống, nguồn điện và điều kiện công trình.</p>
            </div>
            <div class="rounded-xl border border-surface-200 bg-surface-50 p-6 text-center transition-all hover:shadow-md">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-success-500/10 text-success-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-surface-900">Tư Vấn Chuyên Nghiệp</h3>
                <p class="mt-2 text-xs leading-relaxed text-surface-500">Tư vấn lựa chọn model dựa trên dữ liệu sản phẩm, nhu cầu sử dụng và điều kiện tải nhiệt thực tế.</p>
            </div>
            <div class="rounded-xl border border-surface-200 bg-surface-50 p-6 text-center transition-all hover:shadow-md">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-warning-500/10 text-warning-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <h3 class="text-sm font-bold text-surface-900">Đổi Trả Linh Hoạt</h3>
                <p class="mt-2 text-xs leading-relaxed text-surface-500">Chính sách đổi trả trong 7 ngày nếu sản phẩm lỗi. Hoàn tiền nhanh chóng.</p>
            </div>
        </div>

        {{-- Dynamic policies from admin --}}
        @if(isset($policies) && $policies->isNotEmpty())
        <div class="mx-auto mt-8 max-w-3xl space-y-4">
            @foreach($policies as $policy)
                <details class="group rounded-xl border border-surface-200 bg-white">
                    <summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-surface-800">
                        {{ $policy->title }}
                        <svg class="h-5 w-5 text-surface-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="border-t border-surface-100 px-5 py-4 text-sm leading-relaxed text-surface-600">
                        {!! $policy->content !!}
                    </div>
                </details>
            @endforeach
        </div>
        @endif
    </div>
</section>
