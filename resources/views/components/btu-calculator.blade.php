@props([
    'result' => null,
    'products' => null,
    'calc' => null,
])

{{-- ================================================================
     COMPONENT: BTU Calculator
     Nhúng vào trang /cong-cu/... hoặc landing page
     ================================================================ --}}

{{-- INPUT FORM --}}
<div class="mx-auto max-w-3xl" id="btu-calculator">
    <div class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-lg">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-primary-600 to-primary-800 px-6 py-5 text-white">
            <h2 class="text-xl font-bold"> Tính Công Suất Điều Hòa Phù Hợp</h2>
            <p class="mt-1 text-sm text-primary-100">Ước tính công suất tham khảo theo điều kiện không gian</p>
        </div>

        {{-- Errors --}}
        @if($errors->any())
        <div class="border-b border-red-200 bg-red-50 px-6 py-3">
            <ul class="list-inside list-disc space-y-1 text-sm text-red-700">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('btu-calculator.calculate') }}" class="space-y-5 p-6"
            x-data="{ equipmentType: @js(old('equipment_type', 'unsure')) }">
            @csrf

            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-surface-700">Phương pháp tính</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-xl border border-surface-300 p-4 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                        <input type="radio" name="method" value="area" required
                            {{ old('method', 'area') === 'area' ? 'checked' : '' }}
                            class="mt-1 h-4 w-4 accent-primary-600">
                        <span>
                            <span class="block text-sm font-semibold text-surface-800">Theo diện tích</span>
                            <span class="mt-1 block text-xs leading-relaxed text-surface-500">Ước tính nhanh cho phòng có chiều cao thông thường.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-xl border border-surface-300 p-4 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                        <input type="radio" name="method" value="volume" required
                            {{ old('method') === 'volume' ? 'checked' : '' }}
                            class="mt-1 h-4 w-4 accent-primary-600">
                        <span>
                            <span class="block text-sm font-semibold text-surface-800">Theo thể tích</span>
                            <span class="mt-1 block text-xs leading-relaxed text-surface-500">Đưa chiều cao trực tiếp vào thể tích không gian cần làm lạnh.</span>
                        </span>
                    </label>
                </div>
                @error('method')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-surface-700">Bạn muốn dùng loại máy nào?</legend>
                <select name="equipment_type" x-model="equipmentType"
                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 @error('equipment_type') border-red-400 @enderror">
                    @foreach(app(\App\Services\Calculator\EquipmentTypeRecommendationService::class)->options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-surface-500">Đây là lựa chọn mong muốn, không làm thay đổi kết quả tính BTU. Chọn “Chưa xác định” nếu cần tư vấn.</p>
                @error('equipment_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                <div x-show="equipmentType === 'cassette'" x-cloak class="mt-3 rounded-xl border border-surface-200 bg-surface-50 p-3">
                    <label class="mb-1.5 block text-sm font-medium text-surface-700" for="cassette_ceiling_clearance">Trần có khoảng không lắp dàn lạnh cassette?</label>
                    <select id="cassette_ceiling_clearance" name="cassette_ceiling_clearance" class="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm">
                        @foreach(['unknown' => 'Không rõ', 'yes' => 'Có', 'no' => 'Không'] as $value => $label)
                            <option value="{{ $value }}" {{ old('cassette_ceiling_clearance', 'unknown') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="equipmentType === 'ducted'" x-cloak class="mt-3 rounded-xl border border-surface-200 bg-surface-50 p-3">
                    <label class="mb-1.5 block text-sm font-medium text-surface-700" for="duct_space">Công trình có trần kỹ thuật/không gian đi ống gió?</label>
                    <select id="duct_space" name="duct_space" class="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm">
                        @foreach(['unknown' => 'Không rõ', 'yes' => 'Có', 'no' => 'Không'] as $value => $label)
                            <option value="{{ $value }}" {{ old('duct_space', 'unknown') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                {{-- Diện tích --}}
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-surface-700" for="area_m2">
                        Diện tích phòng <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number" id="area_m2" name="area_m2"
                            value="{{ old('area_m2') }}"
                            min="5" max="5000" step="0.5" required
                            class="w-full rounded-lg border border-surface-300 py-2.5 pl-4 pr-12 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 @error('area_m2') border-red-400 @enderror"
                            placeholder="vd: 40"
                        >
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-surface-400">m²</span>
                    </div>
                </div>

                {{-- Chiều cao trần --}}
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-surface-700" for="ceiling_height">
                        Chiều cao trần
                        <span class="font-normal text-surface-400">(mặc định 3m)</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number" id="ceiling_height" name="ceiling_height"
                            value="{{ old('ceiling_height', 3) }}"
                            min="2" max="15" step="0.1"
                            class="w-full rounded-lg border border-surface-300 py-2.5 pl-4 pr-10 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        >
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-surface-400">m</span>
                    </div>
                </div>

                {{-- Loại không gian --}}
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-semibold text-surface-700">
                        Loại không gian <span class="text-red-500">*</span>
                    </label>
                    <select name="space_type" required
                        class="w-full rounded-lg border border-surface-300 py-2.5 px-4 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 @error('space_type') border-red-400 @enderror">
                        <option value="">-- Chọn loại không gian --</option>
                        @foreach(\App\Services\Calculator\BtuCalculatorService::spaceTypeGroupedLabels() as $group => $items)
                        <optgroup label="{{ $group }}">
                            @foreach($items as $key => $label)
                            <option value="{{ $key }}" {{ old('space_type', 'van_phong') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                </div>

                {{-- Số người --}}
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-surface-700" for="people_count">
                        Số người thường xuyên
                    </label>
                    <input
                        type="number" id="people_count" name="people_count"
                        value="{{ old('people_count', 0) }}"
                        min="0" max="5000"
                        class="w-full rounded-lg border border-surface-300 py-2.5 px-4 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        placeholder="Ví dụ: 20"
                    >
                </div>

                {{-- Priority --}}
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-surface-700" for="priority">
                        Ưu tiên khi chọn máy
                    </label>
                    <select id="priority" name="priority"
                        class="w-full rounded-lg border border-surface-300 py-2.5 px-4 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                        @foreach(\App\Services\Calculator\BtuCalculatorService::priorityOptions() as $value => $label)
                            <option value="{{ $value }}" {{ old('priority') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-surface-500">Chỉ thay đổi thứ tự sản phẩm; không thay đổi kết quả tính công suất.</p>
                </div>

                {{-- Toggles --}}
                <div class="sm:col-span-2">
                    <div class="flex flex-wrap gap-4">
                        <label class="flex cursor-pointer items-center gap-2.5">
                            <input type="hidden" name="direct_sunlight" value="0">
                            <input type="checkbox" name="direct_sunlight" value="1"
                                {{ old('direct_sunlight') ? 'checked' : '' }}
                                class="h-4 w-4 rounded accent-primary-600">
                            <span class="text-sm text-surface-700"> Có nắng trực tiếp vào phòng</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2.5">
                            <input type="hidden" name="heat_equipment" value="0">
                            <input type="checkbox" name="heat_equipment" value="1"
                                {{ old('heat_equipment') ? 'checked' : '' }}
                                class="h-4 w-4 rounded accent-primary-600">
                            <span class="text-sm text-surface-700"> Nhiều thiết bị sinh nhiệt (bếp, máy móc…)</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Optional contact --}}
            <div class="rounded-xl border border-surface-200 bg-surface-50 p-4">
                <p class="mb-3 text-sm font-medium text-surface-600"> Nhận tư vấn qua điện thoại <span class="font-normal">(không bắt buộc)</span></p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input type="text" name="full_name" value="{{ old('full_name') }}"
                        class="rounded-lg border border-surface-300 py-2.5 px-4 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        placeholder="Họ và tên">
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                        class="rounded-lg border border-surface-300 py-2.5 px-4 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        placeholder="Số điện thoại">
                </div>
                <p class="mt-2 text-xs text-surface-500">Thông tin liên hệ chỉ được lưu và gửi cho bộ phận tư vấn khi bạn cung cấp số điện thoại.</p>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">
                Kết quả mang tính tham khảo. Công suất thực tế có thể thay đổi theo hướng nắng, vật liệu, số người, thiết bị sinh nhiệt và điều kiện công trình.
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 py-3.5 text-base font-bold text-white shadow-md transition-all hover:from-primary-700 hover:to-primary-800 hover:shadow-lg active:scale-[0.99]">
                 Tính Công Suất BTU Ngay
            </button>
        </form>
    </div>
</div>

{{-- ─────────────────────────────────────────────────────────
     RESULT BLOCK — chỉ hiện khi có kết quả
     ───────────────────────────────────────────────────────── --}}
@if($result)
<div class="mx-auto mt-8 max-w-3xl" id="btu-result">

    {{-- Result card --}}
    <div class="overflow-hidden rounded-2xl border-2 border-primary-300 bg-gradient-to-br from-primary-50 to-white shadow-xl">
        <div class="bg-primary-600 px-6 py-4 text-white">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold"> Kết quả ước tính tham khảo</h3>
                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-medium">
                    {{ $result['method_label'] ?? 'Theo diện tích' }} · {{ $calc['area_m2'] ?? '—' }}m²
                </span>
            </div>
        </div>

        <div class="p-6">
            {{-- Calculated load and market tier are different concepts. --}}
            <div class="mb-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-surface-200 bg-white p-4 text-center">
                    <div class="text-xs font-semibold uppercase tracking-wide text-surface-500">Nhu cầu tải ước tính</div>
                    <div class="mt-1 text-3xl font-extrabold text-surface-900">{{ number_format($result['calculated_btu'] ?? $result['raw_btu']) }}</div>
                    <div class="text-sm text-surface-500">BTU/h</div>
                </div>
                <div class="rounded-2xl bg-primary-600 p-4 text-center text-white shadow-lg">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-100">Nhóm công suất thị trường</div>
                    <div class="mt-1 text-3xl font-extrabold">{{ number_format($result['recommended_btu']) }}</div>
                    <div class="text-sm text-primary-100">BTU · {{ isset($result['recommended_hp']) ? '≈ ' . $result['recommended_hp'] . ' HP' : 'HP chưa xác định' }}</div>
                </div>
            </div>

            {{-- Thông tin tóm tắt --}}
            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-surface-100 p-3 text-center">
                    <div class="text-xs text-surface-500">Loại không gian</div>
                    <div class="text-sm font-semibold text-surface-800">{{ \App\Models\BtuCalculation::spaceTypeLabels()[$calc['space_type'] ?? ''] ?? '—' }}</div>
                </div>
                <div class="rounded-xl bg-surface-100 p-3 text-center">
                    <div class="text-xs text-surface-500">Hệ số cơ sở</div>
                    <div class="text-sm font-semibold text-surface-800">{{ isset($result['factor_value']) ? number_format((float) $result['factor_value'], 2, ',', '.') : '—' }} {{ $result['factor_unit'] ?? '' }}</div>
                </div>
                <div class="rounded-xl bg-surface-100 p-3 text-center">
                    <div class="text-xs text-surface-500">Nhu cầu ước tính</div>
                    <div class="text-sm font-semibold text-surface-800">{{ number_format($result['calculated_btu'] ?? $result['raw_btu']) }} BTU</div>
                </div>
                <div class="rounded-xl bg-surface-100 p-3 text-center">
                    <div class="text-xs text-surface-500">Market tier</div>
                    <div class="text-sm font-semibold text-primary-700">{{ number_format($result['recommended_btu']) }} BTU</div>
                </div>
            </div>

            @php($equipment = $result['equipment_recommendation'] ?? null)
            @if($equipment)
                <div class="mb-4 rounded-xl border p-4 {{ in_array($equipment['status'], ['NOT_RECOMMENDED_FOR_THIS_LOAD', 'TECHNICAL_CONSULTATION_REQUIRED'], true) ? 'border-amber-300 bg-amber-50' : 'border-blue-200 bg-blue-50' }}">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-surface-500">Loại máy bạn chọn</div>
                            <div class="mt-1 font-bold text-surface-900">{{ $equipment['requested_type_label'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-surface-500">Đánh giá</div>
                            <div class="mt-1 font-bold text-surface-900">{{ $equipment['status_label'] }}</div>
                        </div>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-surface-700">{{ $equipment['reason'] }}</p>

                    @if(! empty($equipment['installation_notes']))
                        <div class="mt-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-surface-500">Lưu ý lắp đặt</div>
                            <ul class="mt-1 list-disc space-y-1 pl-5 text-xs text-surface-600">
                                @foreach($equipment['installation_notes'] as $note)<li>{{ $note }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    @if(! empty($equipment['alternatives']))
                        <div class="mt-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-surface-500">Loại thay thế có model đủ công suất trong catalog</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($equipment['alternatives'] as $alternative)
                                    <span class="rounded-full border border-surface-200 bg-white px-3 py-1.5 text-xs text-surface-700">
                                        {{ $alternative['label'] }} · từ {{ number_format($alternative['nearest_btu']) }} BTU
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="mb-4 rounded-xl border border-surface-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm text-surface-600">Model đúng type đủ công suất gần nhất</span>
                    <strong class="text-base text-surface-900">
                        {{ isset($result['nearest_available_product_btu']) ? number_format($result['nearest_available_product_btu']) . ' BTU' : 'Chưa có sản phẩm đủ công suất' }}
                    </strong>
                </div>
                @if(($result['catalog_gap_btu'] ?? 0) > 0)
                    <p class="mt-1 text-xs text-amber-700">
                         Catalog không có model đúng type ở nhóm {{ number_format($result['recommended_btu']) }} BTU; model đủ công suất gần nhất cao hơn {{ number_format($result['catalog_gap_btu']) }} BTU. Đây là khoảng trống catalog, không phải kết quả công thức.
                    </p>
                @endif
            </div>

            @if(($result['method'] ?? 'area') === 'volume')
                <div class="mb-4 grid grid-cols-3 gap-3">
                    <div class="rounded-xl bg-surface-50 p-3 text-center"><div class="text-xs text-surface-500">Diện tích</div><div class="font-semibold">{{ $calc['area_m2'] ?? '—' }} m²</div></div>
                    <div class="rounded-xl bg-surface-50 p-3 text-center"><div class="text-xs text-surface-500">Chiều cao</div><div class="font-semibold">{{ $calc['ceiling_height'] ?? '—' }} m</div></div>
                    <div class="rounded-xl bg-surface-50 p-3 text-center"><div class="text-xs text-surface-500">Thể tích</div><div class="font-semibold">{{ number_format($result['volume_m3'] ?? 0, 2) }} m³</div></div>
                </div>
            @endif

            {{-- Giải thích --}}
            <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm leading-relaxed text-blue-800"> {{ $result['explanation'] }}</p>
                <p class="mt-2 text-xs text-blue-600">Phiên bản quy tắc: {{ $result['rule_version'] ?? 'unversioned' }}</p>
            </div>

            {{-- Warning note (split machine) --}}
            @if(! empty($result['note']))
            <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4">
                <div class="flex items-start gap-2">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <p class="text-sm font-medium text-amber-800">{{ $result['note'] }}</p>
                </div>
            </div>
            @endif

            {{-- Bước tính --}}
            @if(! empty($result['steps']))
            <details class="mb-5 cursor-pointer">
                <summary class="text-sm font-medium text-surface-600 hover:text-primary-600">
                    Xem chi tiết cách tính ▾
                </summary>
                <ul class="mt-2 space-y-1 pl-4">
                    @foreach($result['steps'] as $step)
                    <li class="text-xs text-surface-500 before:mr-2 before:content-['→']">{{ $step }}</li>
                    @endforeach
                </ul>
            </details>
            @endif

            {{-- CTA buttons --}}
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('quote.index', ['source' => 'calculator']) }}"
                    class="flex-1 rounded-xl bg-accent-500 py-3 text-center text-sm font-bold text-white transition-all hover:bg-accent-600">
                     Nhận báo giá theo kết quả này
                </a>
                <a href="tel:{{ setting('contact.hotline', '') }}"
                    class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 border-primary-600 py-3 text-center text-sm font-bold text-primary-700 transition-all hover:bg-primary-50">
                     {{ setting('cta.phone_cta_text', 'Gọi tư vấn') }}
                </a>
            </div>
            <p class="mt-3 text-xs leading-relaxed text-surface-500">Kết quả là ước tính tham khảo để chọn dải công suất. Loại máy, số lượng và phương án lắp đặt cần được xác nhận theo hiện trạng công trình.</p>
        </div>
    </div>

    {{-- Sản phẩm đề xuất --}}
    @if(! empty($products) && count($products) > 0)
    <div class="mt-8">
        <h3 class="mb-4 text-lg font-bold text-surface-900">
             Các model đúng chủng loại hiện có từ {{ number_format($result['recommended_btu']) }} BTU
        </h3>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($products as $product)
            <a href="{{ route('product.show', $product['slug']) }}"
                class="group overflow-hidden rounded-xl border border-surface-200 bg-white transition-all hover:border-primary-300 hover:shadow-md">
                {{-- Thumbnail --}}
                <div class="aspect-square overflow-hidden bg-surface-100">
                    <img
                        src="{{ !empty($product['main_image']) ? media_url($product['main_image']) : (setting('product_detail.default_product_image') ? media_url(setting('product_detail.default_product_image')) : asset('images/placeholders/product-default.jpg')) }}"
                        alt="{{ $product['name'] }}"
                        class="h-full w-full object-contain p-2 transition-transform group-hover:scale-105"
                        loading="lazy">
                </div>
                {{-- Info --}}
                <div class="p-3">
                    @if(! empty($product['btu']))
                    <div class="mb-1 inline-block rounded bg-primary-100 px-2 py-0.5 text-xs font-bold text-primary-700">
                        {{ number_format($product['btu']) }} BTU
                    </div>
                    @endif
                    @if(! empty($product['equipment_type_label']))
                        <div class="mb-1 text-[11px] font-medium text-surface-500">{{ $product['equipment_type_label'] }}</div>
                    @endif
                    <h4 class="text-xs font-semibold leading-tight text-surface-800 line-clamp-2 group-hover:text-primary-700">
                        {{ $product['name'] }}
                    </h4>
                    @if(! empty($product['brand']))<p class="mt-1 text-xs text-surface-500">{{ $product['brand'] }}</p>@endif
                    @if(! empty($product['sale_price']))
                    <p class="mt-1 text-sm font-bold text-primary-700">
                        {{ number_format($product['sale_price'], 0, ',', '.') }}đ
                    </p>
                    @elseif(! empty($product['regular_price']))
                    <p class="mt-1 text-sm font-bold text-surface-700">
                        {{ number_format($product['regular_price'], 0, ',', '.') }}đ
                    </p>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @else
    <div class="mt-6 rounded-xl border border-surface-200 bg-surface-50 p-6 text-center text-sm text-surface-500">
        Phép tính đã hoàn tất nhưng catalog hiện không có model đúng type đủ công suất qua các gate xác minh. Vui lòng <a href="{{ route('quote.index', ['source' => 'calculator']) }}" class="text-primary-600 underline">nhờ kỹ thuật tư vấn</a>.
    </div>
    @endif

    {{-- Internal links --}}
    <div class="mt-6 flex flex-wrap gap-2">
        <a href="/dieu-hoa-tu-dung" class="rounded-full border border-surface-200 bg-white px-4 py-2 text-xs text-surface-600 hover:border-primary-300 hover:text-primary-600">
            ← Xem toàn bộ điều hòa tủ đứng
        </a>
        <a href="/bang-gia/dieu-hoa-tu-dung" class="rounded-full border border-surface-200 bg-white px-4 py-2 text-xs text-surface-600 hover:border-primary-300 hover:text-primary-600">
             Bảng giá điều hòa tủ đứng
        </a>
        <a href="#btu-calculator" class="rounded-full border border-surface-200 bg-white px-4 py-2 text-xs text-surface-600 hover:border-primary-300 hover:text-primary-600">
             Tính lại
        </a>
    </div>
</div>
@endif
