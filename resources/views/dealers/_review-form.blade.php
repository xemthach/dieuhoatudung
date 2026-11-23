<div data-dealer-review-panel>
    <p data-review-success role="status" hidden class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-900"></p>
    <form method="POST" action="{{ route('dealers.review.store', $dealer->slug) }}" data-dealer-review-form class="grid content-start gap-4 rounded-2xl border border-surface-200 bg-white p-5 sm:p-6">
        @csrf
        <div><h2 class="text-lg font-bold text-surface-900">Viết đánh giá</h2><p class="mt-1 text-sm text-surface-600">Chia sẻ trải nghiệm thực tế của bạn. Email và điện thoại được giữ riêng tư.</p></div>
        <p data-review-error role="alert" hidden class="rounded-lg bg-red-50 p-3 text-sm text-red-800"></p>
        <label class="grid gap-1 text-sm font-medium text-surface-700">Họ tên *<input required name="customer_name" autocomplete="name" maxlength="255" value="{{ old('customer_name') }}" class="w-full min-w-0 rounded-lg border-surface-300"><span data-field-error="customer_name" class="text-red-700">@error('customer_name'){{ $message }}@enderror</span></label>
        <fieldset x-data="{ rating: {{ (int) old('rating', 0) }}, hover: 0 }">
            <legend class="text-sm font-medium text-surface-700">Xếp hạng *</legend>
            <div class="mt-2 flex flex-wrap gap-1" @mouseleave="hover = 0">
                @foreach(range(1, 5) as $star)
                    <label class="relative cursor-pointer rounded-lg p-2" @mouseenter="hover = {{ $star }}">
                        <input class="peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" type="radio" name="rating" value="{{ $star }}" aria-label="{{ $star }} sao" x-model="rating" required @checked((int) old('rating') === $star)>
                        <svg class="h-7 w-7 rounded peer-focus-visible:outline-2 peer-focus-visible:outline-primary-700" :class="(hover || Number(rating)) >= {{ $star }} ? 'text-amber-500' : 'text-surface-300'" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.5 2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96L12 2.5Z"/></svg>
                        <span class="sr-only">{{ $star }} sao</span>
                    </label>
                @endforeach
            </div>
            <p data-field-error="rating" class="text-sm text-red-700">@error('rating'){{ $message }}@enderror</p>
        </fieldset>
        <label class="grid gap-1 text-sm font-medium text-surface-700">Tiêu đề<input name="title" maxlength="255" value="{{ old('title') }}" class="w-full min-w-0 rounded-lg border-surface-300"><span data-field-error="title" class="text-red-700"></span></label>
        <label class="grid gap-1 text-sm font-medium text-surface-700">Nội dung *<textarea required name="content" minlength="10" maxlength="2000" rows="5" class="w-full min-w-0 rounded-lg border-surface-300">{{ old('content') }}</textarea><span data-field-error="content" class="text-red-700">@error('content'){{ $message }}@enderror</span></label>
        <div class="grid min-w-0 gap-4 sm:grid-cols-2">
            <label class="grid min-w-0 gap-1 text-sm font-medium">Email (tùy chọn)<input type="email" name="customer_email" autocomplete="email" value="{{ old('customer_email') }}" maxlength="255" class="w-full min-w-0 rounded-lg border-surface-300"><span data-field-error="customer_email" class="text-red-700"></span></label>
            <label class="grid min-w-0 gap-1 text-sm font-medium">Điện thoại (tùy chọn)<input type="tel" name="customer_phone" autocomplete="tel" value="{{ old('customer_phone') }}" maxlength="30" class="w-full min-w-0 rounded-lg border-surface-300"><span data-field-error="customer_phone" class="text-red-700"></span></label>
        </div>
        <label class="hidden" aria-hidden="true">Bỏ trống<input name="honeypot" tabindex="-1" autocomplete="off"></label>
        <button class="rounded-lg bg-primary-700 px-5 py-3 font-semibold text-white hover:bg-primary-800 disabled:opacity-50" type="submit">Gửi đánh giá</button>
    </form>
</div>
