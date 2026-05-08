<x-layouts.app :seoTitle="$seoTitle" :seoDescription="$seoDescription" :canonical="$canonical">

@push('head')
    {{-- Always noindex for compare page as it creates many duplicate combinations --}}
    <meta name="robots" content="noindex, follow">
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
        <form method="POST" action="{{ route('compare.clear') }}" class="shrink-0">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-100 hover:text-red-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Xóa tất cả
            </button>
        </form>
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
    
    <div class="relative overflow-x-auto rounded-2xl border border-surface-200 bg-white shadow-sm scrollbar-thin scrollbar-track-surface-100 scrollbar-thumb-surface-300">
        <table class="w-full min-w-[800px] text-left text-sm">
            {{-- HEADER: Hình ảnh & Tên --}}
            <thead class="bg-surface-50">
                <tr>
                    <th class="w-48 border-b border-surface-200 p-4 font-semibold text-surface-700">
                        Sản phẩm ({{ count($products) }}/4)
                    </th>
                    @foreach($products as $product)
                    <th class="w-64 border-b border-l border-surface-200 p-4 align-top">
                        <div class="relative flex h-full flex-col">
                            {{-- Nút xóa --}}
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
                {{-- Helper macro for generating rows --}}
                @php
                    $row = function($label, $group, $field, $isHtml = false) use ($products, $compareRows) {
                        echo '<tr class="group hover:bg-surface-50"><td class="border-b border-surface-200 p-4 font-medium text-surface-700 bg-white group-hover:bg-surface-50 sticky left-0 shadow-[1px_0_0_0_#e5e7eb] z-10">' . $label . '</td>';
                        foreach($products as $p) {
                            $val = $compareRows[$p->id][$group][$field] ?? '—';
                            if ($val === null || $val === '') $val = '—';
                            echo '<td class="border-b border-l border-surface-200 p-4 text-surface-600">' . ($isHtml ? $val : htmlspecialchars($val)) . '</td>';
                        }
                        for($i = count($products); $i < 4; $i++) {
                            echo '<td class="border-b border-l border-surface-200 p-4 bg-surface-50/50"></td>';
                        }
                        echo '</tr>';
                    };
                @endphp

                <tr class="bg-surface-100">
                    <td colspan="5" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-surface-600">Thông tin chung</td>
                </tr>
                
                @php $row('Thương hiệu', 'basic', 'brand') @endphp
                @php $row('Model', 'basic', 'model_code') @endphp
                @php $row('Mã SKU', 'basic', 'sku') @endphp
                @php $row('Danh mục', 'basic', 'category') @endphp
                @php $row('Tình trạng', 'basic', 'stock_status', true) @endphp
                @php $row('Bảo hành', 'basic', 'warranty', true) @endphp

                <tr class="bg-surface-100">
                    <td colspan="5" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-surface-600">Thông số kỹ thuật</td>
                </tr>
                
                @php $row('Công suất lạnh', 'technical', 'btu') @endphp
                @php $row('Công nghệ Inverter', 'technical', 'inverter', true) @endphp
                @php $row('Loại máy', 'technical', 'cooling_type') @endphp
                @php $row('Điện áp', 'technical', 'voltage') @endphp
                @php $row('Loại Gas', 'technical', 'refrigerant_gas') @endphp
                @php $row('Tiêu thụ điện', 'technical', 'power_consumption') @endphp
                @php $row('Lưu lượng gió', 'technical', 'airflow') @endphp
                @php $row('Độ ồn', 'technical', 'noise_level') @endphp

                <tr class="bg-surface-100">
                    <td colspan="5" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-surface-600">Kích thước & Trọng lượng</td>
                </tr>
                @php $row('Kích thước dàn lạnh', 'physical', 'indoor_dimensions') @endphp
                @php $row('Kích thước dàn nóng', 'physical', 'outdoor_dimensions') @endphp
                @php $row('Trọng lượng', 'physical', 'weight') @endphp
                @php $row('Ống đồng', 'physical', 'pipe_size') @endphp

            </tbody>
        </table>
    </div>
    
    @endif
</div>

</x-layouts.app>
