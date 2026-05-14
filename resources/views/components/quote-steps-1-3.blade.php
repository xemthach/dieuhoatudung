{{-- STEP 1: Loại công trình --}}
<div x-show="step === 1" x-transition.opacity>
    <h3 class="mb-1 text-lg font-bold text-surface-900">Loại công trình của bạn?</h3>
    <p class="mb-4 text-sm text-surface-500">Chọn loại không gian cần lắp điều hòa.</p>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach(\App\Models\QuoteRequest::projectTypeLabels() as $val => $label)
        <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 p-3 text-center text-sm font-medium transition-all hover:border-primary-400"
            :class="formData.project_type === '{{ $val }}' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200 bg-white text-surface-700'">
            <input type="radio" name="project_type" value="{{ $val }}" x-model="formData.project_type" class="sr-only">
            <span>{{ $label }}</span>
        </label>
        @endforeach
    </div>
    <div class="mt-4 space-y-3">
        <div>
            <label class="form-label">Mô tả không gian <span class="font-normal text-surface-400">(không bắt buộc)</span></label>
            <input type="text" name="usage_description" x-model="formData.usage_description" class="form-input"
                placeholder="vd: phòng khách, sảnh, khu bán hàng...">
        </div>
        <div>
            <label class="form-label">Số phòng / khu vực</label>
            <input type="number" name="number_of_rooms" x-model="formData.number_of_rooms"
                min="1" max="100" class="form-input" placeholder="1">
        </div>
    </div>
    <div class="mt-6 flex justify-end">
        <button type="button" @click="nextStep()" class="btn-primary">Tiếp theo &rarr;</button>
    </div>
</div>

