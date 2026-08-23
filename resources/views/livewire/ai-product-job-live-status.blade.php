@php
    $view = $status['view'] ?? ['label' => 'Chưa xác định', 'color' => 'gray'];
    $tone = match ($view['color'] ?? 'gray') {
        'success' => 'bg-success-50 text-success-700',
        'info' => 'bg-info-50 text-info-700',
        'warning' => 'bg-warning-50 text-warning-700',
        'danger' => 'bg-danger-50 text-danger-700',
        default => 'bg-gray-50 text-gray-700',
    };
@endphp

<div wire:poll.10s="refreshStatus" class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Tiến độ trực tiếp</div>
            <div class="text-xs text-gray-500">Cập nhật {{ $status['updated_human'] ?? 'chưa có' }}</div>
        </div>
        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $tone }}">{{ $view['label'] }}</span>
    </div>

    @if(!empty($status['warning']))
        <div class="rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-800">{{ $status['warning'] }}</div>
    @endif
    @if(!empty($status['safe_reason']))
        <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $status['safe_reason'] }}</div>
    @endif

    <div class="grid gap-2 text-sm sm:grid-cols-3 lg:grid-cols-6">
        <div><span class="text-gray-500">Đã xử lý</span><div class="font-semibold">{{ $status['processed'] ?? 0 }} / {{ $status['total'] ?? 0 }}</div></div>
        <div><span class="text-gray-500">Đang chạy</span><div class="font-semibold text-info-600">{{ $status['running'] ?? 0 }}</div></div>
        <div><span class="text-gray-500">Thành công</span><div class="font-semibold text-success-600">{{ $status['success'] ?? 0 }}</div></div>
        <div><span class="text-gray-500">Chờ duyệt</span><div class="font-semibold text-warning-600">{{ $status['review'] ?? 0 }}</div></div>
        <div><span class="text-gray-500">Bị chặn</span><div class="font-semibold text-warning-700">{{ $status['blocked'] ?? 0 }}</div></div>
        <div><span class="text-gray-500">Thất bại</span><div class="font-semibold text-danger-600">{{ $status['failed'] ?? 0 }}</div></div>
    </div>

    @if($status['percent'] !== null)
        <div>
            <div class="mb-1 flex justify-between text-xs text-gray-500"><span>Tiến độ batch</span><span>{{ $status['percent'] }}%</span></div>
            <div class="h-1.5 overflow-hidden rounded-full bg-gray-200"><div class="h-full bg-info-600" style="width: {{ $status['percent'] }}%"></div></div>
        </div>
    @endif

    @if($status['token_budget'] !== null)
        <div class="text-xs text-gray-500">
            Token: dùng {{ number_format((int) ($status['token_used'] ?? 0)) }} · giữ chỗ {{ number_format((int) ($status['token_reserved'] ?? 0)) }} · ngân sách {{ number_format((int) $status['token_budget']) }}
        </div>
    @endif
</div>
