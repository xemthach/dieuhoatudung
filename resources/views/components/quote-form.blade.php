@props([
    'product' => null,
    'calculatorContext' => null,
    'brandContext' => null,
    'categoryContext' => null,
    'entryContext' => 'direct',
    'submissionToken',
])

{{-- Canonical full quote form: need -> useful context -> contact. --}}
<div
    x-data="{
        step: {{ $errors->any() ? 3 : 1 }},
        totalSteps: 3,
        formData: {
            project_type: @js(old('project_type', '')),
            usage_description: @js(old('usage_description', '')),
            number_of_rooms: @js(old('number_of_rooms', '')),
            area_m2: @js(old('area_m2', $calculatorContext['area_m2'] ?? '')),
            ceiling_height: @js(old('ceiling_height', $calculatorContext['ceiling_height'] ?? '')),
            sun_exposure: @js(old('sun_exposure', !empty($calculatorContext['direct_sunlight']) ? 'nang_nhieu' : '')),
            current_aircon_status: @js(old('current_aircon_status', '')),
            budget_range: @js(old('budget_range', '')),
            installation_time: @js(old('installation_time', '')),
            need_installation_service: @js(old('need_installation_service', '')),
            full_name: @js(old('full_name', '')),
            phone: @js(old('phone', '')),
            email: @js(old('email', '')),
            province_city: @js(old('province_city', '')),
            message: @js(old('message', '')),
        },
        submitting: false,
        init() {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: 'quote_started', entry_context: @js($entryContext) });
        },
        progress() { return Math.round((this.step / this.totalSteps) * 100); },
        next() {
            if (this.step >= this.totalSteps) return;
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: 'quote_step_completed', step: this.step, entry_context: @js($entryContext) });
            this.step++;
            this.$nextTick(() => this.$refs.stepHeading?.focus());
        },
        prev() { if (this.step > 1) this.step--; },
    }"
    class="fqf-wrap"
    id="full-quote-form"
