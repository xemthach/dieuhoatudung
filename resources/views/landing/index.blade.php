<x-layouts.app
    :seo-title="'Điều Hòa Tủ Đứng Chính Hãng 2026 - Giá Tốt, Miễn Phí Lắp Đặt'"
    :seo-description="'Mua điều hòa tủ đứng chính hãng Daikin, LG, Panasonic, Gree. Công suất 24.000 - 100.000 BTU. Miễn phí lắp đặt, bảo hành toàn quốc, giá tốt nhất thị trường.'"
    :canonical="route('landing')"
    og-type="website"
>
    @foreach($sections as $section)
        @if($section->is_active)
            @include('landing.sections.' . $section->section_type->value, [
                'section' => $section,
            ])
        @endif
    @endforeach
    
    <x-testimonial-section :testimonials="$featuredTestimonials" />

    @push('schema')
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "WebPage",
        "name": "Điều Hòa Tủ Đứng Chính Hãng",
        "description": "Chuyên cung cấp điều hòa tủ đứng chính hãng Daikin, LG, Panasonic, Gree. Giá tốt nhất, miễn phí lắp đặt.",
        "url": "{{ route('landing') }}",
        "isPartOf": {
            "@@type": "WebSite",
            "name": "{{ config('seo.og.site_name') }}",
            "url": "{{ config('app.url') }}"
        }
    }
    </script>
    @endpush

</x-layouts.app>
