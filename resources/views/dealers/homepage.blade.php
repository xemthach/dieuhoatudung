<section class="border-y border-surface-200 bg-surface-50 py-10 sm:py-12 lg:py-16" data-homepage-dealers>
    <div class="container-main space-y-6" data-dealer-directory data-home-dealers data-nearby-url="{{ route('dealers.nearby') }}"
        data-location-url="{{ route('dealers.locations') }}" data-home-mode="{{ $homepageDealerMode }}">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-primary-700">Đại lý & dịch vụ</p>
                <h2 class="mt-1 text-2xl font-bold text-surface-900 sm:text-3xl">{{ $homepageDealerTitle }}</h2>
                @if($homepageDealerSubtitle !== '')<p class="mt-2 max-w-2xl text-surface-600">{{ $homepageDealerSubtitle }}</p>@endif
            </div>
            <a href="{{ route('dealers.index') }}" class="shrink-0 text-sm font-semibold text-primary-700 hover:underline">Xem tất cả đại lý</a>
        </div>

        <div class="flex flex-col gap-3 rounded-xl border border-surface-200 bg-white p-4 sm:flex-row sm:items-end">
                <form method="GET" action="{{ route('home') }}" class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" data-dealer-region-form>
                    <select name="sort" class="hidden" aria-hidden="true"><option value="featured">Featured</option><option value="distance">Distance</option></select>
                    <input type="hidden" name="limit" value="{{ $homepageDealerLimit }}">
                    <input type="hidden" name="card_profile" value="homepage">
                    <label class="text-sm font-medium">Hệ địa giới
                        <select name="scheme" class="mt-1 w-full rounded-lg border-surface-300" data-location-select>
                            <option value="current" @selected(($homepageDealerFilters['scheme'] ?? 'current') === 'current')>Hiện hành</option>
                            <option value="legacy" @selected(($homepageDealerFilters['scheme'] ?? '') === 'legacy')>Địa chỉ ba cấp cũ</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">Tỉnh/Thành
                        <select name="province" data-location-select data-selected="{{ $homepageDealerFilters['province'] ?? '' }}" class="mt-1 w-full rounded-lg border-surface-300">
                            <option value="">Tất cả</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium" data-legacy-only @if(($homepageDealerFilters['scheme'] ?? 'current') !== 'legacy') hidden @endif>Quận/Huyện (địa chỉ cũ)
                        <select name="district" data-location-select data-selected="{{ $homepageDealerFilters['district'] ?? '' }}" class="mt-1 w-full rounded-lg border-surface-300" @disabled(($homepageDealerFilters['scheme'] ?? '') !== 'legacy')>
                            <option value="">Tất cả</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">Phường/Xã
                        <select name="ward" data-location-select data-selected="{{ $homepageDealerFilters['ward'] ?? '' }}" class="mt-1 w-full rounded-lg border-surface-300">
                            <option value="">Tất cả</option>
                        </select>
                    </label>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <button type="submit" class="rounded-lg border border-primary-600 px-4 py-2 font-semibold text-primary-700 hover:bg-primary-50">Xem đại lý theo khu vực</button>
                    </div>
                </form>

            @if($homepageDealerGeolocation)
                <div class="flex flex-wrap items-end gap-3">
                    <button type="button" data-nearby class="rounded-lg bg-primary-700 px-4 py-2 font-semibold text-white hover:bg-primary-800">📍 Tìm đại lý gần tôi</button>
                    <label class="text-sm font-medium">Bán kính
                        <select data-radius class="mt-1 rounded-lg border-surface-300">
                            @foreach(config('dealers.radii') as $radius)
                                <option value="{{ $radius }}" @selected((int) $radius === $homepageDealerRadius)>{{ $radius }} km</option>
                            @endforeach
                            <option value="all">Không giới hạn</option>
                        </select>
                    </label>
                </div>
            @endif
        </div>

        <p data-location-status role="status" aria-live="polite" class="min-h-5 text-sm text-surface-600"></p>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p data-result-count class="text-sm text-surface-600">{{ $homepageDealers->count() }} đại lý</p>
            <button type="button" data-toggle-map class="rounded-lg border border-surface-300 px-3 py-2 text-sm font-medium" aria-expanded="false">Bản đồ / Danh sách</button>
        </div>
        <div data-directory-map hidden>
            <p class="mb-2 text-sm text-surface-600">Bản đồ hiển thị các đại lý trong danh sách hiện tại.</p>
            <x-dealer-map :markers="$homepageDealers->filter(fn ($d) => $d->latitude !== null && $d->longitude !== null)->map(fn ($d) => [
                    'id' => $d->id, 'name' => $d->name, 'latitude' => $d->latitude, 'longitude' => $d->longitude,
                    'address' => $d->full_address, 'url' => route('dealers.show', $d->slug),
                    'rating' => $d->average_rating, 'distance_km' => $d->distance_km,
                ])->values()->all()" />
        </div>
        <div data-dealer-pagination hidden></div>
        <div data-dealer-results>
            @include('dealers._cards', ['dealers' => $homepageDealers, 'cardOptions' => $homepageDealerCardOptions])
        </div>
    </div>
</section>
