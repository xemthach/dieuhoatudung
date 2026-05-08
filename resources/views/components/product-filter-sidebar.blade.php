@props(['brands', 'categories', 'currentCategory' => null])

@php
    $btuOptions = [
        '9000-12000' => 'Dưới 12.000 BTU',
        '18000' => '18.000 BTU',
        '24000' => '24.000 BTU',
        '28000' => '28.000 BTU',
        '36000' => '36.000 BTU',
        '42000' => '42.000 BTU',
        '48000' => '48.000 BTU',
        '50000-100000' => 'Từ 50.000 BTU trở lên'
    ];
@endphp

<form id="productFilterForm" action="{{ url()->current() }}" method="GET" class="rounded-xl border border-surface-200 bg-white p-5">
    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-bold uppercase tracking-wider text-surface-900">Lọc sản phẩm</h3>
        @if(request()->except('page'))
            <a href="{{ url()->current() }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">Xóa lọc</a>
        @endif
    </div>

    {{-- Categories --}}
    <div class="mb-6 border-t border-surface-100 pt-4">
        <h4 class="mb-3 text-sm font-semibold text-surface-900">Danh mục</h4>
        <ul class="space-y-2">
            <li>
                <a href="{{ route('products.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ !isset($currentCategory) ? 'bg-primary-50 text-primary-700' : 'text-surface-600 hover:bg-surface-50' }}">
                    Tất cả sản phẩm
                </a>
            </li>
            @foreach($categories as $cat)
                <li>
                    <a href="{{ route('category.show', $cat->slug) }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ (isset($currentCategory) && $currentCategory->id === $cat->id) ? 'bg-primary-50 text-primary-700' : 'text-surface-600 hover:bg-surface-50' }}">
                        <span>{{ $cat->name }}</span>
                        <span class="rounded-full bg-surface-100 px-2 py-0.5 text-xs text-surface-500">{{ $cat->products_count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Brands --}}
    @if(isset($brands) && $brands->isNotEmpty())
        <div class="mb-6 border-t border-surface-100 pt-4">
            <h4 class="mb-3 text-sm font-semibold text-surface-900">Thương hiệu</h4>
            <div class="space-y-2">
                @foreach($brands as $brand)
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" class="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-600"
                            {{ in_array($brand->slug, request('brand', [])) ? 'checked' : '' }} onchange="document.getElementById('productFilterForm').submit()">
                        <span class="flex-1 text-sm text-surface-600">{{ $brand->name }}</span>
                        <span class="text-xs text-surface-400">({{ $brand->products_count }})</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- BTU --}}
    <div class="mb-6 border-t border-surface-100 pt-4">
        <h4 class="mb-3 text-sm font-semibold text-surface-900">Công suất (BTU)</h4>
        <div class="space-y-2">
            @foreach($btuOptions as $val => $label)
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="btu[]" value="{{ $val }}" class="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-600"
                        {{ in_array((string)$val, request('btu', [])) ? 'checked' : '' }} onchange="document.getElementById('productFilterForm').submit()">
                    <span class="text-sm text-surface-600">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Inverter --}}
    <div class="mb-6 border-t border-surface-100 pt-4">
        <h4 class="mb-3 text-sm font-semibold text-surface-900">Công nghệ</h4>
        <div class="space-y-2">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="inverter" value="" class="h-4 w-4 border-surface-300 text-primary-600 focus:ring-primary-600"
                    {{ request('inverter') === null ? 'checked' : '' }} onchange="document.getElementById('productFilterForm').submit()">
                <span class="text-sm text-surface-600">Tất cả</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="inverter" value="1" class="h-4 w-4 border-surface-300 text-primary-600 focus:ring-primary-600"
                    {{ request('inverter') === '1' ? 'checked' : '' }} onchange="document.getElementById('productFilterForm').submit()">
                <span class="text-sm text-surface-600">Có Inverter (Tiết kiệm điện)</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="inverter" value="0" class="h-4 w-4 border-surface-300 text-primary-600 focus:ring-primary-600"
                    {{ request('inverter') === '0' ? 'checked' : '' }} onchange="document.getElementById('productFilterForm').submit()">
                <span class="text-sm text-surface-600">Không Inverter (Máy cơ)</span>
            </label>
        </div>
    </div>

    {{-- Preserve sort --}}
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif
</form>
