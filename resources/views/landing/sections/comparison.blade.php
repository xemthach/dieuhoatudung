{{-- Comparison / Price Table Section --}}
@if(isset($comparisonProducts) && $comparisonProducts->isNotEmpty())
<section class="border-y border-surface-200 bg-white py-12 lg:py-16" id="landing-comparison">
    <div class="container-main">
        <x-section-heading
            :title="$section->title ?? 'Bảng Giá Điều Hòa Tủ Đứng'"
            :subtitle="$section->subtitle ?? 'So sánh thông số kỹ thuật và giá các model phổ biến'"
        />

        <div class="mt-8 overflow-x-auto">
            <table class="w-full min-w-[700px] text-sm">
                <thead>
                    <tr class="border-b-2 border-primary-200 bg-primary-50">
                        <th class="px-4 py-3 text-left font-semibold text-primary-800">Model</th>
                        <th class="px-4 py-3 text-left font-semibold text-primary-800">Thương hiệu</th>
                        <th class="px-4 py-3 text-center font-semibold text-primary-800">BTU</th>
                        <th class="px-4 py-3 text-center font-semibold text-primary-800">Inverter</th>
                        <th class="px-4 py-3 text-right font-semibold text-primary-800">Giá bán</th>
                        <th class="px-4 py-3 text-center font-semibold text-primary-800"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100">
                    @foreach($comparisonProducts as $product)
                    <tr class="transition-colors hover:bg-surface-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('product.show', $product->slug) }}" class="font-medium text-surface-800 transition-colors hover:text-primary-600">
                                {{ $product->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-surface-600">{{ $product->brand?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-center text-surface-600">{{ $product->btu ? number_format($product->btu) : '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($product->inverter)
                                <span class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700">Có</span>
                            @else
                                <span class="text-surface-400">Không</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($product->sale_price && $product->sale_price < $product->regular_price)
                                <span class="font-bold text-danger-600">{{ number_format($product->sale_price) }}₫</span>
                            @elseif($product->regular_price)
                                <span class="font-bold text-surface-900">{{ number_format($product->regular_price) }}₫</span>
                            @else
                                <span class="text-primary-600">Liên hệ</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('product.show', $product->slug) }}" class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-primary-700">
                                Chi tiết
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 rounded-lg bg-accent-50 p-4 text-center text-sm text-accent-700">
            <strong> Lưu ý:</strong> Giá trên là giá tham khảo. Liên hệ để nhận giá ưu đãi tốt nhất cho dự án của bạn.
        </div>
    </div>
</section>
@endif
