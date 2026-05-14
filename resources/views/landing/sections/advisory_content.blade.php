{{-- Advisory Content Section (SEO content block) --}}
<section class="bg-surface-50 py-12 lg:py-16" id="landing-advisory">
    <div class="container-main">
        <div class="mx-auto max-w-4xl">
            @if($section->title)
                <x-section-heading :title="$section->title" />
            @endif

            <div class="mt-8 rounded-2xl border border-surface-200 bg-white p-6 sm:p-10">
                <div class="prose prose-sm max-w-none text-surface-700 sm:prose-base prose-headings:text-surface-900 prose-h3:text-lg prose-h3:font-bold prose-a:text-primary-600 prose-ul:list-disc prose-li:marker:text-primary-400">
                    @if($section->content)
                        {!! $section->content !!}
                    @else
                        <p class="text-surface-400">Nội dung tư vấn sẽ được cập nhật bởi quản trị viên.</p>
                    @endif
                </div>
            </div>

            {{-- BTU calculation copy is kept user-facing. --}}
            <div class="mt-8 rounded-2xl border border-accent-200 bg-white p-6 sm:p-8">
                <h3 class="text-lg font-bold text-surface-900">Tính công suất BTU theo dữ liệu khảo sát</h3>
                <p class="mt-1 text-sm text-surface-600">Công suất nên được tính dựa trên diện tích, chiều cao trần, loại công trình và điều kiện tải nhiệt thực tế.</p>
                <a href="{{ route('btu-calculator.index') }}" class="mt-4 inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700">Mở công cụ tính BTU</a>
            </div>
        </div>
    </div>
</section>
