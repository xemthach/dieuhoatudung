{{-- Product Q&A Section Component --}}
@props(['product', 'questions', 'settings'])

@php
    $enabled = $settings['enabled'] ?? true;
    $requirePhone = $settings['require_phone'] ?? false;
@endphp

@if($enabled)
<section id="questions" class="border-t border-surface-200 py-8 lg:py-12">
    <div class="container-main">
        <div class="mb-8 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h2 class="text-xl font-bold text-surface-900 sm:text-2xl">Hỏi đáp về {{ $product->name }}</h2>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            {{-- Question Form --}}
            <div class="order-2 lg:order-1">
                <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-bold text-surface-900">Đặt câu hỏi</h3>
                    <p class="mb-4 text-sm text-surface-500">Bạn có thắc mắc về sản phẩm? Hãy đặt câu hỏi, chúng tôi sẽ phản hồi sớm nhất.</p>

                    @if(session('question_success'))
                        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                            {{ session('question_success') }}
                        </div>
                    @endif

                    @if($errors->any() && old('_form') === 'question')
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('product.question.store', $product->slug) }}" method="POST"
                          x-data="{ submitting: false }" @submit="if(submitting) { $event.preventDefault(); return; } submitting = true;">
                        @csrf
                        <input type="hidden" name="_form" value="question">
                        {{-- Honeypot --}}
                        <div style="display:none"><input type="text" name="honeypot" tabindex="-1" autocomplete="off"></div>

                        <div class="space-y-4">
                            <div>
                                <label for="q_name" class="mb-1 block text-sm font-medium text-surface-700">Họ tên <span class="text-danger-500">*</span></label>
                                <input type="text" id="q_name" name="customer_name" value="{{ old('customer_name') }}" required
                                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                            </div>

                            <div>
                                <label for="q_phone" class="mb-1 block text-sm font-medium text-surface-700">
                                    Số điện thoại @if($requirePhone)<span class="text-danger-500">*</span>@endif
                                </label>
                                <input type="tel" id="q_phone" name="customer_phone" value="{{ old('customer_phone') }}" {{ $requirePhone ? 'required' : '' }}
                                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                            </div>

                            <div>
                                <label for="q_email" class="mb-1 block text-sm font-medium text-surface-700">Email</label>
                                <input type="email" id="q_email" name="customer_email" value="{{ old('customer_email') }}"
                                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                            </div>

                            <div>
                                <label for="q_question" class="mb-1 block text-sm font-medium text-surface-700">Câu hỏi <span class="text-danger-500">*</span></label>
                                <textarea id="q_question" name="question" rows="4" required minlength="10"
                                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                    placeholder="Nhập câu hỏi của bạn về sản phẩm này...">{{ old('question') }}</textarea>
                            </div>

                            <button type="submit" :disabled="submitting"
                                :class="submitting ? 'opacity-60 cursor-not-allowed' : 'hover:bg-blue-700 hover:shadow-lg'"
                                class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition-all">
                                <span x-show="!submitting">Gửi câu hỏi</span>
                                <span x-show="submitting" x-cloak>Đang gửi...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Questions List --}}
            <div class="order-1 lg:order-2 lg:col-span-2">
                @if($questions->count() > 0)
                    <div class="space-y-4">
                        @foreach($questions as $q)
                            <div class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm">
                                {{-- Question --}}
                                <div class="flex items-start gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">
                                        H
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-surface-900">{{ $q->customer_name }}</span>
                                            <span class="text-xs text-surface-400">{{ $q->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="mt-1 text-sm text-surface-700">{{ $q->question }}</p>
                                    </div>
                                </div>

                                {{-- Answer --}}
                                @if($q->answer)
                                    <div class="mt-4 ml-11 rounded-xl bg-blue-50 p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                                TL
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-semibold text-blue-900">{{ setting('general.site_name', 'Cửa hàng') }}</span>
                                                    @if($q->answered_at)
                                                        <span class="text-xs text-blue-400">{{ $q->answered_at->diffForHumans() }}</span>
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-sm text-blue-800">{{ $q->answer }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-surface-300 bg-white py-12 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-surface-100">
                            <svg class="h-8 w-8 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <p class="text-surface-500">Chưa có câu hỏi nào cho sản phẩm này.</p>
                        <p class="mt-1 text-sm text-surface-400">Hãy là người đầu tiên đặt câu hỏi!</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endif
