<div x-data="compareBar()"
     x-init="init()"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-y-full opacity-0"
     x-transition:enter-end="translate-y-0 opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-y-0 opacity-100"
     x-transition:leave-end="translate-y-full opacity-0"
     x-cloak
     class="fixed bottom-0 left-0 right-0 z-[9998] border-t-2 border-primary-600 bg-white shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.1)]"
     :class="{'pb-safe': true}">

    <div class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-3 py-2 sm:gap-4 sm:px-6 sm:py-3 lg:px-8">

        {{-- Left: Count + Items --}}
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none sm:gap-4">
            {{-- Count label --}}
            <div class="shrink-0 text-xs font-semibold text-surface-700 sm:text-sm">
                So sánh
                <span x-text="count" class="text-primary-600"></span>/4
            </div>

            {{-- Product thumbnails --}}
            <div class="flex gap-1.5 sm:gap-2">
                <template x-for="(item, index) in items" :key="item.slug || item">
                    <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded border border-surface-200 bg-white sm:h-12 sm:w-12 lg:h-14 lg:w-14" :title="item.name || ''">
                        <template x-if="item.image_url">
                            <img :src="item.image_url" :alt="item.name" class="h-full w-full object-contain rounded p-0.5 sm:p-1" />
                        </template>
                        <template x-if="!item.image_url">
                            <svg class="h-5 w-5 opacity-50 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </template>

                        <button type="button" @click="removeItem(item.slug || item)" :disabled="busy" :aria-busy="busy" :class="{ 'opacity-50': busy }" :aria-label="'Xóa ' + (item.name || 'sản phẩm') + ' khỏi so sánh'" class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-100 text-red-600 hover:bg-red-200 sm:-right-2 sm:-top-2 sm:h-5 sm:w-5" title="Xóa">
                            <svg class="h-2.5 w-2.5 sm:h-3 sm:w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                {{-- Empty slots: show only on sm+ --}}
                <template x-for="i in Math.max(0, 4 - count)" :key="'empty-'+i">
                    <div class="hidden h-10 w-10 shrink-0 items-center justify-center rounded border border-dashed border-surface-300 bg-surface-50/50 sm:flex sm:h-12 sm:w-12 lg:h-14 lg:w-14">
                        <span class="text-xs text-surface-400">+</span>
                    </div>
                </template>
            </div>
        </div>

        {{-- Right: Actions --}}
        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <button type="button" @click="clearAll()" :disabled="busy" :aria-busy="busy" :class="{ 'opacity-50': busy }" x-show="count > 0" class="hidden text-xs font-medium text-surface-500 hover:text-red-600 sm:block sm:text-sm">
                Xóa hết
            </button>
            <a href="{{ route('compare.index') }}"
               class="inline-flex items-center justify-center whitespace-nowrap rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-primary-700 sm:rounded-xl sm:px-4 sm:py-2 sm:text-sm"
               :class="{'opacity-50 pointer-events-none': count < 1}">
                So sánh ngay
            </a>
            <button @click="dismiss()" class="text-surface-400 hover:text-surface-600 sm:hidden" title="Đóng">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

    </div>

    {{-- Toast notification --}}
    <div x-show="toast" x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         x-cloak
         class="absolute -top-10 left-1/2 -translate-x-1/2 rounded-lg bg-surface-800 px-4 py-1.5 text-xs font-medium text-white shadow-lg sm:text-sm">
        <span x-text="toastMsg"></span>
    </div>
</div>

@once
@push('scripts')
<script>
function compareBar() {
    return {
        show: false,
        items: [],
        count: 0,
        busy: false,
        toast: false,
        toastMsg: '',
        toastTimer: null,

        init() {
            // Load initial state from server-rendered data (baked at page load)
            this.loadInitialState();

            // Expose toast function globally for addToCompare
            window.__compareToast = (msg) => this.showToast(msg);

            // Expose state update function for addToCompare
            window.__compareBarUpdate = (items) => {
                this.items = items;
                this.count = items.length;
                this.show = this.count > 0;
                this.updateBodyPadding(this.show);
                if (this.count === 0) this.clearAllStorage();
                else localStorage.setItem('compare_items', JSON.stringify(this.items));
            };

            window.addEventListener('compare:updated', event => window.__compareBarUpdate(event.detail));
            window.addEventListener('compare:busy', event => { this.busy = event.detail; });

            // Manage body padding when bar visibility changes
            this.$watch('show', (val) => {
                this.updateBodyPadding(val);
            });
        },

        loadInitialState() {
            // Server-rendered data (single source of truth on page load)
            @php
                $sessionItems = app(\App\Services\Product\ProductCompareSession::class)->items(request()->session());
            @endphp
            let serverItems = {{ Illuminate\Support\Js::from($sessionItems) }};

            this.items = serverItems;
            this.count = this.items.length;

            if (this.count > 0) {
                this.show = true;
                localStorage.setItem('compare_items', JSON.stringify(this.items));
            } else {
                this.show = false;
                this.clearAllStorage();
            }
        },

        async removeItem(slug) {
            try {
                const data = await window.updateComparison('{{ route('compare.remove') }}', JSON.stringify({ slug }));
                if (data) this.showToast(data.message);
            } catch (_) {
                this.showToast('Không thể cập nhật so sánh. Vui lòng thử lại.');
            }
        },

        async clearAll() {
            try {
                const data = await window.updateComparison('{{ route('compare.clear') }}');
                if (data) this.showToast(data.message);
            } catch (_) {
                this.showToast('Không thể cập nhật so sánh. Vui lòng thử lại.');
            }
        },

        dismiss() {
            // Only hide visually — don't clear state
            this.show = false;
        },

        clearAllStorage() {
            localStorage.removeItem('compare_items');
            localStorage.removeItem('compare.products'); // Legacy key
            document.cookie = 'compare_products=; Max-Age=0; path=/;';
        },

        updateBodyPadding(visible) {
            const bar = this.$el;
            if (visible && bar) {
                this.$nextTick(() => {
                    const height = bar.offsetHeight || 64;
                    document.body.style.paddingBottom = height + 'px';
                });
            } else {
                document.body.style.paddingBottom = '';
            }
        },

        showToast(msg) {
            this.toastMsg = msg;
            this.toast = true;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toast = false; }, 2500);
        }
    }
}

/**
 * Global function to add a product to compare list.
 * Called from product cards and product detail pages.
 */
window.addToCompare = async function(slug) {
    try {
        const data = await window.updateComparison('{{ route('compare.add') }}', JSON.stringify({ slug }));
        if (data) window.__compareToast?.(data.message);
    } catch (error) {
        window.__compareToast?.(error.message || 'Không thể cập nhật so sánh.');
    }
};
</script>
@endpush
@endonce
