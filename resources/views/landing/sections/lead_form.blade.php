{{-- Lead Form Section --}}
<section class="bg-gradient-to-br from-primary-700 via-primary-800 to-primary-900 py-12 lg:py-16" id="landing-lead-form">
    <div class="container-main">
        <div class="mx-auto max-w-2xl text-center text-white">
            <h2 class="text-2xl font-bold sm:text-3xl">{{ $section->title ?? 'Nhận Báo Giá Miễn Phí' }}</h2>
            <p class="mt-2 text-primary-100">{{ $section->subtitle ?? 'Đội ngũ tư vấn phản hồi trong 30 phút' }}</p>
        </div>

        <div class="mx-auto mt-8 max-w-xl rounded-2xl bg-white p-6 shadow-2xl sm:p-8">
            <form action="{{ route('landing.lead') }}" method="POST" id="landing-lead-form-element">
                @csrf

                @error('__global')
                    <div class="mb-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-700">{{ $message }}</div>
                @enderror

                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="lead-name" class="mb-1 block text-sm font-medium text-surface-700">Họ tên *</label>
                            <input type="text" id="lead-name" name="name" required value="{{ old('name') }}" class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200" placeholder="Nguyễn Văn A">
                            @error('name') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="lead-phone" class="mb-1 block text-sm font-medium text-surface-700">Số điện thoại *</label>
                            <input type="tel" id="lead-phone" name="phone" required value="{{ old('phone') }}" class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200" placeholder="0912 345 678">
                            @error('phone') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="lead-email" class="mb-1 block text-sm font-medium text-surface-700">Email</label>
                        <input type="email" id="lead-email" name="email" value="{{ old('email') }}" class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200" placeholder="email@example.com">
                    </div>
                    <div>
                        <label for="lead-area" class="mb-1 block text-sm font-medium text-surface-700">Diện tích phòng (m²)</label>
                        <input type="text" id="lead-area" name="room_area" value="{{ old('room_area') }}" class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200" placeholder="50">
                    </div>
                    <div>
                        <label for="lead-note" class="mb-1 block text-sm font-medium text-surface-700">Ghi chú / Yêu cầu cụ thể</label>
                        <textarea id="lead-note" name="note" rows="3" class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200" placeholder="Mô tả nhu cầu...">{{ old('note') }}</textarea>
                    </div>
                    <input type="hidden" name="source_page" value="{{ url()->current() }}">
                    {{-- Honeypot: hidden from real users, bots will fill it --}}
                    <input type="text" name="website_url" value="" style="display:none!important" tabindex="-1" autocomplete="off">
                    <button type="submit" class="btn-accent w-full py-3.5 text-base">
                        <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Gửi yêu cầu báo giá
                    </button>
                </div>
            </form>

            @if(session('lead_success'))
                <div class="mt-4 rounded-lg bg-success-50 p-4 text-center text-sm text-success-700">
                     {{ session('lead_success') }}
                </div>
            @endif
        </div>
    </div>
</section>
