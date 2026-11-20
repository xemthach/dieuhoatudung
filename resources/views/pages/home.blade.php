<x-layouts.app
    :seo-title="setting('seo.default_seo_title', setting('general.site_name', '') . ' - Điều Hòa Tủ Đứng')"
    :seo-description="setting('seo.default_meta_description', 'Danh sách điều hòa tủ đứng, thông số kỹ thuật và tư vấn lựa chọn theo nhu cầu công trình.')"
    :canonical="config('app.url')"
>
    {{-- Search remains in the page header area; content sections follow the saved order. --}}
    <x-home.homepage-search />

    @foreach($homepageSectionOrder as $homepageSection)
        @if($homepageSection !== 'dealers' || $homepageDealersEnabled)
            <div data-homepage-section="{{ $homepageSection }}">@includeIf('pages.home-sections.' . $homepageSection)</div>
        @endif
    @endforeach

{{-- Organization + WebSite Schema (homepage only) --}}
    @push('schema')
    @php
        $schemaService = app(\App\Services\Schema\SchemaService::class);
        $orgSchemas = $schemaService->organizationAndWebsite();
    @endphp
    @foreach($orgSchemas as $orgSchema)
    {!! \App\Services\Schema\SchemaService::toScript($orgSchema) !!}
    @endforeach
    @if($faqs->isNotEmpty())
    {!! \App\Services\Schema\SchemaService::toScript($schemaService->faqPage($faqs)) !!}
    @endif
    @endpush


</x-layouts.app>
