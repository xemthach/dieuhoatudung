<x-layouts.app
    :seo-title="$policyPage->seo_title_computed . ' | ' . setting('general.site_name', 'Điều Hòa Tủ Đứng')"
    :seo-description="$policyPage->seo_description_computed"
>
    <div class="container-main py-8 lg:py-12">
        {{-- Breadcrumb --}}
        <nav class="mb-6 text-sm text-surface-500" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1">
                <li><a href="/" class="hover:text-primary-600 transition-colors">Trang chủ</a></li>
                <li class="text-surface-400">/</li>
                <li><a href="{{ route('policy-pages.index') }}" class="hover:text-primary-600 transition-colors">Chính sách</a></li>
                <li class="text-surface-400">/</li>
                <li class="text-surface-700 font-medium">{{ $policyPage->title }}</li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            {{-- Main Content (8/12 desktop) --}}
            <article class="lg:col-span-8 xl:col-span-9">
                <div class="rounded-2xl bg-white p-6 shadow-sm border border-surface-200 lg:p-10">
                    <header class="mb-8 border-b border-surface-100 pb-6">
                        <div class="mb-3">
                            <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">
                                {{ $policyPage->type->label() }}
                            </span>
                        </div>
                        <h1 class="text-2xl font-bold text-surface-900 lg:text-3xl">{{ $policyPage->title }}</h1>
                        <p class="mt-2 text-sm text-surface-500">
                            Cập nhật lần cuối: {{ $policyPage->updated_at->format('d/m/Y') }}
                        </p>
                    </header>

                    {{-- Content with typography --}}
                    <div class="policy-content text-surface-700 leading-relaxed text-base lg:text-lg">
                        @if(strip_tags($policyPage->content) === $policyPage->content)
                            {{-- Plain text: convert newlines to paragraphs --}}
                            {!! nl2br(e($policyPage->content)) !!}
                        @else
                            {{-- HTML content: render as-is --}}
                            {!! $policyPage->content !!}
                        @endif
                    </div>
                </div>
            </article>

            {{-- Sidebar (4/12 desktop) --}}
            <aside class="lg:col-span-4 xl:col-span-3">
                <div class="lg:sticky lg:top-24 space-y-6">
                    {{-- Other Policies --}}
                    @if($otherPages->count() > 0)
                    <div class="rounded-2xl bg-white p-5 shadow-sm border border-surface-200">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-surface-700">Chính sách khác</h3>
                        <ul class="space-y-1">
                            @foreach($otherPages as $otherPage)
                            <li>
                                <a href="{{ $otherPage->public_url }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm text-surface-600 transition-colors hover:bg-surface-50 hover:text-primary-600">
                                    <svg class="h-4 w-4 flex-shrink-0 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    {{ $otherPage->title }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    {{-- Contact CTA --}}
                    <div class="rounded-2xl bg-primary-50 p-5 border border-primary-100">
                        <h3 class="mb-2 font-semibold text-primary-900">Cần hỗ trợ?</h3>
                        <p class="mb-4 text-sm text-primary-700">Liên hệ chúng tôi nếu bạn có thắc mắc về chính sách.</p>
                        @if(setting('contact.hotline'))
                        <a href="tel:{{ setting('contact.hotline') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ setting('contact.hotline') }}
                        </a>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- Policy content typography --}}
    <style>
        .policy-content h2 { font-size: 1.5rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem; color: #1e293b; }
        .policy-content h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #334155; }
        .policy-content p { margin-bottom: 1rem; }
        .policy-content ul, .policy-content ol { padding-left: 1.5rem; margin-bottom: 1rem; }
        .policy-content ul { list-style-type: disc; }
        .policy-content ol { list-style-type: decimal; }
        .policy-content li { margin-bottom: 0.25rem; }
        .policy-content a { color: #2563eb; text-decoration: underline; }
        .policy-content a:hover { color: #1d4ed8; }
        .policy-content blockquote { border-left: 4px solid #e2e8f0; padding-left: 1rem; margin: 1rem 0; color: #64748b; font-style: italic; }
        .policy-content table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        .policy-content th, .policy-content td { border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; text-align: left; }
        .policy-content th { background: #f8fafc; font-weight: 600; }
        .policy-content strong, .policy-content b { font-weight: 600; }
    </style>

    @php
        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Chính sách', 'item' => route('policy-pages.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $policyPage->title, 'item' => $policyPage->public_url],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
</x-layouts.app>
