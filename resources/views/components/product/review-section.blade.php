{{-- Product Review Section Component --}}
@props(['product', 'reviews', 'ratingStats', 'settings'])

@php
    $enabled       = $settings['enabled'] ?? true;
    $requirePhone  = $settings['require_phone'] ?? false;
    $allowImages   = $settings['allow_images'] ?? true;
    $maxImages     = (int) ($settings['max_images'] ?? 3);
    $uploadSettings = app(\App\Services\Settings\UploadSettingService::class);
    $maxSizeText   = $uploadSettings->formatMb($uploadSettings->reviewImageMaxSizeKb());
    $allowedImageTypes = $uploadSettings->allowedImageTypes();
    $showVerified  = $settings['show_verified_badge'] ?? true;
    // Re-open form on validation error or success flash (so user sees feedback)
    $formOpenInit  = ($errors->any() && old('_form') === 'review') ? 'true' : 'false';
@endphp

@if($enabled)
<section id="reviews" class="border-t border-surface-200 bg-surface-50 py-8 lg:py-12">
    <div class="container-main">
        {{-- Section header --}}
        <div class="mb-8 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            </div>
            <h2 class="text-xl font-bold text-surface-900 sm:text-2xl">Đánh giá sản phẩm</h2>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            {{-- ── Left: Rating Summary ── --}}
            <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                @if($ratingStats && $ratingStats['total'] > 0)
                    <div class="mb-4 text-center">
                        <div class="text-5xl font-bold text-surface-900">{{ $ratingStats['average'] }}</div>
                        <div class="mt-2 flex items-center justify-center">
                            <x-rating-stars :rating="$ratingStats['average']" size="md" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">{{ $ratingStats['total'] }} đánh giá</p>
                    </div>
                    {{-- Breakdown bars --}}
                    <div class="space-y-2">
                        @foreach($ratingStats['breakdown'] as $star => $data)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="w-8 text-right font-medium text-surface-600">{{ $star }}</span>
                                <svg class="h-4 w-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <div class="flex-1">
                                    <div class="h-2 overflow-hidden rounded-full bg-surface-100">
                                        <div class="h-full rounded-full bg-yellow-400 transition-all duration-500" style="width: {{ $data['percent'] }}%"></div>
                                    </div>
                                </div>
                                <span class="w-8 text-surface-400">{{ $data['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-surface-100">
                            <svg class="h-8 w-8 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        </div>
                        <p class="text-sm text-surface-500">Chưa có đánh giá nào.</p>
                        <p class="mt-1 text-xs text-surface-400">Hãy là người đầu tiên đánh giá sản phẩm này!</p>
                    </div>
                @endif

                {{-- Toggle button (Alpine controls visibility of form below) --}}
                <div class="mt-6" x-data>
                    <button
                        type="button"
                        @click="$dispatch('toggle-review-form')"
                        class="w-full rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white transition-all hover:bg-primary-700 hover:shadow-lg"
                        x-text="$store.reviewForm.open ? 'Đóng form đánh giá' : 'Gửi đánh giá'"
                    ></button>
                </div>
            </div>

            {{-- ── Right: Reviews List + Form ── --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Flash messages --}}
                @if(session('review_success'))
                    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                        {{ session('review_success') }}
                    </div>
                @endif

                {{-- Approved reviews list --}}
                @if($reviews->count() > 0)
                    <div class="space-y-4">
                        @foreach($reviews as $review)
                            <div class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-600">
                                            {{ mb_strtoupper(mb_substr($review->customer_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-surface-900">{{ $review->customer_name }}</p>
                                            @if($showVerified && $review->is_verified_purchase)
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                    Đã mua hàng
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="text-xs text-surface-400">{{ $review->created_at->diffForHumans() }}</span>
                                </div>

                                <div class="mt-3">
                                    <x-rating-stars :rating="$review->rating" size="sm" />
                                </div>

                                <p class="mt-3 text-sm leading-relaxed text-surface-600">{{ $review->content }}</p>

                                {{-- Images — only shown for approved reviews --}}
                                @if($review->status === 'approved' && !empty($review->image_urls))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($review->image_urls as $imgUrl)
                                            <a href="{{ $imgUrl }}" target="_blank" rel="noopener">
                                                <img
                                                    src="{{ $imgUrl }}"
                                                    alt="Ảnh đánh giá của {{ $review->customer_name }}"
                                                    class="h-20 w-20 rounded-lg border border-surface-200 object-cover transition hover:opacity-80"
                                                    loading="lazy"
                                                >
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Admin reply --}}
                                @if($review->admin_reply)
                                    <div class="mt-4 rounded-xl bg-primary-50 p-4">
                                        <div class="mb-1 flex items-center gap-2">
                                            <svg class="h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                            <span class="text-sm font-semibold text-primary-700">Phản hồi từ cửa hàng</span>
                                        </div>
                                        <p class="text-sm text-primary-800">{{ $review->admin_reply }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- ── Review Form (hidden by default, toggled via Alpine store) ── --}}
                <div
                    id="review-form"
                    x-data
                    x-show="$store.reviewForm.open"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-3"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-3"
                    class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm"
                    style="display:none"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-surface-900">Gửi đánh giá của bạn</h3>
                        <button
                            type="button"
                            @click="$store.reviewForm.open = false"
                            class="rounded-lg p-1.5 text-surface-400 transition hover:bg-surface-100 hover:text-surface-600"
                            aria-label="Đóng"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    @if($errors->any() && old('_form') === 'review')
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form
                        action="{{ route('product.review.store', $product->slug) }}"
                        method="POST"
                        enctype="multipart/form-data"
                        x-data="{ submitting: false }" @submit="if(submitting) { $event.preventDefault(); return; } submitting = true;"
                    >
                        @csrf
                        <input type="hidden" name="_form" value="review">
                        {{-- Honeypot --}}
                        <div style="display:none"><input type="text" name="honeypot" tabindex="-1" autocomplete="off"></div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="review_name" class="mb-1 block text-sm font-medium text-surface-700">Họ tên <span class="text-danger-500">*</span></label>
                                <input type="text" id="review_name" name="customer_name" value="{{ old('customer_name') }}" required
                                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                            </div>
                            <div>
                                <label for="review_phone" class="mb-1 block text-sm font-medium text-surface-700">
                                    Số điện thoại @if($requirePhone)<span class="text-danger-500">*</span>@endif
                                </label>
                                <input type="tel" id="review_phone" name="customer_phone" value="{{ old('customer_phone') }}" {{ $requirePhone ? 'required' : '' }}
                                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="review_email" class="mb-1 block text-sm font-medium text-surface-700">Email</label>
                            <input type="email" id="review_email" name="customer_email" value="{{ old('customer_email') }}"
                                class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                        </div>

                        {{-- Star rating (interactive) --}}
                        <div class="mt-4">
                            <label class="mb-2 block text-sm font-medium text-surface-700">Đánh giá <span class="text-danger-500">*</span></label>
                            <x-rating-stars :rating="old('rating', 5)" size="xl" interactive />
                        </div>

                        <div class="mt-4">
                            <label for="review_content" class="mb-1 block text-sm font-medium text-surface-700">Nội dung đánh giá <span class="text-danger-500">*</span></label>
                            <textarea id="review_content" name="content" rows="4" required minlength="10"
                                class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm này...">{{ old('content') }}</textarea>
                        </div>

                        @if($allowImages)
                            <div class="mt-4">
                                <label for="review_images" class="mb-1 block text-sm font-medium text-surface-700">
                                    Hình ảnh <span class="text-surface-400 font-normal">(tối đa {{ $maxImages }} ảnh, mỗi ảnh {{ $maxSizeText }})</span>
                                </label>
                                <input
                                    type="file"
                                    id="review_images"
                                    name="images[]"
                                    multiple
                                    accept="{{ implode(',', $allowedImageTypes) }}"
                                    class="w-full rounded-lg border border-surface-300 px-4 py-2 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100"
                                >
                                <p class="mt-1 text-xs text-surface-400">Định dạng hỗ trợ: {{ implode(', ', $uploadSettings->allowedImageExtensions()) }}</p>
                            </div>
                        @endif

                        <div class="mt-6 flex items-center gap-3">
                            <button type="submit" :disabled="submitting"
                                :class="submitting ? 'opacity-60 cursor-not-allowed' : 'hover:bg-primary-700 hover:shadow-lg'"
                                class="rounded-xl bg-primary-600 px-6 py-3 text-sm font-semibold text-white transition-all">
                                <span x-show="!submitting">Gửi đánh giá</span>
                                <span x-show="submitting" x-cloak>Đang gửi...</span>
                            </button>
                            <button type="button"
                                @click="$store.reviewForm.open = false"
                                class="rounded-xl border border-surface-300 px-6 py-3 text-sm font-medium text-surface-600 transition hover:bg-surface-100">
                                Hủy
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Alpine store for review form toggle --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('reviewForm', {
        open: {{ $formOpenInit }},
    });
    document.addEventListener('toggle-review-form', () => {
        Alpine.store('reviewForm').open = !Alpine.store('reviewForm').open;
        if (Alpine.store('reviewForm').open) {
            setTimeout(() => {
                const el = document.getElementById('review-form');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    });
});
</script>
@endif
