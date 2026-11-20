@if($plan)
<div class="space-y-4">
    <p class="font-semibold">Bạn đang cập nhật {{ count($plan['ids']) }} sản phẩm.</p>
    @if(count($plan['ids']) >= 100)<p class="font-semibold text-warning-600">Batch lớn. Kiểm tra kỹ số lượng và giá trị trước khi xác nhận.</p>@endif
    <p>{{ $plan['reason'] }}</p>
    <ul>
        @foreach($plan['patch'] as $key => $value)
            <li>{{ \App\Services\Product\ProductBulkTechnicalService::FIELDS[$key][0] }} → {{ $value === null ? 'Xóa giá trị' : $value }}</li>
        @endforeach
        @foreach($plan['extras'] as $row)
            <li>{{ $row['operation'] === 'REMOVE' ? 'Xóa theo tên' : 'Thêm / cập nhật' }} {{ $row['key'] }} → {{ $row['value'] ?? '' }}</li>
        @endforeach
    </ul>
    <p>Xóa theo tên áp dụng cho dòng thủ công. Thêm / cập nhật thay toàn bộ giá trị của tên đó.</p>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr><th>Sản phẩm</th><th>Trường</th><th>Trước</th><th>Sau</th></tr></thead>
            <tbody>
            @foreach($plan['preview'] as $product)
                @foreach($product['after'] as $key => $value)
                    @if(isset(\App\Services\Product\ProductBulkTechnicalService::FIELDS[$key]) || $key === 'specs_json')
                        <tr>
                            <td class="p-2">#{{ $product['id'] }} {{ $product['model'] }}</td>
                            <td class="p-2">{{ \App\Services\Product\ProductBulkTechnicalService::FIELDS[$key][0] ?? 'Thông số mở rộng' }}</td>
                            <td class="p-2">{{ is_array($product['before'][$key]) ? json_encode(collect($product['before'][$key])->map(fn($r) => is_array($r) ? \Illuminate\Support\Arr::only($r, ['key','value']) : $r)->all(), JSON_UNESCAPED_UNICODE) : $product['before'][$key] }}</td>
                            <td class="p-2">{{ is_array($value) ? json_encode(collect($value)->map(fn($r) => is_array($r) ? \Illuminate\Support\Arr::only($r, ['key','value']) : $r)->all(), JSON_UNESCAPED_UNICODE) : $value }}</td>
                        </tr>
                    @endif
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
    @if(count($plan['ids']) > 10)<p>Còn {{ count($plan['ids']) - 10 }} sản phẩm trong batch.</p>@endif
</div>
@else
<p>Tạo lại bản xem trước.</p>
@endif