>
    <div class="mb-5" aria-live="polite">
        <div class="mb-1.5 flex items-center justify-between text-xs text-surface-500">
            <span>Bước <span x-text="step"></span> / <span x-text="totalSteps"></span></span>
            <span x-text="progress() + '% hành trình'"></span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-100" role="progressbar"
            :aria-valuenow="progress()" aria-valuemin="0" aria-valuemax="100">
            <div class="h-1.5 rounded-full bg-gradient-to-r from-primary-500 to-primary-600 transition-all duration-500"
                :style="'width:' + progress() + '%'"></div>
        </div>
        <div class="mt-1.5 flex justify-between text-[10px] text-surface-400">
            @foreach(['Nhu cầu', 'Thông tin hữu ích', 'Liên hệ'] as $i => $label)
                <span :class="step >= {{ $i + 1 }} ? 'text-primary-600 font-semibold' : ''">{{ $label }}</span>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('quote.store') }}" id="fqf-el"
        @submit="if (submitting) { $event.preventDefault(); return; } submitting = true;">
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
        <input type="hidden" name="entry_context" value="{{ $entryContext }}">
        <input type="hidden" name="lead_type" value="{{ $product ? 'product' : ($calculatorContext ? 'consultation' : 'general') }}">
        @if($product)
            <input type="hidden" name="product_id" value="{{ $product->id }}">
        @endif
        @if($brandContext)
            <input type="hidden" name="preferred_brand" value="{{ $brandContext['name'] }}">
        @endif
        <input type="hidden" name="source_page" value="{{ url()->current() }}">
        <input type="hidden" name="landing_page" value="{{ url()->current() }}">
        <input type="hidden" name="referrer" value="{{ request()->headers->get('referer', '') }}">
        @foreach(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid'] as $trackingField)
            <input type="hidden" name="{{ $trackingField }}" value="{{ request($trackingField) }}">
        @endforeach
        <div class="absolute -left-[9999px] h-0 overflow-hidden" aria-hidden="true">
            <input type="text" name="website_url" autocomplete="off" tabindex="-1">
        </div>

        <section x-show="step === 1" x-transition.opacity.duration.200ms aria-labelledby="quote-step-1">
            <h2 id="quote-step-1" tabindex="-1" x-ref="stepHeading" class="fqf-step-title">Anh/chị đang cần tư vấn cho không gian nào?</h2>
            <p class="fqf-step-sub">Chọn nếu biết; chúng tôi vẫn tiếp nhận yêu cầu khi chưa xác định rõ.</p>

            @if($product || $calculatorContext || $brandContext || $categoryContext)
                <div class="mb-4 rounded-xl border border-primary-200 bg-primary-50 p-3 text-sm text-primary-800">
                    @if($product)
                        <p class="font-semibold">Sản phẩm đang yêu cầu báo giá</p>
                        <p>{{ $product->name }}</p>
                        <p class="mt-1 text-xs text-primary-600">{{ $product->brand?->name }}@if($product->model_code) · {{ $product->model_code }}@endif</p>
                    @elseif($calculatorContext)
                        <p class="font-semibold">Đã dùng kết quả từ công cụ tính BTU</p>
                        <p>{{ number_format($calculatorContext['area_m2'] ?? 0, 1) }} m² · {{ number_format($calculatorContext['recommended_btu'] ?? 0) }} BTU</p>
                        @if(! empty($calculatorContext['requested_equipment_type_label']))
                            <p class="mt-1 text-xs text-primary-600">Loại máy mong muốn: {{ $calculatorContext['requested_equipment_type_label'] }}</p>
                        @endif
                        <p class="mt-1 text-xs text-primary-600">Dữ liệu này sẽ được chuyển thẳng cho Sales, không cần nhập lại.</p>
                    @elseif($brandContext)
                        <p class="font-semibold">Thương hiệu quan tâm: {{ $brandContext['name'] }}</p>
                    @elseif($categoryContext)
                        <p class="font-semibold">Nhóm sản phẩm quan tâm: {{ $categoryContext['name'] }}</p>
                    @endif
                </div>
            @endif

            <fieldset>
                <legend class="sr-only">Loại công trình</legend>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                    @foreach(\App\Models\QuoteRequest::projectTypeLabels() as $value => $label)
                        <label class="fqf-radio-card" :class="formData.project_type === '{{ $value }}' ? 'fqf-radio-card--active' : ''">
                            <input type="radio" name="project_type" value="{{ $value }}" x-model="formData.project_type" class="sr-only">
                            <span class="text-sm font-medium">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_9rem]">
                <div>
                    <label class="fqf-label" for="quote_usage">Mô tả nhu cầu <span class="fqf-optional">(không bắt buộc)</span></label>
                    <input id="quote_usage" type="text" name="usage_description" x-model="formData.usage_description"
                        class="fqf-input" maxlength="500" placeholder="Ví dụ: phòng khách, cửa hàng, thay máy cũ...">
                </div>
                <div>
                    <label class="fqf-label" for="quote_rooms">Số khu vực</label>
                    <input id="quote_rooms" type="number" name="number_of_rooms" x-model="formData.number_of_rooms"
                        min="1" max="500" inputmode="numeric" class="fqf-input" placeholder="Chưa rõ">
                </div>
            </div>

            <div class="fqf-nav"><span></span><button type="button" @click="next()" class="fqf-btn-primary">Tiếp theo &rarr;</button></div>
        </section>

        <section x-show="step === 2" x-transition.opacity.duration.200ms aria-labelledby="quote-step-2">
            <h2 id="quote-step-2" tabindex="-1" x-ref="stepHeading" class="fqf-step-title">Thông tin giúp tư vấn sát hơn</h2>
            <p class="fqf-step-sub">Tất cả câu hỏi ở bước này đều không bắt buộc.</p>

            @if($calculatorContext)
                <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                    <p class="font-semibold">Kết quả đã được giữ nguyên</p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <span>Phương pháp<br><strong>{{ ($calculatorContext['method'] ?? 'area') === 'volume' ? 'Thể tích' : 'Diện tích' }}</strong></span>
                        <span>Diện tích<br><strong>{{ number_format($calculatorContext['area_m2'] ?? 0, 1) }} m²</strong></span>
                        <span>Chiều cao<br><strong>{{ number_format($calculatorContext['ceiling_height'] ?? 0, 1) }} m</strong></span>
                        <span>Nhóm công suất<br><strong>{{ number_format($calculatorContext['recommended_btu'] ?? 0) }} BTU</strong></span>
                    </div>
                    @if(! empty($calculatorContext['requested_equipment_type_label']))
                        <p class="mt-2 text-xs">Loại máy mong muốn: <strong>{{ $calculatorContext['requested_equipment_type_label'] }}</strong></p>
                    @endif
                    <a href="{{ route('btu-calculator.index') }}" class="mt-3 inline-block text-xs font-semibold underline">Tính lại nếu cần</a>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="fqf-label" for="fqf_area">Diện tích ước tính <span class="fqf-optional">(nếu biết)</span></label>
                        <div class="relative">
                            <input type="number" id="fqf_area" name="area_m2" x-model="formData.area_m2"
                                min="1" max="50000" step="0.5" inputmode="decimal" placeholder="Ví dụ: 40"
                                class="fqf-input pr-12 @error('area_m2') border-red-400 @enderror">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-surface-400">m²</span>
                        </div>
                        <p class="mt-1 text-xs text-surface-500">Không cần chính xác tuyệt đối; để trống nếu chưa rõ.</p>
                        @error('area_m2')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="formData.area_m2">
                        <label class="fqf-label" for="fqf_height">Chiều cao trần <span class="fqf-optional">(nếu biết)</span></label>
                        <div class="relative">
                            <input type="number" id="fqf_height" name="ceiling_height" x-model="formData.ceiling_height"
                                min="1" max="20" step="0.1" inputmode="decimal" placeholder="Ví dụ: 3"
                                class="fqf-input pr-8">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-surface-400">m</span>
                        </div>
                    </div>
                </div>
            @endif

            <details class="mt-4 rounded-xl border border-surface-200 p-4">
                <summary class="cursor-pointer text-sm font-semibold text-surface-700">Thêm điều kiện không gian <span class="font-normal text-surface-400">(tùy chọn)</span></summary>
                <div class="mt-4 space-y-4">
                    <fieldset>
                        <legend class="fqf-label">Mức độ tiếp xúc nắng</legend>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['it_nang' => 'Ít nắng', 'nang_vua' => 'Nắng vừa', 'nang_nhieu' => 'Nắng nhiều'] as $value => $label)
                                <label class="fqf-radio-card" :class="formData.sun_exposure === '{{ $value }}' ? 'fqf-radio-card--active' : ''">
                                    <input type="radio" name="sun_exposure" value="{{ $value }}" x-model="formData.sun_exposure" class="sr-only">
                                    <span class="text-sm">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div>
                        <label class="fqf-label" for="quote_aircon">Điều hòa hiện tại</label>
                        <select id="quote_aircon" name="current_aircon_status" x-model="formData.current_aircon_status" class="fqf-input">
                            <option value="">Chọn nếu biết</option>
                            @foreach(\App\Models\QuoteRequest::airconStatusLabels() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </details>

            <details class="mt-3 rounded-xl border border-surface-200 p-4">
                <summary class="cursor-pointer text-sm font-semibold text-surface-700">Ngân sách & thời gian <span class="font-normal text-surface-400">(tùy chọn)</span></summary>
                <div class="mt-4 space-y-4">
                    <fieldset>
                        <legend class="fqf-label">Ngân sách dự kiến</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach(\App\Models\QuoteRequest::budgetRangeLabels() as $value => $label)
                                <label class="fqf-radio-card" :class="formData.budget_range === '{{ $value }}' ? 'fqf-radio-card--active' : ''">
                                    <input type="radio" name="budget_range" value="{{ $value }}" x-model="formData.budget_range" class="sr-only">
                                    <span class="text-sm">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="fqf-label" for="quote_timeline">Thời gian cần lắp</label>
                            <select id="quote_timeline" name="installation_time" x-model="formData.installation_time" class="fqf-input">
                                <option value="">Chọn nếu biết</option>
                                @foreach(\App\Models\QuoteRequest::installationTimeLabels() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fqf-label" for="quote_service">Phạm vi báo giá</label>
                            <select id="quote_service" name="need_installation_service" x-model="formData.need_installation_service" class="fqf-input">
                                <option value="">Chọn nếu biết</option>
                                @foreach(\App\Models\QuoteRequest::needInstallLabels() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </details>

            <div class="fqf-nav"><button type="button" @click="prev()" class="fqf-btn-ghost">&larr; Quay lại</button><button type="button" @click="next()" class="fqf-btn-primary">Tiếp theo &rarr;</button></div>
        </section>

        <section x-show="step === 3" x-transition.opacity.duration.200ms aria-labelledby="quote-step-3">
            <h2 id="quote-step-3" tabindex="-1" x-ref="stepHeading" class="fqf-step-title">Thông tin để chúng tôi liên hệ</h2>
            <p class="fqf-step-sub">Chỉ tên và số điện thoại là bắt buộc. Email và khu vực có thể bổ sung sau.</p>

            @if($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3" role="alert">
                    <p class="font-semibold text-red-800">Vui lòng kiểm tra thông tin sau:</p>
                    <ul class="mt-1 list-inside list-disc space-y-1 text-sm text-red-700">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="fqf-label" for="fqf_name">Anh/chị tên gì? <span class="text-red-500">*</span></label>
                        <input type="text" id="fqf_name" name="full_name" x-model="formData.full_name" required maxlength="100"
                            class="fqf-input @error('full_name') border-red-400 @enderror" placeholder="Nguyễn Văn A" autocomplete="name">
                        @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="fqf-label" for="fqf_phone">Số điện thoại <span class="text-red-500">*</span></label>
                        <input type="tel" id="fqf_phone" name="phone" x-model="formData.phone" required inputmode="tel" maxlength="20"
                            class="fqf-input @error('phone') border-red-400 @enderror" placeholder="09xx xxx xxx" autocomplete="tel">
                        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="fqf-label" for="fqf_email">Email <span class="fqf-optional">(không bắt buộc)</span></label>
                        <input type="email" id="fqf_email" name="email" x-model="formData.email" maxlength="150"
                            class="fqf-input @error('email') border-red-400 @enderror" placeholder="email@example.com" autocomplete="email">
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="fqf-label" for="fqf_city">Tỉnh / Thành phố <span class="fqf-optional">(không bắt buộc)</span></label>
                        <input type="text" id="fqf_city" name="province_city" x-model="formData.province_city" maxlength="100"
                            class="fqf-input" placeholder="TP.HCM, Hà Nội..." autocomplete="address-level1">
                    </div>
                </div>
                <div>
                    <label class="fqf-label" for="fqf_msg">Ghi chú thêm <span class="fqf-optional">(không bắt buộc)</span></label>
                    <textarea id="fqf_msg" name="message" rows="2" maxlength="2000" x-model="formData.message"
                        class="fqf-input" placeholder="Yêu cầu cụ thể hoặc thời gian thuận tiện để liên hệ..."></textarea>
                </div>

                <div x-show="formData.area_m2 || formData.budget_range" class="rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 text-sm">
                    <p class="mb-2 font-semibold text-surface-700">Tóm tắt yêu cầu</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-if="formData.area_m2"><span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700"><span x-text="formData.area_m2"></span> m²</span></template>
                        @if($calculatorContext)<span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ number_format($calculatorContext['recommended_btu'] ?? 0) }} BTU</span>@endif
                        @if($product)<span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ Str::limit($product->name, 30) }}</span>@endif
                    </div>
                </div>

                <x-policy-links display-location="lead_form" variant="checkbox" class="mt-3" />
            </div>

            <div class="fqf-nav">
                <button type="button" @click="prev()" class="fqf-btn-ghost">&larr; Quay lại</button>
                <button type="submit" :disabled="submitting" class="fqf-btn-accent" :class="submitting ? 'opacity-70 cursor-not-allowed' : ''">
                    <span x-show="!submitting">Gửi yêu cầu báo giá</span>
                    <span x-show="submitting" role="status">Đang gửi yêu cầu...</span>
                </button>
            </div>
        </section>
    </form>
