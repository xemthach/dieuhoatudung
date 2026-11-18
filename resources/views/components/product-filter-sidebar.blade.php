@props(['brands', 'categories', 'currentCategory' => null])

@php
    $btuOptions = \App\Services\Product\ProductFilterService::CAPACITY_FILTER_OPTIONS;
    $selectedBrands = array_map('strval', (array) request('brand', []));
    $selectedBtu = array_map('strval', (array) request('btu', []));
@endphp

<form action="{{ url()->current() }}" method="GET" class="rounded-xl border border-surface-200 bg-white p-5">
    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-bold uppercase tracking-wider text-surface-900">Lọc sản phẩm</h3>
        @if(request()->except('page'))<a href="{{ url()->current() }}" class="text-xs font-medium text-primary-600">Xóa tất cả</a>@endif
    </div>
    <div class="mb-6 border-t border-surface-100 pt-4">
        <h4 class="mb-3 text-sm font-semibold">Danh mục</h4>
        <ul class="space-y-2">
            <li><a href="{{ route('products.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ !isset($currentCategory) ? 'bg-primary-50 text-primary-700' : 'text-surface-600' }}">Tất cả sản phẩm</a></li>
            @foreach($categories as $cat)
                <li><a href="{{ route('category.show', $cat->slug) }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ (isset($currentCategory) && $currentCategory->id === $cat->id) ? 'bg-primary-50 text-primary-700' : 'text-surface-600' }}"><span>{{ $cat->name }}</span><span class="text-xs">{{ $cat->products_count }}</span></a></li>
            @endforeach
        </ul>
    </div>
    @if(isset($brands) && $brands->isNotEmpty())
        <fieldset class="mb-6 border-t border-surface-100 pt-4">
            <legend class="mb-3 text-sm font-semibold">Thương hiệu</legend>
            <div class="space-y-2">@foreach($brands as $brand)<label class="flex cursor-pointer items-center gap-3"><input type="checkbox" name="brand[]" value="{{ $brand->slug }}" class="h-4 w-4 rounded" {{ in_array($brand->slug, $selectedBrands, true) ? 'checked' : '' }}><span class="flex-1 text-sm">{{ $brand->name }}</span><span class="text-xs text-surface-400">({{ $brand->products_count }})</span></label>@endforeach</div>
        </fieldset>
    @endif
    <fieldset class="mb-6 border-t border-surface-100 pt-4">
        <legend class="mb-3 text-sm font-semibold">Công suất marketing (BTU)</legend>
        <div class="space-y-2">@foreach($btuOptions as $val => $label)<label class="flex cursor-pointer items-center gap-3"><input type="checkbox" name="btu[]" value="{{ $val }}" class="h-4 w-4 rounded" {{ in_array((string) $val, $selectedBtu, true) ? 'checked' : '' }}><span class="text-sm">{{ $label }}</span></label>@endforeach</div>
    </fieldset>
    <fieldset class="mb-6 border-t border-surface-100 pt-4">
        <legend class="mb-3 text-sm font-semibold">Công nghệ</legend>
        <div class="space-y-2">@foreach(['' => 'Tất cả', '1' => 'Có Inverter', '0' => 'Không Inverter'] as $value => $label)<label class="flex cursor-pointer items-center gap-3"><input type="radio" name="inverter" value="{{ $value }}" {{ (string) request('inverter', '') === (string) $value ? 'checked' : '' }}><span class="text-sm">{{ $label }}</span></label>@endforeach</div>
    </fieldset>
    <fieldset class="mb-6 border-t border-surface-100 pt-4">
        <legend class="mb-3 text-sm font-semibold">Chế độ làm lạnh</legend>
        <div class="space-y-2">@foreach(['' => 'Tất cả', '1_chieu' => 'Điều hòa 1 chiều', '2_chieu' => 'Điều hòa 2 chiều'] as $value => $label)<label class="flex cursor-pointer items-center gap-3"><input type="radio" name="cooling_type" value="{{ $value }}" {{ (string) request('cooling_type', '') === $value ? 'checked' : '' }}><span class="text-sm">{{ $label }}</span></label>@endforeach</div>
    </fieldset>
    <fieldset class="mb-6 border-t border-surface-100 pt-4">
        <legend class="mb-3 text-sm font-semibold">Khoảng giá hiệu lực</legend>
        <div class="grid grid-cols-2 gap-2">
            <label class="text-xs">Từ<input type="number" min="0" step="1000" name="price_min" value="{{ request('price_min') }}" class="mt-1 w-full rounded-lg border-surface-300 text-sm" placeholder="0"></label>
            <label class="text-xs">Đến<input type="number" min="0" step="1000" name="price_max" value="{{ request('price_max') }}" class="mt-1 w-full rounded-lg border-surface-300 text-sm" placeholder="Không giới hạn"></label>
        </div>
    </fieldset>
    <label class="mb-6 flex cursor-pointer items-center gap-3 border-t border-surface-100 pt-4"><input type="checkbox" name="is_sale" value="1" class="h-4 w-4 rounded" {{ request('is_sale') === '1' ? 'checked' : '' }}><span class="text-sm font-medium">Đang khuyến mãi</span></label>
    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
    <button type="submit" class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white">Áp dụng bộ lọc</button>
</form>
