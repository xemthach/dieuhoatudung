@if($faqs->isNotEmpty())
    <section class="border-t border-surface-200 bg-surface-50 py-12 lg:py-16">
        <div class="container-main">
            <x-section-heading
                title="Câu Hỏi Thường Gặp"
                subtitle="Những thắc mắc phổ biến về điều hòa"
            />
            <div class="mx-auto mt-8 max-w-3xl space-y-3">
                @foreach($faqs as $index => $faq)
                    <x-faq-item :faq="$faq" :open="$index === 0" />
                @endforeach
            </div>
        </div>
    </section>
    @endif
