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

            {{-- Quick BTU Calculator --}}
            <div class="mt-8 rounded-2xl border border-accent-200 bg-gradient-to-br from-accent-50 to-white p-6 sm:p-8" x-data="{ area: 50, result: 30000 }" x-init="$watch('area', v => result = Math.ceil(v * 600 / 1000) * 1000)">
                <h3 class="text-lg font-bold text-surface-900"> Tính công suất BTU phù hợp</h3>
                <p class="mt-1 text-sm text-surface-500">Nhập diện tích phòng để ước tính công suất cần thiết</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="calc-area" class="mb-1 block text-sm font-medium text-surface-700">Diện tích (m²)</label>
                        <input type="number" id="calc-area" x-model.number="area" min="10" max="500" step="5" class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-surface-500">Công suất ước tính:</p>
                        <p class="text-2xl font-bold text-primary-600"><span x-text="result.toLocaleString('vi-VN')"></span> BTU</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-surface-400">* Công thức cơ bản: Diện tích × 600 BTU. Hệ số thực tế có thể cao hơn tùy điều kiện phòng.</p>
            </div>
        </div>
    </div>
</section>
