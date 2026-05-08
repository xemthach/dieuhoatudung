{{-- FAQ Section --}}
@if(isset($faqs) && $faqs->isNotEmpty())
<section class="border-t border-surface-200 bg-surface-50 py-12 lg:py-16" id="landing-faq">
    <div class="container-main">
        <x-section-heading
            :title="$section->title ?? 'Câu Hỏi Thường Gặp'"
            :subtitle="$section->subtitle ?? ''"
        />
        <div class="mx-auto mt-8 max-w-3xl space-y-3">
            @foreach($faqs as $index => $faq)
                <x-faq-item :faq="$faq" :open="$index === 0" />
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- FAQPage Schema.org --}}
@if(isset($faqs) && $faqs->isNotEmpty())
@push('schema')
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "FAQPage",
    "mainEntity": [
        @foreach($faqs as $faq)
        {
            "@@type": "Question",
            "name": "{!! e($faq->question) !!}",
            "acceptedAnswer": {
                "@@type": "Answer",
                "text": "{!! e($faq->answer) !!}"
            }
        }@if(!$loop->last),@endif
        @endforeach
    ]
}
</script>
@endpush
@endif
