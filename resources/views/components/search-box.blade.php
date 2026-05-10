{{-- Search Box Component — Alpine.js powered autocomplete --}}
@props(['variant' => 'hero'])

@php
    $isHero = $variant === 'hero' || $variant === 'homepage';
    $isHomepage = $variant === 'homepage';
    $isInline = $variant === 'inline';
@endphp

<div x-data="searchBox()" x-init="init()" @click.away="showDropdown = false" @keydown.escape.window="showDropdown = false"
     class="relative z-[100] w-full {{ $isHero ? 'max-w-2xl mx-auto' : ($isInline ? 'max-w-xl mx-auto' : 'max-w-md') }}"
     id="search-box">

    <form action="{{ route('search.index') }}" method="GET" @submit="onSubmit" class="relative" role="search" aria-label="Tìm kiếm sản phẩm">
        <div class="relative flex items-center">
            {{-- Search icon --}}
            <div class="pointer-events-none absolute left-4 text-surface-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <input
                type="text"
                name="q"
                x-model="query"
                @input.debounce.300ms="onInput"
                @keydown.arrow-down.prevent="moveDown"
                @keydown.arrow-up.prevent="moveUp"
                @keydown.enter.prevent="onEnter"
                @focus="onFocus"
                autocomplete="off"
                spellcheck="false"
                maxlength="80"
                placeholder="{{ $isInline ? 'Tìm model, SKU, thương hiệu...' : 'Nhập mã model, SKU hoặc tên sản phẩm cần tìm...' }}"
                class="@if($isHomepage) w-full rounded-2xl border border-white/20 bg-white py-3.5 pl-11 pr-24 text-sm text-surface-900 shadow-lg transition placeholder:text-surface-400 focus:ring-2 focus:ring-accent-400 focus:outline-none sm:py-4 sm:pl-12 sm:pr-36 sm:text-base
                       @elseif($isHero) w-full rounded-2xl border-0 bg-white/95 backdrop-blur-sm py-3.5 pl-11 pr-24 text-sm text-surface-900 shadow-xl ring-2 ring-white/20 transition placeholder:text-surface-400 focus:bg-white focus:ring-accent-400 focus:outline-none sm:py-4 sm:pl-12 sm:pr-36 sm:text-base
                       @elseif($isInline) w-full rounded-xl border border-surface-200 bg-white py-2.5 pl-11 pr-24 text-sm text-surface-900 shadow-sm transition placeholder:text-surface-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100 focus:outline-none
                       @else w-full rounded-xl border border-surface-200 bg-white py-3 pl-12 pr-28 text-sm text-surface-900 shadow-sm transition placeholder:text-surface-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100 focus:outline-none
                       @endif"
                id="search-input-{{ $variant }}"
                aria-controls="search-results-dropdown-{{ $variant }}"
                aria-autocomplete="list"
                :aria-expanded="showDropdown && results.length > 0"
            >

            {{-- Loading spinner --}}
            <div x-show="loading" x-cloak class="absolute {{ $isHero ? 'right-32 sm:right-36' : 'right-20 sm:right-24' }} flex items-center">
                <svg class="h-4 w-4 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </div>

            {{-- Clear button --}}
            <button type="button" x-show="query.length > 0" x-cloak @click="clearQuery"
                class="absolute {{ $isHero ? 'right-32 sm:right-36' : 'right-20 sm:right-24' }} rounded-full p-1 text-surface-400 transition hover:text-surface-600" aria-label="Xóa từ khóa">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <button type="submit"
                class="@if($isHomepage) absolute right-1.5 rounded-xl bg-accent-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-accent-600 active:scale-95 sm:right-2 sm:px-6 sm:py-2.5 sm:text-sm
                       @elseif($isHero) absolute right-1.5 rounded-xl bg-accent-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-accent-600 active:scale-95 sm:right-2 sm:px-6 sm:py-2.5 sm:text-sm
                       @elseif($isInline) absolute right-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-700 active:scale-95
                       @else absolute right-1.5 rounded-lg bg-primary-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-primary-700 active:scale-95
                       @endif"
                id="search-submit-btn-{{ $variant }}">
                <span class="sm:hidden">{{ $isInline ? 'Tìm' : 'Tìm' }}</span>
                <span class="hidden sm:inline">{{ $isInline ? 'Tìm' : 'Tìm sản phẩm' }}</span>
            </button>
        </div>
    </form>

    {{-- Suggestion hints (homepage and hero variants) --}}
    @if($isHero)
    <div class="mt-3 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-xs {{ $isHomepage ? 'text-primary-200' : 'text-primary-200/80' }}" x-show="!showDropdown">
        <span>Ví dụ:</span>
        <button type="button" @click="quickSearch('GCC24S6I')" class="rounded-full {{ $isHomepage ? 'bg-white/15 hover:bg-white/25' : 'bg-white/10 hover:bg-white/20' }} px-2.5 py-0.5 transition">GCC24S6I</button>
        <button type="button" @click="quickSearch('24000 BTU')" class="rounded-full {{ $isHomepage ? 'bg-white/15 hover:bg-white/25' : 'bg-white/10 hover:bg-white/20' }} px-2.5 py-0.5 transition">24.000 BTU</button>
        <button type="button" @click="quickSearch('Gree')" class="rounded-full {{ $isHomepage ? 'bg-white/15 hover:bg-white/25' : 'bg-white/10 hover:bg-white/20' }} px-2.5 py-0.5 transition">Gree</button>
        <button type="button" @click="quickSearch('cassette inverter')" class="rounded-full {{ $isHomepage ? 'bg-white/15 hover:bg-white/25' : 'bg-white/10 hover:bg-white/20' }} px-2.5 py-0.5 transition">cassette inverter</button>
    </div>
    @endif

    {{-- Dropdown results --}}
    <div x-show="showDropdown && (results.length > 0 || noResults)" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute left-0 right-0 top-full z-[9999] mt-2 max-h-[min(420px,60vh)] overflow-y-auto overscroll-contain rounded-2xl border border-surface-200 bg-white shadow-2xl ring-1 ring-black/5"
         id="search-results-dropdown-{{ $variant }}" role="listbox">

        {{-- Results --}}
        <template x-for="(item, index) in results" :key="item.id">
            <a :href="item.url" @mouseenter="activeIndex = index"
               :class="{'bg-primary-50': activeIndex === index}"
               class="flex items-center gap-3 px-4 py-3 transition hover:bg-surface-50 border-b border-surface-100 last:border-0"
               role="option" :aria-selected="activeIndex === index">
                {{-- Image --}}
                <div class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg border border-surface-100 bg-surface-50 p-0.5">
                    <img :src="item.image" :alt="item.name" class="h-full w-full object-contain" loading="lazy">
                </div>
                {{-- Info --}}
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-surface-900" x-text="item.name"></p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-surface-500">
                        <span x-show="item.brand" x-text="item.brand" class="font-medium text-primary-600"></span>
                        <span x-show="item.model" class="text-surface-300">|</span>
                        <span x-show="item.model" x-text="item.model"></span>
                        <span x-show="item.btu" class="text-surface-300">|</span>
                        <span x-show="item.btu" x-text="item.btu ? Number(item.btu).toLocaleString('vi') + ' BTU' : ''"></span>
                    </div>
                </div>
                {{-- Arrow --}}
                <svg class="h-4 w-4 flex-shrink-0 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </template>

        {{-- No results --}}
        <div x-show="noResults" class="px-6 py-8 text-center">
            <svg class="mx-auto mb-3 h-10 w-10 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm font-medium text-surface-700">Không tìm thấy sản phẩm phù hợp</p>
            <p class="mt-1 text-xs text-surface-500">Thử tìm bằng mã model, SKU hoặc thương hiệu</p>
        </div>

        {{-- View all link --}}
        <div x-show="results.length > 0" class="border-t border-surface-100 bg-surface-50/50">
            <a :href="'{{ route('search.index') }}?q=' + encodeURIComponent(query)"
               class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-primary-600 transition hover:bg-surface-100 hover:text-primary-700">
                Xem tất cả kết quả
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function searchBox() {
    return {
        query: new URLSearchParams(window.location.search).get('q') || '',
        results: [],
        loading: false,
        showDropdown: false,
        noResults: false,
        activeIndex: -1,
        abortController: null,

        init() {},

        async onInput() {
            const q = this.query.trim();
            if (q.length < 2) {
                this.results = [];
                this.showDropdown = false;
                this.noResults = false;
                return;
            }

            if (this.abortController) this.abortController.abort();
            this.abortController = new AbortController();

            this.loading = true;
            try {
                const res = await fetch(`/api/search/suggest?q=${encodeURIComponent(q)}`, {
                    signal: this.abortController.signal,
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error(res.status);
                const json = await res.json();
                this.results = json.data || [];
                this.noResults = this.results.length === 0;
                this.showDropdown = true;
                this.activeIndex = -1;
            } catch (e) {
                if (e.name !== 'AbortError') {
                    this.results = [];
                    this.noResults = true;
                    this.showDropdown = true;
                }
            } finally {
                this.loading = false;
            }
        },

        onFocus() {
            if (this.results.length > 0 || this.noResults) {
                this.showDropdown = true;
            }
        },

        onSubmit(e) {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                e.preventDefault();
                window.location.href = this.results[this.activeIndex].url;
            }
        },

        onEnter() {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                window.location.href = this.results[this.activeIndex].url;
            } else {
                this.$el.closest('form')?.submit();
            }
        },

        moveDown() {
            if (this.activeIndex < this.results.length - 1) {
                this.activeIndex++;
            } else {
                this.activeIndex = 0;
            }
        },

        moveUp() {
            if (this.activeIndex > 0) {
                this.activeIndex--;
            } else {
                this.activeIndex = this.results.length - 1;
            }
        },

        clearQuery() {
            this.query = '';
            this.results = [];
            this.showDropdown = false;
            this.noResults = false;
            this.$nextTick(() => {
                this.$el.querySelector('input[name=q]')?.focus();
            });
        },

        quickSearch(term) {
            this.query = term;
            this.onInput();
        }
    }
}
</script>
@endpush
@endonce
