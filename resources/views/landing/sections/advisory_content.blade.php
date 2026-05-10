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
            @php
                $grouped = \App\Services\Calculator\BtuCalculatorService::spaceTypeGrouped();
                $svc = new \App\Services\Calculator\BtuCalculatorService();
                $wMap = collect(\App\Services\Calculator\BtuCalculatorService::spaceTypeLabels())
                    ->mapWithKeys(fn($label, $key) => [$key => $svc->getCoolingLoad($key)]);
            @endphp
            <div class="mt-8 rounded-2xl border border-accent-200 bg-gradient-to-br from-accent-50 to-white p-6 sm:p-8"
                x-data="{
                    area: 50,
                    spaceType: 'van_phong',
                    wTable: @js($wMap),
                    tiers: [9000,12000,18000,24000,28000,30000,36000,42000,45000,48000,50000,60000,100000],
                    get wPerM2() { return this.wTable[this.spaceType] || 170 },
                    get rawBtu() { return Math.round(this.area * this.wPerM2 * 3.412) },
                    get result() {
                        for (let t of this.tiers) { if (this.rawBtu <= t) return t }
                        return Math.ceil(this.rawBtu / 1000) * 1000
                    },
                    get hp() { return (this.result / 9000).toFixed(1) }
                }">
                <h3 class="text-lg font-bold text-surface-900">⚡ Tính công suất BTU phù hợp</h3>
                <p class="mt-1 text-sm text-surface-500">Chọn loại không gian và nhập diện tích để ước tính chính xác</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="calc-space" class="mb-1 block text-sm font-medium text-surface-700">Loại không gian</label>
                        <select id="calc-space" x-model="spaceType" class="w-full rounded-lg border border-surface-300 px-3 py-2.5 text-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">
                            @foreach($grouped as $group => $items)
                            <optgroup label="{{ $group }}">
                                @foreach($items as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="calc-area" class="mb-1 block text-sm font-medium text-surface-700">Diện tích (m²)</label>
                        <input type="number" id="calc-area" x-model.number="area" min="5" max="5000" step="1" class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                    <div class="flex flex-col justify-end">
                        <p class="text-sm text-surface-500">Công suất đề xuất:</p>
                        <p class="text-2xl font-bold text-primary-600"><span x-text="result.toLocaleString('vi-VN')"></span> BTU</p>
                        <p class="text-xs text-surface-400">≈ <span x-text="hp"></span> HP · <span x-text="wPerM2"></span> W/m²</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-surface-400">* Kết quả dựa trên hệ số W/m² tiêu chuẩn HVAC. Thực tế có thể cao hơn tùy trần cao, nắng, thiết bị sinh nhiệt.
                    <a href="{{ route('btu-calculator.index') }}" class="text-primary-500 underline hover:text-primary-700">Tính chi tiết →</a>
                </p>
            </div>
        </div>
    </div>
</section>
