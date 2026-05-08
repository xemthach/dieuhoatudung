<x-layouts.app :seo-title="$seoTitle" :seo-description="$seoDescription" canonical="{{ $canonical }}">
    
    @push('schema')
        @php
            $schemaImage = $caseStudy->cover_image
                ? media_url($caseStudy->cover_image)
                : asset('images/og-default.jpg');
            $schemaDate  = $caseStudy->published_at
                ? $caseStudy->published_at->toIso8601String()
                : $caseStudy->created_at->toIso8601String();
            $schema = [
                '@context'      => 'https://schema.org',
                '@type'         => 'Article',
                'headline'      => $caseStudy->title,
                'image'         => [$schemaImage],
                'datePublished' => $schemaDate,
                'author'        => [
                    '@type' => 'Organization',
                    'name'  => setting('general.site_name', config('app.name')),
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
    @endpush

    <x-breadcrumb :items="[
        ['label' => 'Dự án thực tế', 'url' => route('case-studies.index')],
        ['label' => $caseStudy->title]
    ]" />

    <div class="bg-white pb-12 sm:pb-16 lg:pb-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-12 lg:gap-12">
                {{-- Main Content --}}
                <div class="lg:col-span-8 mt-8 lg:mt-0">
                    <h1 class="text-2xl font-bold tracking-tight text-surface-900 sm:text-3xl lg:text-4xl mb-6">
                        {{ $caseStudy->title }}
                    </h1>
                    
                    @if($caseStudy->cover_image)
                        <div class="mb-10 overflow-hidden rounded-2xl bg-surface-100">
                            <img src="{{ media_url($caseStudy->cover_image) }}" alt="{{ $caseStudy->title }}" class="w-full object-cover">
                        </div>
                    @endif

                    <div class="prose prose-primary max-w-none">
                        @if($caseStudy->problem)
                            <h2>Bài toán đặt ra</h2>
                            {!! $caseStudy->problem !!}
                        @endif

                        @if($caseStudy->solution)
                            <h2>Giải pháp thi công</h2>
                            {!! $caseStudy->solution !!}
                        @endif

                        @if($caseStudy->result)
                            <h2>Kết quả nghiệm thu</h2>
                            {!! $caseStudy->result !!}
                        @endif
                    </div>

                    {{-- Gallery --}}
                    @if($caseStudy->gallery_json && count($caseStudy->gallery_json) > 0)
                        <div class="mt-12">
                            <h3 class="text-2xl font-bold text-surface-900 mb-6">Hình ảnh thi công thực tế</h3>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:gap-6">
                                @foreach($caseStudy->gallery_json as $image)
                                    <a href="{{ media_url($image) }}" target="_blank" class="block overflow-hidden rounded-xl bg-surface-100">
                                        <img src="{{ media_url($image) }}" loading="lazy" class="h-48 w-full object-cover transition-transform hover:scale-105" alt="Gallery image">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Testimonial --}}
                    @if($caseStudy->testimonial)
                        <div class="mt-12 rounded-2xl bg-primary-50 p-6 sm:p-8">
                            <figure>
                                <svg class="h-10 w-10 text-primary-400 mb-4" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true">
                                    <path d="M9.352 4C4.456 7.456 1 13.12 1 19.36c0 5.088 3.072 8.064 6.624 8.064 3.36 0 5.856-2.688 5.856-5.856 0-3.168-2.208-5.472-5.088-5.472-.576 0-1.344.096-1.536.192.48-3.264 3.552-7.104 6.624-9.024L9.352 4zm16.512 0c-4.8 3.456-8.256 9.12-8.256 15.36 0 5.088 3.072 8.064 6.624 8.064 3.264 0 5.856-2.688 5.856-5.856 0-3.168-2.304-5.472-5.184-5.472-.576 0-1.248.096-1.44.192.48-3.264 3.456-7.104 6.528-9.024L25.864 4z" />
                                </svg>
                                <blockquote class="text-lg font-medium italic text-surface-900">
                                    <p>{{ $caseStudy->testimonial }}</p>
                                </blockquote>
                                @if($caseStudy->client_name)
                                    <figcaption class="mt-4 text-sm font-bold text-primary-600">
                                        &mdash; {{ $caseStudy->client_name }}
                                    </figcaption>
                                @endif
                            </figure>
                        </div>
                    @endif
                </div>

                {{-- Sidebar: Project Summary & CTA --}}
                <div class="lg:col-span-4 mt-12 lg:mt-0">
                    <div class="sticky top-28 space-y-8">
                        {{-- Summary Box --}}
                        <div class="rounded-2xl border border-surface-200 bg-surface-50 p-6">
                            <h3 class="text-lg font-bold text-surface-900 mb-4 border-b border-surface-200 pb-4">Thông tin dự án</h3>
                            <dl class="space-y-4 text-sm">
                                @if($caseStudy->project_type)
                                    <div class="flex justify-between">
                                        <dt class="text-surface-500">Loại công trình:</dt>
                                        <dd class="font-medium text-surface-900 text-right">{{ $caseStudy->project_type }}</dd>
                                    </div>
                                @endif
                                @if($caseStudy->location)
                                    <div class="flex justify-between">
                                        <dt class="text-surface-500">Địa điểm:</dt>
                                        <dd class="font-medium text-surface-900 text-right">{{ $caseStudy->location }}</dd>
                                    </div>
                                @endif
                                @if($caseStudy->area_m2)
                                    <div class="flex justify-between">
                                        <dt class="text-surface-500">Diện tích:</dt>
                                        <dd class="font-medium text-surface-900 text-right">{{ $caseStudy->area_m2 }}</dd>
                                    </div>
                                @endif
                                @if($caseStudy->ceiling_height)
                                    <div class="flex justify-between">
                                        <dt class="text-surface-500">Trần cao:</dt>
                                        <dd class="font-medium text-surface-900 text-right">{{ $caseStudy->ceiling_height }}</dd>
                                    </div>
                                @endif
                                @if($caseStudy->total_units)
                                    <div class="flex justify-between">
                                        <dt class="text-surface-500">Số lượng máy:</dt>
                                        <dd class="font-medium text-surface-900 text-right">{{ $caseStudy->total_units }} bộ</dd>
                                    </div>
                                @endif
                                @if($caseStudy->installation_time)
                                    <div class="flex justify-between">
                                        <dt class="text-surface-500">TG thi công:</dt>
                                        <dd class="font-medium text-surface-900 text-right">{{ $caseStudy->installation_time }}</dd>
                                    </div>
                                @endif
                                @if($caseStudy->completion_date)
                                    <div class="flex justify-between">
                                        <dt class="text-surface-500">Nghiệm thu:</dt>
                                        <dd class="font-medium text-surface-900 text-right">{{ $caseStudy->completion_date->format('m/Y') }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>

                        {{-- Product Featured --}}
                        @if($caseStudy->product)
                            <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                                <h3 class="text-lg font-bold text-surface-900 mb-4">Sản phẩm sử dụng</h3>
                                <a href="{{ route('product.show', $caseStudy->product->slug) }}" class="group block">
                                    <div class="aspect-square w-full overflow-hidden rounded-xl bg-surface-100 mb-4">
                                        <img
                                            src="{{ $caseStudy->product->card_image_url }}"
                                            alt="{{ $caseStudy->product->name }}"
                                            class="h-full w-full object-contain mix-blend-multiply transition-transform group-hover:scale-105"
                                            loading="lazy"
                                        >
                                    </div>
                                    <h4 class="font-medium text-surface-900 group-hover:text-primary-600 line-clamp-2">{{ $caseStudy->product->name }}</h4>
                                    <div class="mt-2 text-primary-600 font-bold">
                                        @php
                                            $displayPrice = $caseStudy->product->sale_price ?? $caseStudy->product->regular_price;
                                        @endphp
                                        {{ $displayPrice ? number_format($displayPrice, 0, ',', '.') . 'đ' : 'Liên hệ' }}
                                    </div>
                                </a>
                            </div>
                        @endif

                        {{-- CTA Box --}}
                        <div class="rounded-2xl bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-center text-white shadow-lg">
                            <h3 class="text-xl font-bold mb-2">Cần tư vấn giải pháp?</h3>
                            <p class="text-primary-100 text-sm mb-6">Liên hệ ngay để được kỹ sư khảo sát và lên phương án thi công tối ưu nhất cho công trình của bạn.</p>
                            <a href="tel:{{ setting('contact.hotline') }}" class="block w-full rounded-xl bg-white px-4 py-3 text-sm font-bold text-primary-700 transition-colors hover:bg-primary-50">
                                Hotline: {{ setting('contact.hotline') }}
                            </a>
                            <a href="{{ route('quote.index') }}" class="mt-3 block w-full rounded-xl border border-primary-400 px-4 py-3 text-sm font-bold text-white transition-colors hover:bg-primary-700">
                                Yêu cầu báo giá
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Testimonials --}}
    <x-testimonial-section :testimonials="$caseStudy->activeTestimonials" />

    {{-- Related Case Studies --}}
    @if($relatedCaseStudies->count() > 0)
        <div class="bg-surface-50 py-12 sm:py-16 border-t border-surface-200">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-surface-900 mb-8">Dự án tương tự</h2>
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($relatedCaseStudies as $related)
                        <a href="{{ route('case-studies.show', $related->slug) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-surface-200 transition-all hover:shadow-lg hover:ring-primary-500">
                            <div class="relative aspect-[4/3] overflow-hidden bg-surface-100">
                                @if($related->cover_image)
                                    <img src="{{ media_url($related->cover_image) }}" alt="{{ $related->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                                @endif
                                @if($related->project_type)
                                    <div class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-primary-700 backdrop-blur-sm">
                                        {{ $related->project_type }}
                                    </div>
                                @endif
                            </div>
                            <div class="p-6">
                                <h3 class="text-lg font-bold text-surface-900 group-hover:text-primary-600 line-clamp-2">
                                    {{ $related->title }}
                                </h3>
                                @if($related->location)
                                    <p class="mt-2 text-sm text-surface-500">{{ $related->location }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