{{-- STEP 2: Diện tích & điều kiện --}}
<div x-show="step === 2" x-transition.opacity>
    <h3 class="mb-1 text-lg font-bold text-surface-900">Diện tích &amp; điều kiện không gian</h3>
    <p class="mb-4 text-sm text-surface-500">Thông tin giúp tính đúng công suất BTU.</p>
    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label" for="area_m2">Diện tích phòng <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="number" id="area_m2" name="area_m2" x-model="formData.area_m2"
                        min="5" max="50000" step="1" placeholder="vd: 80"
                        class="form-input pr-12 @error('area_m2') border-red-400 @enderror">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-surface-400">m&sup2;</span>
                </div>
                @error('area_m2')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="ceiling_height">Chiều cao trần</label>
                <div class="relative">
                    <input type="number" id="ceiling_height" name="ceiling_height" x-model="formData.ceiling_height"
                        min="2" max="15" step="0.5" placeholder="3" class="form-input pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-surface-400">m</span>
                </div>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Số người thường xuyên</label>
                <input type="number" name="number_of_people" x-model="formData.number_of_people"
                    min="0" max="5000" class="form-input" placeholder="vd: 10">
            </div>
            <div>
                <label class="form-label">Mức độ tiếp xúc nắng</label>
                <select name="sun_exposure" x-model="formData.sun_exposure" class="form-input">
                    <option value="">-- Chọn --</option>
                    @foreach(\App\Models\QuoteRequest::sunExposureLabels() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Chất lượng cách nhiệt</label>
                <select name="insulation_quality" x-model="formData.insulation_quality" class="form-input">
                    <option value="">-- Chọn --</option>
                    @foreach(\App\Models\QuoteRequest::insulationLabels() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Diện tích kính</label>
                <select name="glass_area" x-model="formData.glass_area" class="form-input">
                    <option value="">-- Chọn --</option>
                    @foreach(\App\Models\QuoteRequest::glassAreaLabels() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="form-label">Tình trạng điều hòa hiện tại</label>
            <select name="current_aircon_status" x-model="formData.current_aircon_status" class="form-input">
                <option value="">-- Chọn --</option>
                @foreach(\App\Models\QuoteRequest::airconStatusLabels() as $v => $l)
                <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-700">
            Chưa biết diện tích chính xác? Nhập ước tính &mdash; chuyên gia sẽ xác nhận khi tư vấn.
        </div>
    </div>
    <div class="mt-6 flex justify-between">
        <button type="button" @click="prevStep()" class="btn-ghost">&larr; Quay lại</button>
        <button type="button" @click="nextStep()" class="btn-primary">Tiếp theo &rarr;</button>
    </div>
</div>

{{-- STEP 3: Yêu cầu kỹ thuật --}}
<div x-show="step === 3" x-transition.opacity>
    <h3 class="mb-1 text-lg font-bold text-surface-900">Yêu cầu kỹ thuật</h3>
    <p class="mb-4 text-sm text-surface-500">Thông tin kỹ thuật giúp tư vấn chính xác hơn.</p>
    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Công suất BTU mong muốn</label>
                <select name="preferred_btu" x-model="formData.preferred_btu" class="form-input">
                    @foreach(\App\Models\QuoteRequest::btuOptions() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Loại lắp đặt</label>
                <select name="installation_type" x-model="formData.installation_type" class="form-input">
                    <option value="">-- Chọn --</option>
                    @foreach(\App\Models\QuoteRequest::installationTypeLabels() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="form-label">Thương hiệu ưa thích <span class="font-normal text-surface-400">(chọn nhiều)</span></label>
            <div class="flex flex-wrap gap-2">
                @foreach(['GREE','Daikin','Panasonic','Mitsubishi','LG','Samsung','Khác'] as $brand)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition hover:border-primary-400"
                    :class="(formData.preferred_brands||[]).includes('{{ $brand }}') ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200'">
                    <input type="checkbox" name="preferred_brands[]" value="{{ $brand }}"
                        x-model="formData.preferred_brands" class="h-4 w-4 accent-primary-600">
                    {{ $brand }}
                </label>
                @endforeach
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Nguồn điện</label>
                <select name="power_supply" x-model="formData.power_supply" class="form-input">
                    <option value="">-- Chọn --</option>
                    <option value="1_pha">1 pha (220V)</option>
                    <option value="3_pha">3 pha (380V)</option>
                    <option value="chua_ro">Chưa rõ</option>
                </select>
            </div>
            <div>
                <label class="form-label">Vị trí dàn nóng</label>
                <select name="outdoor_unit_location" x-model="formData.outdoor_unit_location" class="form-input">
                    <option value="">-- Chọn --</option>
                    @foreach(\App\Models\QuoteRequest::outdoorLocationLabels() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex flex-wrap gap-4">
            <label class="flex cursor-pointer items-center gap-2.5">
                <input type="hidden" name="need_inverter" value="0">
                <input type="checkbox" name="need_inverter" value="1" x-model="formData.need_inverter"
                    class="h-4 w-4 rounded accent-primary-600">
                <span class="text-sm">Cần Inverter (điều chỉnh tải)</span>
            </label>
            <label class="flex cursor-pointer items-center gap-2.5">
                <input type="hidden" name="need_three_phase" value="0">
                <input type="checkbox" name="need_three_phase" value="1" x-model="formData.need_three_phase"
                    class="h-4 w-4 rounded accent-primary-600">
                <span class="text-sm">Cần điện 3 pha</span>
            </label>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Khoảng cách ống gió (m)</label>
                <input type="number" name="pipe_distance_m" x-model="formData.pipe_distance_m"
                    min="0" max="100" step="0.5" class="form-input" placeholder="vd: 5">
            </div>
            <div>
                <label class="form-label">Thoát nước condensate</label>
                <select name="drainage_available" x-model="formData.drainage_available" class="form-input">
                    <option value="">-- Chọn --</option>
                    <option value="co">Có sẵn</option>
                    <option value="khong">Chưa có</option>
                    <option value="chua_ro">Chưa rõ</option>
                </select>
            </div>
        </div>
    </div>
    <div class="mt-6 flex justify-between">
        <button type="button" @click="prevStep()" class="btn-ghost">&larr; Quay lại</button>
        <button type="button" @click="nextStep()" class="btn-primary">Tiếp theo &rarr;</button>
    </div>
</div>
