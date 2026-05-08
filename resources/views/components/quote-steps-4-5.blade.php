{{-- STEP 4: Ngân sách & thời gian --}}
<div x-show="step === 4" x-transition.opacity>
    <h3 class="mb-1 text-lg font-bold text-surface-900">Ngân sách &amp; thời gian</h3>
    <p class="mb-4 text-sm text-surface-500">Giúp chúng tôi tư vấn giải pháp phù hợp nhất.</p>
    <div class="space-y-5">
        <div>
            <label class="form-label">Ngân sách dự kiến <span class="text-red-500">*</span></label>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach(\App\Models\QuoteRequest::budgetRangeLabels() as $val => $label)
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3 text-sm font-medium transition-all hover:border-primary-400"
                    :class="formData.budget_range === '{{ $val }}' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200 bg-white'">
                    <input type="radio" name="budget_range" value="{{ $val }}" x-model="formData.budget_range" class="accent-primary-600">
                    <span>{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>
        <div>
            <label class="form-label">Thời gian lắp đặt mong muốn</label>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach(\App\Models\QuoteRequest::installationTimeLabels() as $val => $label)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition hover:border-primary-400"
                    :class="formData.installation_time === '{{ $val }}' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200'">
                    <input type="radio" name="installation_time" value="{{ $val }}" x-model="formData.installation_time" class="accent-primary-600">
                    {{ $label }}
                </label>
                @endforeach
            </div>
        </div>
        <div>
            <label class="form-label">Dịch vụ cần báo giá</label>
            <div class="space-y-2">
                @foreach(\App\Models\QuoteRequest::needInstallLabels() as $val => $label)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition hover:border-primary-400"
                    :class="formData.need_installation_service === '{{ $val }}' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200'">
                    <input type="radio" name="need_installation_service" value="{{ $val }}" x-model="formData.need_installation_service" class="accent-primary-600">
                    {{ $label }}
                </label>
                @endforeach
            </div>
        </div>
        <div class="flex flex-wrap gap-5">
            <label class="flex cursor-pointer items-center gap-2">
                <input type="hidden" name="need_invoice" value="0">
                <input type="checkbox" name="need_invoice" value="1" x-model="formData.need_invoice" class="h-4 w-4 accent-primary-600">
                <span class="text-sm">Cần xuất hóa đơn VAT</span>
            </label>
            <label class="flex cursor-pointer items-center gap-2">
                <input type="hidden" name="need_site_survey" value="0">
                <input type="checkbox" name="need_site_survey" value="1" x-model="formData.need_site_survey" class="h-4 w-4 accent-primary-600">
                <span class="text-sm">Cần khảo sát thực tế trước</span>
            </label>
        </div>
    </div>
    <div class="mt-6 flex justify-between">
        <button type="button" @click="prevStep()" class="btn-ghost">&larr; Quay lại</button>
        <button type="button" @click="nextStep()" class="btn-primary">Tiếp theo &rarr;</button>
    </div>
</div>

{{-- STEP 5: Thông tin liên hệ + tóm tắt --}}
<div x-show="step === 5" x-transition.opacity>
    <h3 class="mb-1 text-lg font-bold text-surface-900">Thông tin liên hệ</h3>
    <p class="mb-4 text-sm text-surface-500">Điền thông tin để chúng tôi gửi báo giá sớm nhất.</p>

    @if($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3">
        <ul class="list-inside list-disc space-y-1 text-sm text-red-700">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label" for="full_name">Họ và tên <span class="text-red-500">*</span></label>
                <input type="text" id="full_name" name="full_name" x-model="formData.full_name"
                    class="form-input @error('full_name') border-red-400 @enderror" placeholder="Nguyễn Văn A">
                @error('full_name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="phone">Số điện thoại <span class="text-red-500">*</span></label>
                <input type="tel" id="phone" name="phone" x-model="formData.phone"
                    class="form-input @error('phone') border-red-400 @enderror" placeholder="09x.xxx.xxxx">
                @error('phone')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" x-model="formData.email"
                    class="form-input @error('email') border-red-400 @enderror" placeholder="email@example.com">
            </div>
            <div>
                <label class="form-label" for="province_city">Tỉnh / Thành phố <span class="text-red-500">*</span></label>
                <input type="text" id="province_city" name="province_city" x-model="formData.province_city"
                    class="form-input @error('province_city') border-red-400 @enderror" placeholder="TP.HCM, Hà Nội...">
                @error('province_city')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Cách liên hệ ưa thích</label>
                <select name="preferred_contact_method" x-model="formData.preferred_contact_method" class="form-input">
                    <option value="">-- Chọn --</option>
                    @foreach(\App\Models\QuoteRequest::contactMethodLabels() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Thời gian liên hệ thuận tiện</label>
                <select name="preferred_contact_time" x-model="formData.preferred_contact_time" class="form-input">
                    <option value="">-- Chọn --</option>
                    @foreach(\App\Models\QuoteRequest::contactTimeLabels() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="form-label">Địa chỉ cụ thể</label>
            <input type="text" name="address" x-model="formData.address" class="form-input"
                placeholder="Số nhà, đường, quận...">
        </div>
        <div>
            <label class="form-label" for="message">Ghi chú thêm</label>
            <textarea id="message" name="message" rows="3" x-model="formData.message"
                class="form-input" placeholder="Yêu cầu đặc biệt, câu hỏi thêm..."></textarea>
        </div>

        {{-- Summary --}}
        <div class="rounded-xl border border-surface-200 bg-surface-50 p-4 text-sm">
            <p class="mb-3 font-bold text-surface-800">Tóm tắt yêu cầu:</p>
            <div class="grid gap-1.5 sm:grid-cols-2">
                <div x-show="formData.project_type" class="flex gap-2">
                    <span class="min-w-[90px] text-surface-500">Công trình:</span>
                    <span x-text="{'nha_o':'Nhà ở','can_ho':'Căn hộ','van_phong':'Văn phòng','cua_hang':'Cửa hàng','showroom':'Showroom','nha_hang':'Nhà hàng','hoi_truong':'Hội trường','nha_xuong':'Nhà xưởng','truong_hoc':'Trường học','khach_san':'Khách sạn','khac':'Khác'}[formData.project_type]" class="font-medium text-surface-800"></span>
                </div>
                <div x-show="formData.area_m2" class="flex gap-2">
                    <span class="min-w-[90px] text-surface-500">Diện tích:</span>
                    <span class="font-medium text-surface-800"><span x-text="formData.area_m2"></span> m&sup2;</span>
                </div>
                <div x-show="formData.ceiling_height" class="flex gap-2">
                    <span class="min-w-[90px] text-surface-500">Chiều cao:</span>
                    <span class="font-medium text-surface-800"><span x-text="formData.ceiling_height"></span> m</span>
                </div>
                <div x-show="formData.preferred_btu" class="flex gap-2">
                    <span class="min-w-[90px] text-surface-500">BTU:</span>
                    <span class="font-medium text-surface-800"><span x-text="Number(formData.preferred_btu).toLocaleString()"></span> BTU</span>
                </div>
                <div x-show="formData.need_inverter" class="flex gap-2">
                    <span class="min-w-[90px] text-surface-500">Inverter:</span>
                    <span class="font-medium text-surface-800">Có yêu cầu</span>
                </div>
                <div x-show="formData.need_three_phase" class="flex gap-2">
                    <span class="min-w-[90px] text-surface-500">Điện 3 pha:</span>
                    <span class="font-medium text-surface-800">Có yêu cầu</span>
                </div>
                <div x-show="formData.preferred_brands && formData.preferred_brands.length" class="flex gap-2 sm:col-span-2">
                    <span class="min-w-[90px] text-surface-500">Thương hiệu:</span>
                    <span x-text="(formData.preferred_brands||[]).join(', ')" class="font-medium text-surface-800"></span>
                </div>
                <div x-show="formData.budget_range" class="flex gap-2">
                    <span class="min-w-[90px] text-surface-500">Ngân sách:</span>
                    <span x-text="{'duoi_20_trieu':'Dưới 20 triệu','20_40_trieu':'20-40 triệu','40_70_trieu':'40-70 triệu','tren_70_trieu':'Trên 70 triệu','chua_ro':'Chưa rõ'}[formData.budget_range]" class="font-medium text-surface-800"></span>
                </div>
                <div x-show="formData.installation_time" class="flex gap-2">
                    <span class="min-w-[90px] text-surface-500">Thời gian:</span>
                    <span x-text="{'ngay':'Càng sớm càng tốt','3_ngay':'1-3 ngày','1_tuan':'Trong tuần','1_thang':'Trong tháng','chua_ro':'Chưa xác định'}[formData.installation_time]" class="font-medium text-surface-800"></span>
                </div>
                @if(isset($productContext) && $productContext)
                <div class="flex gap-2 sm:col-span-2 rounded-lg bg-primary-50 border border-primary-200 p-2 mt-1">
                    <span class="min-w-[90px] text-primary-600 font-medium">Sản phẩm:</span>
                    <span class="font-bold text-primary-800">{{ $productContext->name }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-between">
        <button type="button" @click="prevStep()" class="btn-ghost">&larr; Quay lại</button>
        <button type="submit" :disabled="submitting"
            class="btn-accent flex items-center gap-2 px-8 py-3 text-base font-bold disabled:cursor-not-allowed disabled:opacity-50">
            <template x-if="!submitting">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </template>
            <template x-if="submitting">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </template>
            <span x-text="submitting ? 'Đang gửi...' : 'Gửi yêu cầu báo giá'"></span>
        </button>
    </div>
</div>
