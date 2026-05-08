@props([
    'testimonials',
    'title' => 'Khách Hàng Đánh Giá',
    'subtitle' => 'Những phản hồi chân thực từ các đối tác, nhà thầu và khách hàng đã sử dụng sản phẩm dịch vụ của chúng tôi.',
    'product' => null // Optional: For AggregateRating schema
])

@if($testimonials->isNotEmpty())
    <section class="border-t border-surface-200 bg-surface-50 py-12 lg:py-16">
        <div class="container-main">
            <div class="text-center">
                <x-section-heading :title="$title" class="justify-center" />
                @if($subtitle)
                    <p class="mx-auto mt-4 max-w-2xl text-surface-600 sm:text-lg">{{ $subtitle }}</p>
                @endif
            </div>
            
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($testimonials as $testimonial)
                    <x-testimonial-card :testimonial="$testimonial" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Review Schema for Product --}}
    @if($product && $testimonials->whereNotNull('rating')->count() > 0)
        @php
            $reviewCount = $testimonials->whereNotNull('rating')->count();
            $ratingValue = round($testimonials->whereNotNull('rating')->avg('rating'), 1);
        @endphp
        
        @push('schema')
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org/",
            "@@type": "Product",
            "name": "{{ e($product->name) }}",
            @if($product->main_image)
            "image": "{{ media_url($product->main_image) }}",
            @endif
            "aggregateRating": {
                "@@type": "AggregateRating",
                "ratingValue": "{{ $ratingValue }}",
                "reviewCount": "{{ $reviewCount }}",
                "bestRating": "5",
                "worstRating": "1"
            },
            "review": [
                @foreach($testimonials->whereNotNull('rating') as $review)
                {
                    "@@type": "Review",
                    "reviewRating": {
                        "@@type": "Rating",
                        "ratingValue": "{{ $review->rating }}",
                        "bestRating": "5",
                        "worstRating": "1"
                    },
                    "author": {
                        "@@type": "Person",
                        "name": "{{ e($review->customer_name) }}"
                    },
                    "reviewBody": "{{ e($review->content) }}"
                }{{ !$loop->last ? ',' : '' }}
                @endforeach
            ]
        }
        </script>
        @endpush
    @endif
@endif
