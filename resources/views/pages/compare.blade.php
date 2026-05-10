<x-layouts.app :seoTitle="$seoTitle" :seoDescription="$seoDescription" :canonical="$canonical">

@push('head')
    {{-- Always noindex for compare page as it creates many duplicate combinations --}}
    <meta name="robots" content="noindex, follow">
    <style>
        /* Compare table: sticky first column */
        .compare-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .compare-table {
            min-width: 700px;
        }
        .compare-table .sticky-col {
            position: sticky;
            left: 0;
            z-index: 10;
            background-color: inherit;
        }
        .compare-table thead .sticky-col {
            z-index: 20;
        }
        .compare-table .sticky-col::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 1px;
            background: #e5e7eb;
        }
        /* Group header row */
        .compare-group-header td {
            font-weight: 700;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        /* Diff highlight */
        .compare-diff {
            background-color: #fef9c3 !important;
        }
        /* Tooltip for truncated text */
        .compare-cell-value {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (min-width: 1024px) {
            .compare-cell-value {
                max-width: 260px;
            }
        }
        .compare-cell-value:hover {
            white-space: normal;
            overflow: visible;
            word-break: break-word;
        }
        /* Smooth scroll indicator */
        .compare-table-wrap::-webkit-scrollbar { height: 6px; }
        .compare-table-wrap::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
        .compare-table-wrap::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 3px; }
        .compare-table-wrap::-webkit-scrollbar-thumb:hover { background: #64748b; }
    </style>
@endpush

<div class="container-main py-8 lg:py-12">
    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-surface-500" aria-label="Breadcrumb">
        <a href="/" class="hover:text-primary-600">Trang chủ</a>
        <span>/</span>
        <span class="text-surface-700 font-medium">So sánh sản phẩm</span>
    </nav>

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-surface-900 sm:text-3xl">So sánh điều hòa tủ đứng</h1>
            <p class="mt-2 text-surface-600">So sánh chi tiết thông số kỹ thuật, tính năng và giá bán để chọn sản phẩm phù hợp nhất.</p>
        </div>
        
        @if(count($products) > 0)
        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            {{-- Export buttons --}}
            @php
                $exportSlugs = collect($products)->pluck('slug')->join(',');
            @endphp
            <div class="flex items-center gap-2" x-data="{ exportOpen: false }">
                <div class="relative">
                    <button @click="exportOpen = !exportOpen" @click.away="exportOpen = false"
                        class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-4 py-2 text-sm font-medium text-surface-700 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-600 shadow-sm">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Xuất dữ liệu
                        <svg class="h-3 w-3 transition-transform" :class="exportOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="exportOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-2 w-48 rounded-xl border border-surface-200 bg-white py-1 shadow-lg z-30" x-cloak>
                        <a href="{{ route('compare.export.pdf', ['products' => $exportSlugs]) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-surface-700 hover:bg-surface-50 hover:text-primary-600 transition">
                            <svg class="h-4 w-4 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h1.5v1.5H11V12h1.5v4H11v-1.5H9.5V16H8v-4zm5 0h1.5v2.5H16V16h-3v-4zm-5 5h8v1H8v-1z"/></svg>
                            Xuất PDF
                        </a>
                        <a href="{{ route('compare.export.excel', ['products' => $exportSlugs]) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-surface-700 hover:bg-surface-50 hover:text-primary-600 transition">
                            <svg class="h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12l2 4 2-4h1.5L11 16.5 13.5 21H12l-2-4-2 4H6.5L9 16.5 6.5 12H8z"/></svg>
                            Xuất Excel
                        </a>
                        <a href="{{ route('compare.export.csv', ['products' => $exportSlugs]) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-surface-700 hover:bg-surface-50 hover:text-primary-600 transition">
                            <svg class="h-4 w-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 13h8v1H8zm0 2h8v1H8zm0 2h5v1H8z"/></svg>
                            Xuất CSV
                        </a>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('compare.clear') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-100 hover:text-red-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Xóa tất cả
                </button>
            </form>
        </div>
        @endif
    </div>

    @if(count($products) === 0)
    <div class="rounded-2xl border border-surface-200 bg-white p-12 text-center shadow-sm">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 text-surface-400">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        </div>
        <h2 class="mb-2 text-lg font-bold text-surface-900">Chưa có sản phẩm nào để so sánh</h2>
        <p class="mb-6 text-surface-600">Vui lòng chọn các sản phẩm bạn đang phân vân để xem bảng so sánh chi tiết.</p>
        <a href="{{ route('price-list') }}" class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-primary-700">
            Xem danh sách sản phẩm
        </a>
    </div>
    @else
    
    <div class="compare-table-wrap rounded-2xl border border-surface-200 bg-white shadow-sm">
        <table class="compare-table w-full text-left text-sm">
            {{-- HEADER: Product images & names --}}
            <thead class="bg-surface-50">
                <tr>
                    <th class="sticky-col w-48 border-b border-surface-200 p-4 font-semibold text-surface-700 bg-surface-50">
                        Sản phẩm ({{ count($products) }}/4)
                    </th>
                    @foreach($products as $product)
                    <th class="w-64 border-b border-l border-surface-200 p-4 align-top bg-surface-50">
                        <div class="relative flex h-full flex-col">
                            {{-- Remove button --}}
                            <form method="POST" action="{{ route('compare.remove') }}" class="absolute right-0 top-0">
                                @csrf
                                <input type="hidden" name="slug" value="{{ $product->slug }}">
                                <button type="submit" class="rounded-full bg-surface-100 p-1.5 text-surface-400 transition hover:bg-red-100 hover:text-red-600" title="Xóa khỏi so sánh">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>

                            <a href="{{ route('product.show', $product->slug) }}" class="group block flex-1 text-center">
                                <div class="mx-auto mb-3 h-32 w-32 overflow-hidden rounded-lg border border-surface-100 bg-white p-2">
                                    <img src="{{ $product->compare_image_url }}" alt="{{ $product->name }}" class="h-full w-full object-contain transition-transform group-hover:scale-105" loading="lazy">
                                </div>
                                <h3 class="font-bold text-surface-900 group-hover:text-primary-600 line-clamp-2" title="{{ $product->name }}">
                                    {{ $product->name }}
                                </h3>
                            </a>
                            
                            <div class="mt-4 text-center">
                                @if($product->sale_price)
                                    <div class="text-lg font-bold text-red-600">{{ number_format($product->sale_price, 0, ',', '.') }}đ</div>
                                    @if($product->regular_price && $product->regular_price > $product->sale_price)
                                        <div class="mt-1 flex items-center justify-center gap-2">
                                            <span class="text-xs text-surface-400 line-through">{{ number_format($product->regular_price, 0, ',', '.') }}đ</span>
                                            <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700">
                                                -{{ round((($product->regular_price - $product->sale_price) / $product->regular_price) * 100) }}%
                                            </span>
                                        </div>
                                    @endif
                                @elseif($product->regular_price)
                                    <div class="text-lg font-bold text-surface-900">{{ number_format($product->regular_price, 0, ',', '.') }}đ</div>
                                @else
                                    <div class="text-lg font-bold text-surface-500">Liên hệ</div>
                                @endif
                                
                                <div class="mt-3">
                                    <a href="{{ route('quote.index', ['product' => $product->slug]) }}" class="inline-block w-full rounded-lg bg-accent-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-accent-600">
                                        {{ setting('cta.quote_cta_text', 'Nhận báo giá') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </th>
                    @endforeach
                    
                    {{-- Empty columns to reach 4 --}}
                    @for($i = count($products); $i < 4; $i++)
                    <th class="w-64 border-b border-l border-surface-200 bg-surface-50/50 p-4 align-middle">
                        <div class="flex h-full flex-col items-center justify-center text-center">
                            <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-full border-2 border-dashed border-surface-300 bg-white text-surface-400">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <span class="text-sm font-medium text-surface-500">Thêm sản phẩm</span>
                            <a href="{{ route('price-list') }}" class="mt-3 text-xs text-primary-600 hover:underline">Về bảng giá</a>
                        </div>
                    </th>
                    @endfor
                </tr>
            </thead>
            
            <tbody>
                @php
                    $groupColors = [
                        'Thông tin chung'         => 'bg-slate-100 text-slate-700',
                        'Công suất & Hiệu suất'  => 'bg-blue-50 text-blue-800',
                        'Điện & Môi chất lạnh'    => 'bg-amber-50 text-amber-800',
                        'Dàn lạnh'                => 'bg-cyan-50 text-cyan-800',
                        'Mặt nạ (Panel)'          => 'bg-violet-50 text-violet-800',
                        'Dàn nóng'                => 'bg-orange-50 text-orange-800',
                        'Lắp đặt'                 => 'bg-emerald-50 text-emerald-800',
                        'Nguồn dữ liệu'          => 'bg-gray-100 text-gray-600',
                        'Thông số khác'           => 'bg-surface-100 text-surface-600',
                    ];
                @endphp

                @foreach($groupedSpecs as $groupLabel => $rows)
                    {{-- Group header --}}
                    @php $groupClass = $groupColors[$groupLabel] ?? 'bg-surface-100 text-surface-600'; @endphp
                    <tr class="compare-group-header">
                        <td colspan="{{ count($products) + 1 + (4 - count($products)) }}" class="px-4 py-2.5 {{ $groupClass }}">
                            {{ $groupLabel }}
                        </td>
                    </tr>

                    {{-- Spec rows --}}
                    @foreach($rows as $row)
                    <tr class="group hover:bg-surface-50 transition-colors">
                        <td class="sticky-col border-b border-surface-200 p-4 font-medium text-surface-700 bg-white group-hover:bg-surface-50 text-[13px]" title="{{ $row['label'] }}">
                            {{ $row['label'] }}
                        </td>
                        @foreach($row['values'] as $idx => $value)
                        <td class="border-b border-l border-surface-200 p-4 text-surface-600 text-[13px] {{ $row['differs'] && $value !== '—' ? 'compare-diff' : '' }}">
                            <span class="compare-cell-value inline-block" title="{{ $value }}">{{ $value }}</span>
                        </td>
                        @endforeach
                        @for($i = count($products); $i < 4; $i++)
                        <td class="border-b border-l border-surface-200 p-4 bg-surface-50/50"></td>
                        @endfor
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Mobile scroll hint --}}
    <div class="mt-3 flex items-center justify-center gap-2 text-xs text-surface-400 lg:hidden">
        <svg class="h-4 w-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        Vuốt ngang để xem thêm
    </div>
    
    @endif
</div>

</x-layouts.app>
