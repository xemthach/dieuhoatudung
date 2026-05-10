@props([
    'product' => null,
])

{{--
    FULL QUOTE FORM — 4 steps, used on /bao-gia page
    Step 1: Loai cong trinh
    Step 2: Dien tich & dieu kien
    Step 3: Ngan sach & timeline
    Step 4: Thong tin lien he
--}}
<div
    x-data="{
        step: {{ $errors->any() ? 4 : 1 }},
        totalSteps: 4,
        formData: {
            project_type:              '{{ old('project_type','') }}',
            usage_description:         '{{ old('usage_description','') }}',
            number_of_rooms:           '{{ old('number_of_rooms',1) }}',
            area_m2:                   '{{ old('area_m2','') }}',
            ceiling_height:            '{{ old('ceiling_height',3) }}',
            sun_exposure:              '{{ old('sun_exposure','') }}',
            current_aircon_status:     '{{ old('current_aircon_status','') }}',
            budget_range:              '{{ old('budget_range','') }}',
            installation_time:         '{{ old('installation_time','') }}',
            need_installation_service: '{{ old('need_installation_service','tron_goi') }}',
            full_name:                 '{{ old('full_name','') }}',
            phone:                     '{{ old('phone','') }}',
            email:                     '{{ old('email','') }}',
            province_city:             '{{ old('province_city','') }}',
            message:                   '{{ old('message','') }}',
        },
        submitting: false,
        progress() { return Math.round(((this.step - 1) / (this.totalSteps - 1)) * 100); },
        next() { if(this.step < this.totalSteps) this.step++; window.scrollTo({top:0,behavior:'smooth'}); },
        prev() { if(this.step > 1) this.step--; },
    }"
    class="fqf-wrap"
    id="full-quote-form"
