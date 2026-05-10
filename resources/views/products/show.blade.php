<x-layouts.app
    :seo-title="($product->seo_title ?? $product->name) . config('seo.defaults.title_suffix', '')"
    :seo-description="$product->seo_description ?? $product->short_description ?? ''"
    :canonical="route('product.show', $product->slug)"
    :og-title="$product->og_title ?? $product->name"
    :og-description="$product->og_description ?? $product->short_description ?? ''"
    :og-image="$product->og_image ? media_url($product->og_image) : $product->main_image_url"
    og-type="product"
>
    <x-breadcrumb :items="[
        ['label' => 'Điều hòa tủ đứng', 'url' => route('products.index')],
        ['label' => $product->category?->name ?? 'Sản phẩm', 'url' => $product->category ? route('category.show', $product->category->slug) : route('products.index')],
        ['label' => $product->name],
    ]" />

    @php
        $hasDiscount = $product->sale_price && $product->sale_price < $product->regular_price;
    @endphp

    <section class="py-8 lg:py-12">
        <div class="container-main">
            <div class="lg:grid lg:grid-cols-2 lg:gap-12">
                {{-- Product Image Gallery --}}
                @php
                    $images = $product->gallery_image_urls;
                    $imagesJson = json_encode($images, JSON_UNESCAPED_SLASHES);
                @endphp

                <div x-data="{
                        images: {{ $imagesJson }},
                        currentIndex: 0,
                        lightboxOpen: false,
                        get currentImage() {
                            return this.images.length > 0 ? this.images[this.currentIndex] : null;
                        }
                    }">
                    
                    <div class="relative overflow-hidden rounded-2xl border border-surface-200 bg-white cursor-pointer group" @click="if(images.length > 0) lightboxOpen = true">
                        @if(count($images) > 0)
                            <img
                                :src="currentImage"
                                alt="{{ $product->name }}"
                                class="aspect-square w-full object-contain p-8 transition-transform duration-300 group-hover:scale-105"
                            >
                        @endif

                        {{-- Badges --}}
                        <div class="absolute left-4 top-4">
                            <x-product-badges :product="$product" class="flex-col items-start gap-2" :limit="5" />
                        </div>
                    </div>

                    {{-- Gallery thumbnails --}}
                    @if(count($images) > 1)
                        <div class="mt-4 grid grid-cols-4 gap-3">
                            <template x-for="(img, index) in images" :key="index">
                                <button type="button" @click="currentIndex = index" :class="currentIndex === index ? 'ring-2 ring-primary-500' : 'ring-1 ring-surface-200 hover:ring-primary-300'" class="overflow-hidden rounded-lg bg-white transition-all">
                                    <img :src="img" alt="{{ $product->name }}" class="aspect-square w-full object-contain p-2" loading="lazy">
                                </button>
                            </template>
                        </div>
                    @endif

                    {{-- Lightbox Modal --}}
                    <template x-if="lightboxOpen">
                        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-surface-900/95 backdrop-blur-sm" @keydown.escape.window="lightboxOpen = false" x-cloak>
                            <button type="button" @click="lightboxOpen = false" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition-colors">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>

                            <template x-if="images.length > 1">
                                <button type="button" @click.stop="currentIndex = (currentIndex > 0) ? currentIndex - 1 : images.length - 1" class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20 transition-colors">
                                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                            </template>

                            <img :src="currentImage" class="max-h-[90vh] max-w-[90vw] object-contain" @click.away="lightboxOpen = false">

                            <template x-if="images.length > 1">
                                <button type="button" @click.stop="currentIndex = (currentIndex < images.length - 1) ? currentIndex + 1 : 0" class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20 transition-colors">
                                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Product Info --}}
                <div class="mt-6 lg:mt-0">
                    {{-- Brand --}}
                    @if($product->brand)
                        <p class="mb-2 text-sm font-medium uppercase tracking-wider text-primary-600">{{ $product->brand->name }}</p>
                    @endif

                    <h1 class="text-2xl font-bold text-surface-900 sm:text-3xl">{{ $product->name }}</h1>

                    {{-- SKU & Model --}}
                    <div class="mt-2 flex flex-wrap gap-3 text-sm text-surface-500">
                        @if($product->sku)
                            <span>SKU: <strong>{{ $product->sku }}</strong></span>
                        @endif
                        @if($product->model_code)
                            <span>Model: <strong>{{ $product->model_code }}</strong></span>
                        @endif
                    </div>

                    {{-- Price --}}
                    <div class="mt-6 rounded-xl border border-surface-200 bg-surface-50 p-5">
                        <div class="flex items-baseline gap-3">
                            @if($hasDiscount)
                                <span class="text-3xl font-bold text-danger-600">{{ number_format($product->sale_price) }}₫</span>
                                <span class="text-lg text-surface-400 line-through">{{ number_format($product->regular_price) }}₫</span>
                            @elseif($product->regular_price)
                                <span class="text-3xl font-bold text-surface-900">{{ number_format($product->regular_price) }}₫</span>
                            @else
                                <span class="text-xl font-bold text-primary-600">Liên hệ để nhận giá tốt nhất</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-surface-500">Đã bao gồm VAT. Miễn phí lắp đặt nội thành.</p>
                    </div>

                    {{-- Quick Specs --}}
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        @if($product->btu)
                            <div class="rounded-lg bg-white p-3 ring-1 ring-surface-200">
                                <p class="text-xs text-surface-500">Công suất</p>
                                <p class="text-sm font-semibold text-surface-800">{{ number_format($product->btu) }} BTU</p>
                            </div>
                        @endif
                        @if($product->inverter !== null)
                            <div class="rounded-lg bg-white p-3 ring-1 ring-surface-200">
                                <p class="text-xs text-surface-500">Công nghệ</p>
                                <p class="text-sm font-semibold text-surface-800">{{ $product->inverter ? 'Inverter' : 'Non-Inverter' }}</p>
                            </div>
                        @endif
                        @if($product->cooling_type)
                            <div class="rounded-lg bg-white p-3 ring-1 ring-surface-200">
                                <p class="text-xs text-surface-500">Kiểu</p>
                                <p class="text-sm font-semibold text-surface-800">{{ $product->cooling_type === '2_chieu' ? '2 chiều' : '1 chiều' }}</p>
                            </div>
                        @endif
                        @if($product->recommended_area)
                            <div class="rounded-lg bg-white p-3 ring-1 ring-surface-200">
                                <p class="text-xs text-surface-500">Diện tích</p>
                                <p class="text-sm font-semibold text-surface-800">{{ $product->recommended_area }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- CTA Buttons --}}
                    <div class="mt-6 flex flex-wrap gap-3"
                         x-data="{ quoteOpen: false }"
                         @keydown.escape.window="quoteOpen = false">

                        {{-- Nhận báo giá → mở modal --}}
                        <button type="button"
                            @click="quoteOpen = true; $dispatch('open-quote-modal')"
                            onclick="window.dataLayer=window.dataLayer||[];dataLayer.push({'event':'click_quote_button','product_name':'{{ e($product->name) }}','product_id':{{ $product->id }},'product_sku':'{{ e($product->sku ?? '') }}'});"
                            class="btn-accent flex-1 py-3.5 text-center text-base">
                            <svg class="mr-2 inline-block h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Nhận báo giá
                        </button>

                        {{-- Gọi tư vấn --}}
                        <a href="tel:{{ setting('contact.hotline') }}"
                           onclick="dataLayer.push({'event':'click_call_button','product_name':'{{ e($product->name) }}','product_id':{{ $product->id }}});"
                           class="btn-primary flex-1 py-3.5 text-center text-base">
                            <svg class="mr-2 inline-block h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            Gọi tư vấn
                        </a>

                        {{-- So sánh --}}
                        <button type="button"
                            onclick="addToCompare('{{ $product->slug }}')"
                            class="btn-ghost flex-none px-4 py-3.5 text-center text-base transition hover:border-primary-300 hover:text-primary-600"
                            title="Thêm vào so sánh">
                            <svg class="inline-block h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Quick Quote Modal (mount tai day, an den khi can) --}}
                    <x-product-quote-modal :product="$product" />


                    {{-- Short description --}}
                    @if($product->short_description)
                        <div class="mt-6 rounded-xl border border-surface-200 bg-white p-5">
                            <p class="text-sm leading-relaxed text-surface-600">{{ $product->short_description }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Tabs: Mô tả / Thông số / Bảo hành --}}
    <section class="border-t border-surface-200 bg-white py-8 lg:py-12" x-data="{ tab: 'desc' }">
        <div class="container-main">
            <div class="border-b border-surface-200">
                <nav class="-mb-px flex gap-6 overflow-x-auto">
                    <button @click="tab = 'desc'" :class="tab === 'desc' ? 'border-primary-600 text-primary-600' : 'border-transparent text-surface-500 hover:text-surface-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition-colors">Mô tả chi tiết</button>
                    <button @click="tab = 'specs'" :class="tab === 'specs' ? 'border-primary-600 text-primary-600' : 'border-transparent text-surface-500 hover:text-surface-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition-colors">Thông số kỹ thuật</button>
                    <button @click="tab = 'warranty'" :class="tab === 'warranty' ? 'border-primary-600 text-primary-600' : 'border-transparent text-surface-500 hover:text-surface-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition-colors">Bảo hành & Lắp đặt</button>
                    @if($product->publicDocuments->count() > 0 || ($product->documents_json && is_array($product->documents_json) && count($product->documents_json) > 0))
                        <button @click="tab = 'docs'" :class="tab === 'docs' ? 'border-primary-600 text-primary-600' : 'border-transparent text-surface-500 hover:text-surface-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition-colors">Tài liệu kỹ thuật</button>
                    @endif
                </nav>
            </div>

            {{-- Tab: Mô tả (Collapsible) --}}
            <div x-show="tab === 'desc'" class="mt-6">
                <x-product.collapsible-description :content="$product->long_description" :settings="$descriptionSettings" />
            </div>

            {{-- Tab: Thông số --}}
            <div x-show="tab === 'specs'" x-cloak class="mt-6">
                <div class="overflow-hidden rounded-xl border border-surface-200">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-surface-100">
                            @if($product->btu)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700 w-1/3">Công suất</td><td class="px-4 py-3 text-surface-600">{{ number_format($product->btu) }} BTU</td></tr>@endif
                            @if($product->inverter !== null)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Inverter</td><td class="px-4 py-3 text-surface-600">{{ $product->inverter ? 'Có' : 'Không' }}</td></tr>@endif
                            @if($product->cooling_type)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Kiểu</td><td class="px-4 py-3 text-surface-600">{{ $product->cooling_type === '2_chieu' ? '2 chiều (Nóng/Lạnh)' : '1 chiều (Lạnh)' }}</td></tr>@endif
                            @if($product->voltage)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Điện áp</td><td class="px-4 py-3 text-surface-600">{{ $product->voltage }}</td></tr>@endif
                            @if($product->refrigerant_gas)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Gas lạnh</td><td class="px-4 py-3 text-surface-600">{{ $product->refrigerant_gas }}</td></tr>@endif
                            @if($product->power_consumption)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Tiêu thụ điện</td><td class="px-4 py-3 text-surface-600">{{ $product->power_consumption }}</td></tr>@endif
                            @if($product->airflow)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Lưu lượng gió</td><td class="px-4 py-3 text-surface-600">{{ $product->airflow }}</td></tr>@endif
                            @if($product->noise_level)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Độ ồn</td><td class="px-4 py-3 text-surface-600">{{ $product->noise_level }}</td></tr>@endif
                            @if($product->indoor_dimensions)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Kích thước dàn lạnh</td><td class="px-4 py-3 text-surface-600">{{ $product->indoor_dimensions }}</td></tr>@endif
                            @if($product->outdoor_dimensions)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Kích thước dàn nóng</td><td class="px-4 py-3 text-surface-600">{{ $product->outdoor_dimensions }}</td></tr>@endif
                            @if($product->weight)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Trọng lượng</td><td class="px-4 py-3 text-surface-600">{{ $product->weight }}</td></tr>@endif
                            @if($product->recommended_area)<tr><td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">Diện tích phù hợp</td><td class="px-4 py-3 text-surface-600">{{ $product->recommended_area }}</td></tr>@endif

                            {{-- Dynamic specs from JSON — grouped with Vietnamese labels --}}
                            @if($product->specs_json)
                                @php
                                    $groupedSpecs = \App\Support\ProductSpecLabel::groupSpecs($product->specs_json);
                                @endphp
                                @foreach($groupedSpecs as $groupLabel => $items)
                                    <tr>
                                        <td colspan="2" class="bg-primary-50 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-primary-700">{{ $groupLabel }}</td>
                                    </tr>
                                    @foreach($items as $item)
                                        <tr>
                                            <td class="bg-surface-50 px-4 py-3 font-medium text-surface-700">{{ $item['label'] }}</td>
                                            <td class="px-4 py-3 text-surface-600">{{ $item['value'] }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tab: Bảo hành --}}
            <div x-show="tab === 'warranty'" x-cloak class="prose prose-sm mt-6 max-w-none text-surface-700 sm:prose-base">
                @if($product->warranty_info)
                    <h3>Thông tin bảo hành</h3>
                    {!! $product->warranty_info !!}
                @endif
                @if($product->installation_note)
                    <h3>Lưu ý lắp đặt</h3>
                    {!! $product->installation_note !!}
                @endif

                {{-- Policy links for product detail --}}
                <x-policy-links display-location="product_detail" variant="detail" class="mt-6 not-prose" />

                @if(!$product->warranty_info && !$product->installation_note)
                    <p class="text-surface-400">Chưa có thông tin bảo hành & lắp đặt.</p>
                @endif
            </div>

            {{-- Tab: Tài liệu kỹ thuật --}}
            @if($product->publicDocuments->count() > 0 || ($product->documents_json && is_array($product->documents_json) && count($product->documents_json) > 0))
            <div x-show="tab === 'docs'" x-cloak class="mt-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($product->publicDocuments as $doc)
                        <div class="flex items-start gap-4 rounded-xl border border-surface-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-bold text-surface-900 line-clamp-2">{{ $doc->title ?? $doc->file_name }}</h4>
                                <p class="mt-1 flex items-center gap-2 text-xs text-surface-500">
                                    <span class="rounded bg-surface-100 px-1.5 py-0.5 font-medium uppercase">{{ $doc->document_type ?? 'DOCUMENT' }}</span>
                                    @if($doc->file_size)<span>{{ round($doc->file_size / 1024, 1) }} KB</span>@endif
                                </p>
                                <a href="{{ media_url($doc->file_path) }}" target="_blank" class="mt-3 inline-flex items-center text-sm font-semibold text-primary-600 hover:text-primary-700">
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Xem / Tải xuống
                                </a>
                            </div>
                        </div>
                    @endforeach

                    @if($product->documents_json && is_array($product->documents_json))
                        @foreach($product->documents_json as $docPath)
                            <div class="flex items-start gap-4 rounded-xl border border-surface-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-bold text-surface-900 line-clamp-2">{{ basename($docPath) }}</h4>
                                    <p class="mt-1 flex items-center gap-2 text-xs text-surface-500">
                                        <span class="rounded bg-surface-100 px-1.5 py-0.5 font-medium uppercase">FILE</span>
                                    </p>
                                    <a href="{{ media_url($docPath) }}" target="_blank" class="mt-3 inline-flex items-center text-sm font-semibold text-primary-600 hover:text-primary-700">
                                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Xem / Tải xuống
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            @endif
        </div>
    </section>

    {{-- Product Documents --}}
    @if($product->publicDocuments->isNotEmpty())
    <section class="border-t border-surface-200 py-8 lg:py-12">
        <div class="container-main">
            <x-section-heading title="Tài Liệu Kỹ Thuật" />
            <div class="mx-auto mt-6 max-w-4xl">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($product->publicDocuments as $doc)
                        <a href="{{ media_url($doc->file_path) }}" 
                           target="_blank" rel="noopener noreferrer"
                           class="group flex items-start gap-4 rounded-xl border border-surface-200 bg-white p-4 transition-all hover:border-primary-500 hover:shadow-md">
                            
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 transition-colors group-hover:bg-primary-100 group-hover:text-primary-700">
                                @if($doc->document_type === 'catalogue')
                                    <x-heroicon-o-book-open class="h-6 w-6" />
                                @elseif($doc->document_type === 'manual')
                                    <x-heroicon-o-document-text class="h-6 w-6" />
                                @elseif($doc->document_type === 'specs')
                                    <x-heroicon-o-clipboard-document-list class="h-6 w-6" />
                                @elseif($doc->document_type === 'installation')
                                    <x-heroicon-o-wrench-screwdriver class="h-6 w-6" />
                                @elseif($doc->document_type === 'warranty')
                                    <x-heroicon-o-shield-check class="h-6 w-6" />
                                @else
                                    <x-heroicon-o-document class="h-6 w-6" />
                                @endif
                            </div>
                            
                            <div class="min-w-0 flex-1">
                                <h4 class="text-sm font-bold text-surface-900 group-hover:text-primary-600 line-clamp-2">
                                    {{ $doc->title }}
                                </h4>
                                <div class="mt-1 flex items-center gap-2 text-xs text-surface-500">
                                    <span class="capitalize">{{ $doc->document_type }}</span>
                                    @if($doc->file_size)
                                        <span>&bull;</span>
                                        <span>{{ number_format($doc->file_size / 1024 / 1024, 2) }} MB</span>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="shrink-0 text-surface-300 transition-colors group-hover:text-primary-500">
                                <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @endif

    <x-testimonial-section :testimonials="$product->activeTestimonials" :product="$product" />

    <x-faq-section :faqs="$product->activeFaqs" title="Câu Hỏi Về Sản Phẩm" />

    {{-- Reviews Section (setting-controlled) --}}
    <x-product.review-section :product="$product" :reviews="$reviews" :rating-stats="$ratingStats" :settings="$reviewSettings" />

    {{-- Q&A Section (setting-controlled) --}}
    <x-product.question-section :product="$product" :questions="$questions" :settings="$questionSettings" />

    {{-- Related Products --}}
    @if($relatedProducts->isNotEmpty())
    <section class="border-t border-surface-200 py-8 lg:py-12">
        <div class="container-main">
            <x-section-heading title="Sản Phẩm Liên Quan" />
            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 lg:gap-6">
                @foreach($relatedProducts as $related)
                    <x-product-card :product="$related" />
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- Product Schema.org JSON-LD (via SchemaService — fixes @@type + price=0) --}}
    @push('schema')
    @php
        $schemaService = app(\App\Services\Schema\SchemaService::class);
    @endphp
    {!! \App\Services\Schema\SchemaService::toScript($schemaService->product($product, $ratingStats)) !!}
    {!! \App\Services\Schema\SchemaService::toScript($schemaService->breadcrumbs([
        ['label' => 'Trang chủ', 'url' => route('home')],
        ['label' => 'Sản phẩm', 'url' => route('products.index')],
        ['label' => $product->category?->name ?? 'Danh mục', 'url' => $product->category ? route('category.show', $product->category->slug) : route('products.index')],
        ['label' => $product->name],
    ])) !!}
    @endpush

    {{-- FAQ Schema from Q&A --}}
    @if($questions->count() > 0)
    @php
        $answeredQuestions = $questions->filter(fn($q) => !empty($q->answer));
    @endphp
    @if($answeredQuestions->count() > 0)
    @push('schema')
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "FAQPage",
        "mainEntity": [
            @foreach($answeredQuestions as $q)
            {
                "@@type": "Question",
                "name": "{{ e($q->question) }}",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "{{ e($q->answer) }}"
                }
            }@if(!$loop->last),@endif
            @endforeach
        ]
    }
    </script>
    @endpush
    @endif
    @endif

</x-layouts.app>

