<div x-data="compareBar()" 
     class="hidden fixed bottom-0 left-0 right-0 z-[9999] border-t-2 border-primary-600 bg-white px-4 py-3 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.1)] sm:px-6 lg:px-8"
     :class="{'hidden': !show, 'block': show}">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
        
        <div class="flex items-center gap-4 overflow-x-auto pb-1 scrollbar-none sm:pb-0">
            <div class="shrink-0 text-sm font-semibold text-surface-700">
                So sánh <br class="hidden sm:block"> <span x-text="count" class="text-primary-600"></span>/4
            </div>
            
            <div class="flex gap-2">
                <template x-for="(item, index) in items" :key="item.slug || item">
                    <div class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded border border-surface-200 bg-white text-xs font-medium text-surface-400 sm:h-14 sm:w-14" :title="item.name || ''">
                        <template x-if="item.image_url">
                            <img :src="item.image_url" :alt="item.name" class="h-full w-full object-contain rounded p-1" />
                        </template>
                        <template x-if="!item.image_url">
                            <svg class="h-6 w-6 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </template>
                        
                        <button @click="removeItem(item.slug || item)" class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-100 text-red-600 hover:bg-red-200" title="Xóa">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
                
                {{-- Empty slots --}}
                <template x-for="i in (4 - count)" :key="'empty-'+i">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded border border-dashed border-surface-300 bg-surface-50/50 sm:h-14 sm:w-14">
                        <span class="text-xs text-surface-400">+</span>
                    </div>
                </template>
            </div>
        </div>
        
        <div class="flex shrink-0 items-center gap-3">
            <button @click="clearAll()" x-show="count > 0" class="hidden text-sm font-medium text-surface-500 hover:text-red-600 sm:block">
                Xóa hết
            </button>
            <a href="{{ route('compare.index') }}" class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-700 disabled:opacity-50" :class="{'opacity-50 pointer-events-none': count < 2}">
                So sánh ngay
            </a>
            <button @click="show = false" class="text-surface-400 hover:text-surface-600 sm:hidden">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
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
        
        init() {

            this.loadState();
            
            // Listen for global events
            window.addEventListener('compare-updated', (e) => {
                this.loadState();
            });
        },
        
        loadState() {
            @php
                $slugs = array_values(session('compare.products', []));
                $sessionItems = \App\Models\Product::whereIn('slug', $slugs)->get()->map(function($p) {
                    return [
                        'slug' => $p->slug,
                        'name' => $p->name,
                        'image_url' => $p->compare_image_url
                    ];
                })->toArray();
            @endphp
            let serverItems = {!! json_encode($sessionItems) !!};

            let stored = localStorage.getItem('compare.products');
            if (stored) {
                try {
                    let parsed = JSON.parse(stored);
                    if (!Array.isArray(parsed)) parsed = [];
                    // Migrate old string format to objects
                    this.items = parsed.map(i => {
                        if (typeof i === 'string') {
                            let sItem = serverItems.find(s => s.slug === i);
                            return sItem || {slug: i, name: i, image_url: ''};
                        }
                        return i;
                    });
                } catch (e) {
                    this.items = serverItems;
                }
                this.count = this.items.length;
                this.show = this.count > 0;
                localStorage.setItem('compare.products', JSON.stringify(this.items));
            } else {
                this.items = serverItems;
                this.count = this.items.length;
                this.show = this.count > 0;
                localStorage.setItem('compare.products', JSON.stringify(this.items));
            }

        },
        
        async removeItem(slug) {
            try {
                const res = await fetch('{{ route('compare.remove') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ slug: slug })
                });
                
                if (res.ok) {
                    this.items = this.items.filter(i => (typeof i === 'string' ? i : i.slug) !== slug);
                    this.count = this.items.length;
                    localStorage.setItem('compare.products', JSON.stringify(this.items));
                    if (this.count === 0) this.show = false;
                    
                    window.dispatchEvent(new CustomEvent('compare-updated'));
                }
            } catch (e) {
                console.error(e);
            }
        },
        
        async clearAll() {
            try {
                const res = await fetch('{{ route('compare.clear') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                
                if (res.ok) {
                    this.items = [];
                    this.count = 0;
                    this.show = false;
                    localStorage.setItem('compare.products', JSON.stringify([]));
                    window.dispatchEvent(new CustomEvent('compare-updated'));
                }
            } catch (e) {
                console.error(e);
            }
        }
    }
}

window.addToCompare = async function(slug) {
    let items = [];
    try {
        items = JSON.parse(localStorage.getItem('compare.products') || '[]');
        if (!Array.isArray(items)) items = [];
    } catch (e) {
        items = [];
    }
    
    if (items.some(i => (typeof i === 'string' ? i : i.slug) === slug)) {
        alert('Sản phẩm đã có trong danh sách so sánh.');
        return;
    }
    
    if (items.length >= 4) {
        alert('Bạn chỉ có thể so sánh tối đa 4 sản phẩm cùng lúc.');
        return;
    }
    
    try {
        const res = await fetch('{{ route('compare.add') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ slug: slug })
        });
        
        const data = await res.json();
        
        if (data.success) {
            items.push(data.product || slug);
            localStorage.setItem('compare.products', JSON.stringify(items));
            window.dispatchEvent(new CustomEvent('compare-updated'));
            
            // alert(data.message); // Optional success message
        } else {
            alert(data.message || 'Có lỗi xảy ra.');
        }
    } catch (e) {
        console.error(e);
        alert('Không thể kết nối đến server.');
    }
};
</script>
@endpush
@endonce
