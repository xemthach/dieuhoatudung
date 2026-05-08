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
            <p class="mt-1 text-sm text-primary-100">Nhập thông tin không gian để nhận đề xuất chính xác</p>
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
        <form method="POST" action="{{ route('btu-calculator.calculate') }}" class="space-y-5 p-6">
            @csrf

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
                        <optgroup label="Nhà ở">
                            <option value="nha_o" {{ old('space_type') === 'nha_o' ? 'selected' : '' }}>Căn hộ, nhà ở (120 W/m²)</option>
                            <option value="phong_khach" {{ old('space_type') === 'phong_khach' ? 'selected' : '' }}>Phòng khách nhà ở (120 W/m²)</option>
                            <option value="khach_san" {{ old('space_type') === 'khach_san' ? 'selected' : '' }}>Khách sạn, nhà nghỉ (120 W/m²)</option>
                        </optgroup>
                        <optgroup label="Văn phòng">
                            <option value="van_phong" {{ old('space_type', 'van_phong') === 'van_phong' ? 'selected' : '' }}>Văn phòng viền ngoài (170 W/m²)</option>
                            <option value="van_phong_interior" {{ old('space_type') === 'van_phong_interior' ? 'selected' : '' }}>Văn phòng bên trong (100 W/m²)</option>
                            <option value="van_phong_private" {{ old('space_type') === 'van_phong_private' ? 'selected' : '' }}>Văn phòng cá nhân (180 W/m²)</option>
                            <option value="ngan_hang" {{ old('space_type') === 'ngan_hang' ? 'selected' : '' }}>Ngân hàng (175 W/m²)</option>
                        </optgroup>
                        <optgroup label="Thương mại">
                            <option value="cua_hang" {{ old('space_type') === 'cua_hang' ? 'selected' : '' }}>Cửa hàng (165 W/m²)</option>
                            <option value="sieu_thi" {{ old('space_type') === 'sieu_thi' ? 'selected' : '' }}>Siêu thị (160 W/m²)</option>
                            <option value="showroom" {{ old('space_type') === 'showroom' ? 'selected' : '' }}>Showroom (900 W/m²)</option>
                        </optgroup>
                        <optgroup label="F&B">
                            <option value="nha_hang" {{ old('space_type') === 'nha_hang' ? 'selected' : '' }}>Nhà hàng (330 W/m²)</option>
                            <option value="cafe" {{ old('space_type') === 'cafe' ? 'selected' : '' }}>Quán cà phê (350 W/m²)</option>
                            <option value="fastfood" {{ old('space_type') === 'fastfood' ? 'selected' : '' }}>Thức ăn nhanh (270 W/m²)</option>
                        </optgroup>
                        <optgroup label="Hội trường / Giáo dục">
                            <option value="hoi_truong" {{ old('space_type') === 'hoi_truong' ? 'selected' : '' }}>Hội trường, giảng đường (280 W/m²)</option>
                            <option value="phong_hop" {{ old('space_type') === 'phong_hop' ? 'selected' : '' }}>Phòng họp (275 W/m²)</option>
                            <option value="phong_hoc" {{ old('space_type') === 'phong_hoc' ? 'selected' : '' }}>Phòng học (95 W/m²)</option>
                            <option value="thu_vien" {{ old('space_type') === 'thu_vien' ? 'selected' : '' }}>Thư viện (150 W/m²)</option>
                            <option value="rap_hat" {{ old('space_type') === 'rap_hat' ? 'selected' : '' }}>Rạp hát (280 W/m²)</option>
                        </optgroup>
                        <optgroup label="Y tế">
                            <option value="benh_vien" {{ old('space_type') === 'benh_vien' ? 'selected' : '' }}>Bệnh viện, phòng khám (190 W/m²)</option>
                            <option value="phong_duoc" {{ old('space_type') === 'phong_duoc' ? 'selected' : '' }}>Văn phòng dược (185 W/m²)</option>
                        </optgroup>
                        <optgroup label="Công nghiệp">
                            <option value="nha_xuong" {{ old('space_type') === 'nha_xuong' ? 'selected' : '' }}>Nhà xưởng CN nhẹ (275 W/m²)</option>
                            <option value="nha_xuong_nang" {{ old('space_type') === 'nha_xuong_nang' ? 'selected' : '' }}>Nhà xưởng CN nặng (490 W/m²)</option>
                        </optgroup>
                        <optgroup label="Đặc biệt">
                            <option value="phong_may_tinh" {{ old('space_type') === 'phong_may_tinh' ? 'selected' : '' }}>Phòng máy tính / Server (480 W/m²)</option>
                            <option value="phong_thi_nghiem" {{ old('space_type') === 'phong_thi_nghiem' ? 'selected' : '' }}>Phòng thí nghiệm (230 W/m²)</option>
                            <option value="tham_my_vien" {{ old('space_type') === 'tham_my_vien' ? 'selected' : '' }}>Thẩm mỹ viện (260 W/m²)</option>
                            <option value="sanh_hanh_lang" {{ old('space_type') === 'sanh_hanh_lang' ? 'selected' : '' }}>Sảnh, hành lang (135 W/m²)</option>
                            <option value="tang_ham" {{ old('space_type') === 'tang_ham' ? 'selected' : '' }}>Tầng hầm (125 W/m²)</option>
                        </optgroup>
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
                        <option value="">-- Không cụ thể --</option>
                        <option value="tiet_kiem_dien" {{ old('priority') === 'tiet_kiem_dien' ? 'selected' : '' }}> Tiết kiệm điện</option>
                        <option value="gia_tot" {{ old('priority') === 'gia_tot' ? 'selected' : '' }}> Giá tốt nhất</option>
                        <option value="van_hanh_ben_bi" {{ old('priority') === 'van_hanh_ben_bi' ? 'selected' : '' }}> Vận hành bền bỉ</option>
                        <option value="thuong_hieu_cao_cap" {{ old('priority') === 'thuong_hieu_cao_cap' ? 'selected' : '' }}> Thương hiệu cao cấp</option>
                    </select>
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
                <h3 class="text-lg font-bold"> Kết Quả Đề Xuất</h3>
                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-medium">
                    {{ \App\Models\BtuCalculation::spaceTypeLabels()[$calc['space_type'] ?? ''] ?? '' }}
                    · {{ $calc['area_m2'] ?? '—' }}m²
                </span>
            </div>
        </div>

        <div class="p-6">
            {{-- BTU badge --}}
            <div class="mb-5 text-center">
                <div class="inline-flex items-baseline gap-2 rounded-2xl bg-primary-600 px-8 py-4 text-white shadow-lg">
                    <span class="text-5xl font-extrabold">{{ number_format($result['recommended_btu']) }}</span>
                    <div class="text-left">
                        <div class="text-xl font-bold">BTU</div>
                        <div class="text-xs text-primary-200">≈ {{ $result['recommended_hp'] ?? number_format($result['recommended_btu'] / 9000, 1) }} HP</div>
                    </div>
                </div>
            </div>

            {{-- Thông tin tóm tắt --}}
            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-surface-100 p-3 text-center">
                    <div class="text-xs text-surface-500">Diện tích phù hợp</div>
                    <div class="text-sm font-semibold text-surface-800">{{ $result['area_range'] }}</div>
                </div>
                <div class="rounded-xl bg-surface-100 p-3 text-center">
                    <div class="text-xs text-surface-500">Tải lạnh</div>
                    <div class="text-sm font-semibold text-surface-800">{{ $result['cooling_w_per_m2'] ?? '—' }} W/m²</div>
                </div>
                <div class="rounded-xl bg-surface-100 p-3 text-center">
                    <div class="text-xs text-surface-500">Công suất tính toán</div>
                    <div class="text-sm font-semibold text-surface-800">{{ number_format($result['calculated_btu'] ?? $result['raw_btu']) }} BTU</div>
                </div>
                <div class="rounded-xl bg-surface-100 p-3 text-center">
                    <div class="text-xs text-surface-500">Đề xuất</div>
                    <div class="text-sm font-semibold text-primary-700">{{ number_format($result['recommended_btu']) }} BTU</div>
                </div>
            </div>

            {{-- Giải thích --}}
            <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm leading-relaxed text-blue-800"> {{ $result['explanation'] }}</p>
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
                <a href="{{ route('landing.lead') !== '' ? '/dieu-hoa-tu-dung#bao-gia' : '/bao-gia' }}"
                    class="flex-1 rounded-xl bg-accent-500 py-3 text-center text-sm font-bold text-white transition-all hover:bg-accent-600">
                     Yêu cầu báo giá
                </a>
                <a href="tel:{{ setting('contact.hotline', '') }}"
                    class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 border-primary-600 py-3 text-center text-sm font-bold text-primary-700 transition-all hover:bg-primary-50">
                     {{ setting('cta.phone_cta_text', 'Gọi tư vấn') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Sản phẩm đề xuất --}}
    @if(! empty($products) && count($products) > 0)
    <div class="mt-8">
        <h3 class="mb-4 text-lg font-bold text-surface-900">
             Sản Phẩm Phù Hợp Với {{ number_format($result['recommended_btu']) }} BTU
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
                    <h4 class="text-xs font-semibold leading-tight text-surface-800 line-clamp-2 group-hover:text-primary-700">
                        {{ $product['name'] }}
                    </h4>
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
        Chưa có sản phẩm khớp BTU trong catalog. Vui lòng <a href="/lien-he" class="text-primary-600 underline">liên hệ tư vấn trực tiếp</a>.
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