>

    {{-- Progress --}}
    <div class="mb-5">
        <div class="mb-1.5 flex items-center justify-between text-xs text-surface-500">
            <span>Buoc <span x-text="step"></span> / <span x-text="totalSteps"></span></span>
            <span x-text="progress() + '% hoan thanh'"></span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-100">
            <div class="h-1.5 rounded-full bg-gradient-to-r from-primary-500 to-primary-600 transition-all duration-500"
                :style="'width:' + progress() + '%'"></div>
        </div>
        <div class="mt-1.5 flex justify-between text-[10px] text-surface-400">
            @foreach(['Cong trinh', 'Dien tich', 'Ngan sach', 'Lien he'] as $i => $lbl)
            <span :class="step >= {{ $i + 1 }} ? 'text-primary-600 font-semibold' : ''">{{ $lbl }}</span>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('quote.store') }}" id="fqf-el"
        @submit="if(submitting) return false; submitting=true;" novalidate>
        @csrf
        <input type="hidden" name="lead_type" value="{{ $product ? 'product' : 'general' }}">
        @if($product)
        <input type="hidden" name="product_id"   value="{{ $product->id }}">
        <input type="hidden" name="product_name" value="{{ $product->name }}">
        <input type="hidden" name="product_sku"  value="{{ $product->sku ?? '' }}">
        <input type="hidden" name="product_url"  value="{{ route('product.show', $product->slug) }}">
        @endif
        <input type="hidden" name="source_page"  value="{{ url()->current() }}">
        <input type="hidden" name="landing_page" value="{{ url()->current() }}">
        <input type="hidden" name="referrer"     value="{{ request()->headers->get('referer', '') }}">
        <input type="hidden" name="utm_source"   value="{{ request('utm_source') }}">
        <input type="hidden" name="utm_medium"   value="{{ request('utm_medium') }}">
        <input type="hidden" name="utm_campaign" value="{{ request('utm_campaign') }}">
        <input type="hidden" name="utm_term"     value="{{ request('utm_term') }}">
        <input type="hidden" name="utm_content"  value="{{ request('utm_content') }}">
        {{-- Honeypot --}}
        <div style="position:absolute;left:-9999px;height:0;overflow:hidden" aria-hidden="true">
            <input type="text" name="website_url" autocomplete="off" tabindex="-1">
        </div>

        {{-- ═══ STEP 1: Loai cong trinh ═══ --}}
        <div x-show="step === 1" x-transition.opacity.duration.200ms>
            <h3 class="fqf-step-title">Loai cong trinh can lap dieu hoa?</h3>
            <p class="fqf-step-sub">Chon loai khong gian chinh xac de chung toi tu van dung may.</p>

            @if($product)
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 p-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-primary-800">San pham da chon:</p>
                    <p class="text-sm text-primary-700">{{ $product->name }}</p>
                </div>
            </div>
            @endif

            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                @foreach(\App\Models\QuoteRequest::projectTypeLabels() as $val => $label)
                <label class="fqf-radio-card" :class="formData.project_type === '{{ $val }}' ? 'fqf-radio-card--active' : ''">
                    <input type="radio" name="project_type" value="{{ $val }}" x-model="formData.project_type" class="sr-only">
                    <span class="text-sm font-medium">{{ $label }}</span>
                </label>
                @endforeach
            </div>

            <div class="mt-4">
                <label class="fqf-label">Mô tả thêm <span class="fqf-optional">(không bắt buộc)</span></label>
                <input type="text" name="usage_description" x-model="formData.usage_description"
                    class="fqf-input" placeholder="vd: phòng họp, khu sản xuất...">
            </div>
            <div class="mt-3">
                <label class="fqf-label">Số phòng / khu vực</label>
                <input type="number" name="number_of_rooms" x-model="formData.number_of_rooms"
                    min="1" max="500" class="fqf-input w-28" placeholder="1">
            </div>

            <div class="fqf-nav">
                <div></div>
                <button type="button" @click="next()" class="fqf-btn-primary">Tiếp theo &rarr;</button>
            </div>
        </div>

        {{-- ═══ STEP 2: Dien tich & dieu kien co ban ═══ --}}
        <div x-show="step === 2" x-transition.opacity.duration.200ms>
            <h3 class="fqf-step-title">Diện tích & điều kiện không gian</h3>
            <p class="fqf-step-sub">Giúp chúng tôi tính công suất BTU chính xác cho bạn.</p>

            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="fqf-label" for="fqf_area">Diện tích phòng <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" id="fqf_area" name="area_m2" x-model="formData.area_m2"
                                min="5" max="50000" step="1" placeholder="vd: 80"
                                class="fqf-input pr-12 @error('area_m2') border-red-400 @enderror">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-surface-400">m&sup2;</span>
                        </div>
                        @error('area_m2')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="fqf-label" for="fqf_height">Chiều cao trần</label>
                        <div class="relative">
                            <input type="number" id="fqf_height" name="ceiling_height" x-model="formData.ceiling_height"
                                min="2" max="15" step="0.5" placeholder="3" class="fqf-input pr-8">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-surface-400">m</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="fqf-label">Mức độ tiếp xúc nắng</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach(['it_nang' => 'Ít nắng', 'nang_vua' => 'Nắng vừa', 'nang_nhieu' => 'Nắng nhiều'] as $val => $label)
                        <label class="fqf-radio-card text-center" :class="formData.sun_exposure === '{{ $val }}' ? 'fqf-radio-card--active' : ''">
                            <input type="radio" name="sun_exposure" value="{{ $val }}" x-model="formData.sun_exposure" class="sr-only">
                            <span class="text-sm">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="fqf-label">Tình trạng điều hòa hiện tại</label>
                    <select name="current_aircon_status" x-model="formData.current_aircon_status" class="fqf-input">
                        <option value="">-- Chọn --</option>
                        @foreach(\App\Models\QuoteRequest::airconStatusLabels() as $v => $l)
                        <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-700">
                    Chưa biết diện tích chính xác? Cứ nhập ước tính — chuyên gia sẽ xác nhận khi tư vấn.
                </div>
            </div>

            <div class="fqf-nav">
                <button type="button" @click="prev()" class="fqf-btn-ghost">&larr; Quay lại</button>
                <button type="button" @click="next()" class="fqf-btn-primary">Tiếp theo &rarr;</button>
            </div>
        </div>

        {{-- ═══ STEP 3: Ngan sach & thoi gian ═══ --}}
        <div x-show="step === 3" x-transition.opacity.duration.200ms>
            <h3 class="fqf-step-title">Ngân sách & thời gian</h3>
            <p class="fqf-step-sub">Giúp chúng tôi đề xuất giải pháp phù hợp nhất.</p>

            <div class="space-y-5">
                <div>
                    <label class="fqf-label">Ngân sách dự kiến <span class="text-red-500">*</span></label>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        @foreach(\App\Models\QuoteRequest::budgetRangeLabels() as $val => $label)
                        <label class="fqf-radio-card" :class="formData.budget_range === '{{ $val }}' ? 'fqf-radio-card--active' : ''">
                            <input type="radio" name="budget_range" value="{{ $val }}" x-model="formData.budget_range" class="sr-only">
                            <span class="text-sm font-medium">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="fqf-label">Thời gian cần lắp đặt</label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach(\App\Models\QuoteRequest::installationTimeLabels() as $val => $label)
                        <label class="fqf-radio-card" :class="formData.installation_time === '{{ $val }}' ? 'fqf-radio-card--active' : ''">
                            <input type="radio" name="installation_time" value="{{ $val }}" x-model="formData.installation_time" class="sr-only">
                            <span class="text-sm">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="fqf-label">Dịch vụ báo giá</label>
                    <div class="space-y-2">
                        @foreach(\App\Models\QuoteRequest::needInstallLabels() as $val => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-2.5 text-sm transition hover:border-primary-400"
                            :class="formData.need_installation_service === '{{ $val }}' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200'">
                            <input type="radio" name="need_installation_service" value="{{ $val }}"
                                x-model="formData.need_installation_service" class="accent-primary-600">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="fqf-nav">
                <button type="button" @click="prev()" class="fqf-btn-ghost">&larr; Quay lại</button>
                <button type="button" @click="next()" class="fqf-btn-primary">Tiếp theo &rarr;</button>
            </div>
        </div>

        {{-- ═══ STEP 4: Thong tin lien he + summary ═══ --}}
        <div x-show="step === 4" x-transition.opacity.duration.200ms>
            <h3 class="fqf-step-title">Thông tin liên hệ</h3>
            <p class="fqf-step-sub">Điền để chúng tôi gửi báo giá chính xác.</p>

            @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3">
                <ul class="list-inside list-disc space-y-1 text-sm text-red-700">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
            @endif

            <div class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="fqf-label" for="fqf_name">Họ và tên <span class="text-red-500">*</span></label>
                        <input type="text" id="fqf_name" name="full_name" x-model="formData.full_name"
                            class="fqf-input @error('full_name') border-red-400 @enderror"
                            placeholder="Nguyễn Văn A" autocomplete="name">
                        @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="fqf-label" for="fqf_phone">Số điện thoại <span class="text-red-500">*</span></label>
                        <input type="tel" id="fqf_phone" name="phone" x-model="formData.phone"
                            class="fqf-input @error('phone') border-red-400 @enderror"
                            placeholder="09x xxx xxxx" autocomplete="tel">
                        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="fqf-label" for="fqf_email">Email</label>
                        <input type="email" id="fqf_email" name="email" x-model="formData.email"
                            class="fqf-input" placeholder="email@example.com" autocomplete="email">
                    </div>
                    <div>
                        <label class="fqf-label" for="fqf_city">Tỉnh / Thành phố <span class="text-red-500">*</span></label>
                        <input type="text" id="fqf_city" name="province_city" x-model="formData.province_city"
                            class="fqf-input @error('province_city') border-red-400 @enderror"
                            placeholder="TP.HCM, Hà Nội...">
                        @error('province_city')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="fqf-label" for="fqf_msg">Ghi chú thêm</label>
                    <textarea id="fqf_msg" name="message" rows="2" x-model="formData.message"
                        class="fqf-input" placeholder="Yêu cầu cụ thể, câu hỏi thêm..."></textarea>
                </div>

                {{-- Mini summary --}}
                <div x-show="formData.project_type || formData.area_m2 || formData.budget_range"
                    class="rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 text-sm">
                    <p class="mb-2 font-semibold text-surface-700">Tóm tắt yêu cầu:</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-if="formData.project_type">
                            <span class="inline-flex items-center gap-1 rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700"
                                x-text="{'nha_o':'Nhà ở','can_ho':'Căn hộ','van_phong':'Văn phòng','cua_hang':'Cửa hàng','showroom':'Showroom','nha_hang':'Nhà hàng','hoi_truong':'Hội trường','nha_xuong':'Nhà xưởng','truong_hoc':'Trường học','khach_san':'Khách sạn','khac':'Khác'}[formData.project_type]"></span>
                        </template>
                        <template x-if="formData.area_m2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                <span x-text="formData.area_m2"></span> m&sup2;
                            </span>
                        </template>
                        <template x-if="formData.budget_range">
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700"
                                x-text="{'duoi_20_trieu':'<20tr','20_40_trieu':'20-40tr','40_70_trieu':'40-70tr','tren_70_trieu':'>70tr','chua_ro':'Ngân sách chưa rõ'}[formData.budget_range]"></span>
                        </template>
                        @if($product)
                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ Str::limit($product->name, 30) }}</span>
                        @endif
                    </div>
                </div>

                {{-- Policy agreement --}}
                <x-policy-links display-location="lead_form" variant="checkbox" class="mt-3" />
            </div>

            <div class="fqf-nav">
                <button type="button" @click="prev()" class="fqf-btn-ghost">&larr; Quay lại</button>
                <button type="submit" :disabled="submitting" class="fqf-btn-accent"
                    :class="submitting ? 'opacity-70 cursor-not-allowed' : ''">
                    <template x-if="!submitting">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Gửi yêu cầu báo giá
                        </span>
                    </template>
                    <template x-if="submitting">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Đang gửi...
                        </span>
                    </template>
                </button>
            </div>
        </div>

    </form>
