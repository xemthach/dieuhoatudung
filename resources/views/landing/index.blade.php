<x-layouts.app
    :seo-title="'Điều Hòa Tủ Đứng 2026 - Tư Vấn Theo Công Trình'"
    :seo-description="'Danh sách điều hòa tủ đứng Daikin, LG, Panasonic, Gree. Xem sản phẩm và gửi yêu cầu để được tư vấn theo nhu cầu công trình.'"
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
        "name": "Điều Hòa Tủ Đứng",
        "description": "Danh sách điều hòa tủ đứng Daikin, LG, Panasonic, Gree. Tư vấn theo nhu cầu công trình và dữ liệu sản phẩm đã công bố.",
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