</div>

@once
@push('head')
<style>
.fqf-step-title { margin:0 0 .25rem; font-size:1.0625rem; font-weight:700; color:#111827; outline:none; }
.fqf-step-sub { margin:0 0 1.25rem; font-size:.875rem; color:#6b7280; }
.fqf-label { display:block; margin-bottom:.3rem; font-size:.8125rem; font-weight:600; color:#374151; }
.fqf-optional { font-weight:400; color:#6b7280; }
.fqf-input { display:block; width:100%; min-height:2.75rem; border-radius:.625rem; border:1.5px solid #e2e8f0; padding:.625rem .875rem; font-size:.9375rem; outline:none; transition:border-color .15s, box-shadow .15s; background:#fff; }
.fqf-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.fqf-radio-card { display:flex; min-height:2.75rem; cursor:pointer; align-items:center; justify-content:center; border-radius:.75rem; border:1.5px solid #e2e8f0; padding:.625rem .75rem; transition:all .15s; background:#fff; text-align:center; }
.fqf-radio-card:hover { border-color:#93c5fd; }
.fqf-radio-card:focus-within { outline:2px solid #2563eb; outline-offset:2px; }
.fqf-radio-card--active { border-color:#2563eb; background:#eff6ff; color:#1d4ed8; }
.fqf-nav { display:flex; justify-content:space-between; margin-top:1.5rem; gap:.75rem; }
.fqf-btn-primary,.fqf-btn-accent,.fqf-btn-ghost { min-height:2.75rem; }
.fqf-btn-primary { display:inline-flex; align-items:center; gap:.375rem; border-radius:.75rem; background:#2563eb; padding:.625rem 1.5rem; font-size:.875rem; font-weight:700; color:#fff; border:none; cursor:pointer; }
.fqf-btn-primary:hover { background:#1d4ed8; }
.fqf-btn-accent { display:inline-flex; align-items:center; gap:.375rem; border-radius:.75rem; background:linear-gradient(135deg,#f97316,#ea580c); padding:.625rem 1.75rem; font-size:.9375rem; font-weight:700; color:#fff; border:none; cursor:pointer; box-shadow:0 2px 8px rgba(249,115,22,.3); }
.fqf-btn-ghost { display:inline-flex; align-items:center; gap:.375rem; border-radius:.75rem; border:1.5px solid #e2e8f0; padding:.625rem 1.25rem; font-size:.875rem; font-weight:500; color:#4b5563; background:transparent; cursor:pointer; }
@media (max-width: 390px) { .fqf-nav > button { flex:1; padding-left:.75rem; padding-right:.75rem; } }
</style>
@endpush
@endonce
