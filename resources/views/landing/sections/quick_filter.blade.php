{{-- Quick Filter Section (placeholder — will connect to product filters in future phases) --}}
<section class="border-y border-surface-200 bg-surface-50 py-12 lg:py-16" id="landing-quick-filter">
    <div class="container-main">
        <x-section-heading
            :title="$section->title ?? 'Tìm Điều Hòa Phù Hợp'"
            :subtitle="$section->subtitle ?? 'Lọc theo công suất, thương hiệu, và ngân sách'"
        />
        <div class="mx-auto mt-8 max-w-3xl rounded-2xl border border-surface-200 bg-white p-6 sm:p-8">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-surface-700">Công suất</label>
                    <select class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm" disabled>
                        <option>Tất cả</option>
                        <option>24.000 BTU</option>
                        <option>36.000 BTU</option>
                        <option>48.000 BTU</option>
                        <option>Trên 48.000 BTU</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-surface-700">Thương hiệu</label>
                    <select class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm" disabled>
                        <option>Tất cả</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-surface-700">Ngân sách</label>
                    <select class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm" disabled>
                        <option>Tất cả</option>
                        <option>Dưới 20 triệu</option>
                        <option>20 - 40 triệu</option>
                        <option>Trên 40 triệu</option>
                    </select>
                </div>
            </div>
            <p class="mt-4 text-center text-xs text-surface-400">Bộ lọc nâng cao sẽ được kích hoạt ở phiên bản tiếp theo.</p>
        </div>
    </div>
</section>
