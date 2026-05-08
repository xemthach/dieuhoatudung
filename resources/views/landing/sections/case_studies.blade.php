{{-- Case Studies Section --}}
@if(isset($caseStudies) && $caseStudies->isNotEmpty())
<section class="border-t border-surface-200 bg-white py-12 lg:py-16" id="landing-case-studies">
    <div class="container-main">
        <x-section-heading
            :title="$section->title ?? 'Dự Án Thực Tế'"
            :subtitle="$section->subtitle ?? ''"
        />
        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($caseStudies as $cs)
                <a href="{{ route('case-studies.show', $cs->slug) }}" class="card group overflow-hidden block transition-shadow hover:shadow-lg">
                    <article>
                    @if($cs->cover_image)
                        <div class="aspect-video overflow-hidden bg-surface-100">
                            <img src="{{ media_url($cs->cover_image) }}" alt="{{ $cs->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                        </div>
                    @else
                        <div class="flex aspect-video items-center justify-center bg-gradient-to-br from-primary-50 to-primary-100 text-primary-300">
                            <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                    @endif
                    <div class="p-5">
                        <h3 class="text-base font-semibold text-surface-800 transition-colors group-hover:text-primary-600">{{ $cs->title }}</h3>
                        @if($cs->client_name)
                            <p class="mt-1 text-sm text-surface-500">{{ $cs->client_name }}</p>
                        @endif
                        @if($cs->product)
                            <p class="mt-2 flex items-center gap-1 text-xs text-primary-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $cs->product->brand?->name }} — {{ $cs->product->name }}
                            </p>
                        @endif
                        @if($cs->problem)
                            <p class="mt-2 line-clamp-2 text-sm text-surface-500">{{ Str::limit(strip_tags($cs->problem), 120) }}</p>
                        @endif
                    </div>
                    </article>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