</div>

@once
@push('head')
<style>
.fqf-wrap { }
.fqf-step-title { margin:0 0 .25rem; font-size:1.0625rem; font-weight:700; color:#111827; }
.fqf-step-sub { margin:0 0 1.25rem; font-size:.875rem; color:#6b7280; }
.fqf-label { display:block; margin-bottom:.3rem; font-size:.8125rem; font-weight:600; color:#374151; }
.fqf-optional { font-weight:400; color:#9ca3af; }
.fqf-input { display:block; width:100%; border-radius:.625rem; border:1.5px solid #e2e8f0; padding:.625rem .875rem; font-size:.875rem; outline:none; transition:border-color .15s, box-shadow .15s; background:#fff; }
.fqf-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.fqf-radio-card { display:flex; cursor:pointer; align-items:center; justify-content:center; border-radius:.75rem; border:1.5px solid #e2e8f0; padding:.625rem .75rem; transition:all .15s; background:#fff; text-align:center; }
.fqf-radio-card:hover { border-color:#93c5fd; }
.fqf-radio-card--active { border-color:#2563eb; background:#eff6ff; color:#1d4ed8; }
.fqf-nav { display:flex; justify-content:space-between; margin-top:1.5rem; gap:.75rem; }
.fqf-btn-primary { display:inline-flex; align-items:center; gap:.375rem; border-radius:.75rem; background:#2563eb; padding:.625rem 1.5rem; font-size:.875rem; font-weight:700; color:#fff; border:none; cursor:pointer; transition:background .15s; }
.fqf-btn-primary:hover { background:#1d4ed8; }
.fqf-btn-accent { display:inline-flex; align-items:center; gap:.375rem; border-radius:.75rem; background:linear-gradient(135deg,#f97316,#ea580c); padding:.625rem 1.75rem; font-size:.9375rem; font-weight:700; color:#fff; border:none; cursor:pointer; box-shadow:0 2px 8px rgba(249,115,22,.3); transition:all .15s; }
.fqf-btn-accent:hover:not(:disabled) { box-shadow:0 4px 12px rgba(249,115,22,.4); transform:translateY(-1px); }
.fqf-btn-ghost { display:inline-flex; align-items:center; gap:.375rem; border-radius:.75rem; border:1.5px solid #e2e8f0; padding:.625rem 1.25rem; font-size:.875rem; font-weight:500; color:#4b5563; background:transparent; cursor:pointer; transition:background .15s; }
.fqf-btn-ghost:hover { background:#f8fafc; }
</style>
@endpush
@endonce
