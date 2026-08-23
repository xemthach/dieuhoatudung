<x-layouts.app
    :seoTitle="$seoTitle"
    :seoDescription="$seoDescription"
    :canonical="$canonical"
    ogType="website"
>

@push('schema')
@php
    $calculatorSchema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebPage',
                'name' => $seoTitle,
                'description' => $seoDescription,
                'url' => $canonical,
                'breadcrumb' => [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Công cụ chọn công suất'],
                    ],
                ],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => collect($calculatorFaqs)->map(fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ])->values()->all(),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($calculatorSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
</script>
@endpush

<div class="container-main py-8 lg:py-12">

    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-surface-500" aria-label="Breadcrumb">
        <a href="/" class="hover:text-primary-600">Trang chủ</a>
        <span>/</span>
        <span class="text-surface-700 font-medium">Công cụ tính BTU</span>
    </nav>

    {{-- Hero heading --}}
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-extrabold text-surface-900 sm:text-3xl lg:text-4xl">
            Công Cụ Chọn Công Suất Điều Hòa Tủ Đứng
        </h1>
        <p class="mx-auto mt-3 max-w-2xl text-base text-surface-600">
            Chọn tính theo diện tích hoặc thể tích để ước tính nhóm công suất BTU tham khảo và xem các sản phẩm RAC không thấp hơn nhu cầu đã tính.
        </p>
    </div>

    {{-- Calculator component --}}
    <x-btu-calculator
        :result="$result"
        :products="$products"
        :calc="$calc"
    />

    {{-- FAQ Section --}}
    <div class="mx-auto mt-12 max-w-3xl">
        <h2 class="mb-6 text-xl font-bold text-surface-900">Câu Hỏi Thường Gặp Về BTU Điều Hòa Tủ Đứng</h2>

        <div class="space-y-3">
            @foreach($calculatorFaqs as $faq)
            <details class="group rounded-xl border border-surface-200 bg-white">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-4 font-medium text-surface-800 hover:text-primary-600">
                    {{ $faq['question'] }}
                    <svg class="h-4 w-4 flex-shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="border-t border-surface-100 px-4 pb-4 pt-3 text-sm text-surface-600 leading-relaxed">
                    {{ $faq['answer'] }}
                </div>
            </details>
            @endforeach
        </div>
    </div>

    {{-- Internal links --}}
    <div class="mx-auto mt-10 max-w-3xl">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <a href="/dieu-hoa-tu-dung"
                class="flex items-center gap-3 rounded-xl border border-surface-200 bg-white p-4 transition-all hover:border-primary-300 hover:shadow-sm">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <div class="text-sm font-semibold text-surface-800">Xem điều hòa tủ đứng</div>
                    <div class="text-xs text-surface-500">Toàn bộ danh mục sản phẩm</div>
                </div>
            </a>
            <a href="/bang-gia/dieu-hoa-tu-dung"
                class="flex items-center gap-3 rounded-xl border border-surface-200 bg-white p-4 transition-all hover:border-primary-300 hover:shadow-sm">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-accent-100 text-accent-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <div class="text-sm font-semibold text-surface-800">Bảng giá điều hòa</div>
                    <div class="text-xs text-surface-500">So sánh giá theo BTU</div>
                </div>
            </a>
        </div>
    </div>

</div>

@push('scripts')
<script>
// Auto scroll to result after form submit
document.addEventListener('DOMContentLoaded', function() {
    const result = document.getElementById('btu-result');
    if (result) {
        setTimeout(() => result.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200);
    }
});
</script>
@endpush

</x-layouts.app>
