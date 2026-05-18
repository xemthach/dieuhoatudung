<x-layouts.app seo-title="Câu Hỏi Thường Gặp Về Điều Hòa Tủ Đứng">
    <div class="container-main py-12">
        <h1 class="text-3xl font-bold mb-8">Câu Hỏi Thường Gặp</h1>
        <div class="space-y-4 max-w-3xl">
            @foreach($faqs as $index => $faq)
                <x-faq-item :faq="$faq" :open="$index === 0" />
            @endforeach
        </div>
    </div>

    @push('schema')
        {!! \App\Services\Schema\SchemaService::toScript(app(\App\Services\Schema\SchemaService::class)->faqPage($faqs)) !!}
    @endpush
</x-layouts.app>
