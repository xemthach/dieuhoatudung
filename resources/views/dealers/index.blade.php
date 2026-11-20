<x-layouts.app :seo-title="$seo->getTitle()" :seo-description="$seo->getDescription()" :canonical="$seo->getCanonical()" :robots="$seo->getRobots()" :og-image="$seo->getOgImage()">
<section class="py-8">
<div class="container-main space-y-6" data-dealer-directory data-nearby-url="{{ route('dealers.nearby') }}" data-location-url="{{ route('dealers.locations') }}">
    <h1 class="text-2xl font-bold">{{ $region ? 'Đại lý tại '.$region->full_name : 'Tìm đại lý' }}</h1>
    @if($region?->introduction)<p>{{ $region->introduction }}</p>@endif
    @if($errors->any())<p class="text-red-700" role="alert">{{ $errors->first() }}</p>@endif
    <form method="GET" action="{{ route('dealers.index') }}" class="grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4" id="dealer-filters">
        <label class="text-sm">Tìm đại lý<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" class="mt-1 w-full rounded border-surface-300" type="search"></label>
        <label class="text-sm">Hệ địa chỉ<select name="scheme" class="mt-1 w-full rounded border-surface-300"><option value="current" @selected($filters['scheme']==='current')>Hiện hành</option><option value="legacy" @selected($filters['scheme']==='legacy')>Địa chỉ cũ</option></select></label>
        @foreach(['province'=>'Tỉnh/Thành','district'=>'Quận/Huyện cũ','ward'=>'Phường/Xã'] as $key=>$label)
            <label class="text-sm" @if($key==='district') data-legacy-only @endif>{{ $label }}<select name="{{ $key }}" data-selected="{{ $filters[$key] ?? '' }}" class="mt-1 w-full rounded border-surface-300"><option value="">Tất cả</option></select></label>
        @endforeach
        @foreach(['brand'=>[$brands,'Thương hiệu'],'category'=>[$categories,'Dòng sản phẩm'],'service'=>[$services,'Dịch vụ']] as $key=>[$items,$label])
            <label class="text-sm">{{ $label }}<select name="{{ $key }}" class="mt-1 w-full rounded border-surface-300"><option value="">Tất cả</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($filters[$key] ?? '') == $item->id)>{{ $item->name }}</option>@endforeach</select></label>
        @endforeach
        <label class="text-sm">Sắp xếp<select name="sort" class="mt-1 w-full rounded border-surface-300">@foreach(['featured'=>'Nổi bật','name'=>'Tên A–Z','rating'=>'Đánh giá cao'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['sort']??'featured')===$value)>{{ $label }}</option>@endforeach</select></label>
        <div class="flex items-end gap-4"><button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-white">Tìm đại lý</button><a href="{{ route('dealers.index') }}" class="text-sm underline">Xóa bộ lọc</a></div>
    </form>
    <div class="flex flex-wrap items-end gap-3">
        <button type="button" data-nearby class="rounded-lg bg-primary-600 px-4 py-2 text-white">Tìm đại lý gần tôi</button>
        <label class="text-sm">Bán kính<select data-radius class="ml-2 rounded border-surface-300">@foreach(config('dealers.radii') as $radius)<option value="{{ $radius }}" @selected($radius===20)>{{ $radius }} km</option>@endforeach<option value="all">Không giới hạn</option></select></label>
        <button type="button" data-toggle-map class="rounded-lg border px-4 py-2" aria-expanded="false">Bản đồ / Danh sách</button>
    </div>
    <p data-location-status role="status" class="text-sm text-surface-600"></p>
    <p data-result-count>{{ $dealers->total() }} đại lý</p>
    <div data-directory-map hidden>
        <p class="mb-2 text-sm">Bản đồ hiển thị đại lý có tọa độ trong kết quả hiện tại.</p>
        <x-dealer-map :markers="$dealers->filter(fn($d)=>$d->latitude !== null && $d->longitude !== null)->map(fn($d)=>app(\App\Http\Controllers\DealerController::class)->marker($d))->values()->all()" />
    </div>
    <div data-dealer-results>@include('dealers._cards')</div>
    <div data-dealer-pagination>{{ $dealers->links() }}</div>
    @if($regions->isNotEmpty())
    <section class="space-y-3"><h2 class="text-xl font-semibold">Đại lý theo khu vực</h2><div class="flex flex-wrap gap-3">
        @foreach($regions as $unit)
            @php
                $target = array_replace($filters, [$unit->kind=>$unit->id]);
                if($unit->kind==='province') unset($target['district'],$target['ward']);
                if($unit->kind==='district') unset($target['ward']);
            @endphp
            @php
                $regionUrl = route('dealers.index',$target);
                if(array_diff(array_keys($filters),['scheme','province','district']) === []) {
                    if($unit->kind === 'province') $regionUrl = $unit->scheme === 'current'
                        ? route('dealers.region',$unit->slug) : route('dealers.legacy-region',$unit->slug);
                    if($unit->kind === 'district') $regionUrl = route('dealers.legacy-region',[$unit->parent->slug,$unit->slug]);
                }
            @endphp
            <a class="rounded border bg-white px-3 py-2 text-sm" href="{{ $regionUrl }}">{{ $unit->full_name }} ({{ $unit->dealer_count }})</a>
        @endforeach
    </div></section>
    @endif
</div>
</section>
</x-layouts.app>
