@php($equipmentRules = $equipmentRules ?? [])
<div class="space-y-5">
    <div class="grid gap-3 md:grid-cols-2">
        @foreach($rules as $method => $rule)
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    {{ $method === 'area' ? 'Method A — Diện tích' : 'Method B — Thể tích' }}
                </div>
                <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $rule['version'] }}</div>
                <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ $rule['factor_unit'] }} · {{ $rule['status'] }}
                </div>
                @if($method === 'volume')
                    <div class="mt-1 text-xs text-gray-500">
                        {{ $rule['authority'] }} · mốc {{ $rule['reference_height_m'] }} m
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2">Loại không gian</th>
                    <th class="px-3 py-2">Hệ số</th>
                    <th class="px-3 py-2">Confidence</th>
                    <th class="px-3 py-2">Trạng thái</th>
                    <th class="px-3 py-2">Nguồn / lý do</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach($rules['area']['space_types'] as $space)
                    <tr>
                        <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $space['label_vi'] }}</td>
                        <td class="whitespace-nowrap px-3 py-2">{{ rtrim(rtrim(number_format($space['w_per_m2'], 3, '.', ''), '0'), '.') }} W/m²</td>
                        <td class="px-3 py-2">{{ $space['confidence'] }}</td>
                        <td class="px-3 py-2">{{ $space['activation'] === 'V2_CALIBRATED' ? 'V2 calibrated' : 'V1 retained' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $space['source'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500">
        Chỉ đọc. Adjustment nắng, thiết bị và số người chưa thay đổi; phạm vi double-count vẫn cần review riêng.
    </p>

    @if($equipmentRules !== [])
    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Quản trị gợi ý chủng loại — chỉ đọc</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2">Chủng loại</th>
                        <th class="px-3 py-2">Market reference</th>
                        <th class="px-3 py-2">Site catalog</th>
                        <th class="px-3 py-2">Confidence</th>
                        <th class="px-3 py-2">Rule status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach($equipmentRules as $rule)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $rule['label'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                {{ number_format($rule['market']['min_btu'] ?? 0) }}–{{ number_format($rule['market']['verified_max_btu'] ?? 0) }} BTU/h
                            </td>
                            <td class="px-3 py-2">
                                @if($rule['catalog']['count'])
                                    {{ number_format($rule['catalog']['min_btu']) }}–{{ number_format($rule['catalog']['max_btu']) }} BTU · {{ $rule['catalog']['count'] }} model
                                @else
                                    Không có model qua gate
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $rule['confidence'] }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $rule['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
