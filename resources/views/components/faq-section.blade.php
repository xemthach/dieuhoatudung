@props([
    'faqs',
    'title' => 'Câu Hỏi Thường Gặp',
    'entity' => null, // Used for schema, optional
    'skipSchema' => false, // Skip schema generation when parent handles it
])

@if($faqs->isNotEmpty())
    <section class="border-t border-surface-200 bg-surface-50 py-8 lg:py-12">
        <div class="container-main">
            <x-section-heading :title="$title" />
            <div class="mx-auto mt-6 max-w-3xl space-y-3">
                @foreach($faqs as $index => $faq)
                    <x-faq-item :faq="$faq" :open="$index === 0" />
                @endforeach
            </div>
        </div>
    </section>

    @unless($skipSchema)
    @push('schema')
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "FAQPage",
            "mainEntity": [
                @foreach($faqs as $index => $faq)
                {
                    "@@type": "Question",
                    "name": "{{ e($faq->question) }}",
                    "acceptedAnswer": {
                        "@@type": "Answer",
                        "text": "{{ e(strip_tags($faq->answer)) }}"
                    }
                }{{ !$loop->last ? ',' : '' }}
                @endforeach
            ]
        }
        </script>
    @endpush
    @endunless
@endif
