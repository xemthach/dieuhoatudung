<x-layouts.app seo-title="Dự Án: {{ $caseStudy->title }}" seo-description="{{ Str::limit(strip_tags($caseStudy->content), 150) }}">
    <div class="container-main py-12">
        <h1 class="text-3xl font-bold mb-6">{{ $caseStudy->title }}</h1>
        <div class="prose max-w-none">
            {!! $caseStudy->content !!}
        </div>
    </div>
</x-layouts.app>
