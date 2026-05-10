<x-layouts.app :seo-title="$seoTitle" :seo-description="$seoDescription" canonical="{{ route('case-studies.index') }}">
    
    {{-- Header Section --}}
    <div class="bg-primary-50 py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-3xl font-extrabold tracking-tight text-surface-900 sm:text-4xl lg:text-5xl">
                    Dự Án Thực Tế
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-surface-600">
                    Khám phá các công trình tiêu biểu chúng tôi đã thi công lắp đặt điều hòa tủ đứng. 
                    Từ không gian nhà hàng, văn phòng đến showroom và nhà xưởng.
                </p>
            </div>
        </div>
    </div>

    {{-- Filter Section --}}
    <div class="border-b border-surface-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('case-studies.index') }}" 
                   class="rounded-full px-4 py-2 text-sm font-medium transition-colors {{ !request('project_type') ? 'bg-primary-600 text-white' : 'bg-surface-100 text-surface-700 hover:bg-surface-200' }}">
                    Tất cả dự án
                </a>
                @foreach($projectTypes as $type)
                    <a href="{{ route('case-studies.index', ['project_type' => $type]) }}" 
                       class="rounded-full px-4 py-2 text-sm font-medium transition-colors {{ request('project_type') === $type ? 'bg-primary-600 text-white' : 'bg-surface-100 text-surface-700 hover:bg-surface-200' }}">
                        {{ $type }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Grid Section --}}
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 sm:py-16">
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($caseStudies as $caseStudy)
                <a href="{{ route('case-studies.show', $caseStudy->slug) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-surface-200 transition-all hover:shadow-lg hover:ring-primary-500">
                    <div class="relative aspect-[4/3] overflow-hidden bg-surface-100">
                        @if($caseStudy->cover_image)
                            <img src="{{ media_url($caseStudy->cover_image) }}" alt="{{ $caseStudy->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-surface-400">
                                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                        
                        @if($caseStudy->project_type)
                            <div class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-primary-700 backdrop-blur-sm">
                                {{ $caseStudy->project_type }}
                            </div>
                        @endif
                    </div>
                    
                    <div class="flex flex-1 flex-col p-6">
                        <h3 class="text-lg font-bold text-surface-900 group-hover:text-primary-600 line-clamp-2">
                            {{ $caseStudy->title }}
                        </h3>
                        
                        <div class="mt-4 flex flex-col gap-2 text-sm text-surface-600">
                            @if($caseStudy->location)
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="truncate">{{ $caseStudy->location }}</span>
                                </div>
                            @endif
                            
                            @if($caseStudy->area_m2)
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                                    <span>Diện tích: {{ $caseStudy->area_m2 }}</span>
                                </div>
                            @endif
                            
                            @if($caseStudy->product)
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <span class="truncate">Sản phẩm: {{ $caseStudy->product->name }}</span>
                                </div>
                            @endif
                        </div>
                        
                        <div class="mt-auto pt-6 text-sm font-medium text-primary-600">
                            Xem chi tiết &rarr;
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full py-12 text-center text-surface-500">
                    Chưa có dự án nào trong danh mục này.
                </div>
            @endforelse
        </div>
        
        <div class="mt-12">
            {{ $caseStudies->links() }}
        </div>
    </div>

    {{-- CTA Section --}}
    <div class="bg-primary-600">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16 lg:flex lg:items-center lg:justify-between">
            <h2 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
                <span class="block">Bạn đang có dự án cần tư vấn?</span>
                <span class="block text-primary-200 text-xl font-normal mt-2">Chúng tôi sẽ khảo sát và lên dự toán hoàn toàn miễn phí.</span>
            </h2>
            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0 gap-4">
                @if(setting('contact.zalo_link'))
                <a href="{{ setting('contact.zalo_link') }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-base font-bold text-primary-600 hover:bg-primary-50 transition-colors">
                    {{ setting('cta.zalo_cta_text', 'Chat Zalo') }}
                </a>
                @endif
                @if(setting('contact.hotline'))
                <a href="tel:{{ setting('contact.hotline') }}" class="inline-flex items-center justify-center rounded-xl border border-transparent bg-primary-700 px-5 py-3 text-base font-bold text-white hover:bg-primary-800 transition-colors">
                    {{ setting('cta.phone_cta_text', 'Gọi ngay') }} {{ setting('contact.hotline_display', setting('contact.hotline')) }}
                </a>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
