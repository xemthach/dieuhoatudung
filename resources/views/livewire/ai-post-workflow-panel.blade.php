@php
    $view = $status['view'] ?? ['label' => 'Chưa tạo', 'color' => 'gray'];
    $tone = match ($view['color'] ?? 'gray') {
        'success' => 'bg-success-50 text-success-700',
        'info' => 'bg-info-50 text-info-700',
        'warning' => 'bg-warning-50 text-warning-700',
        'danger' => 'bg-danger-50 text-danger-700',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<div wire:poll.10s="refreshStatus" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Trợ lý nội dung AI</div>
            <div class="mt-1 text-xs text-gray-500">Cập nhật {{ $status['updated_human'] ?? 'chưa có' }}</div>
        </div>
        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $tone }}">{{ $view['label'] ?? 'Chưa tạo' }}</span>
    </div>

    @if(!empty($status['warning']))
        <div class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-800">{{ $status['warning'] }}</div>
    @endif
    @if(!empty($status['safe_reason']))
        <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $status['safe_reason'] }}</div>
    @endif

    @if(!empty($status['step']['current']))
        <div class="mt-3 text-sm text-gray-700">Bước {{ $status['step']['current'] }}/{{ $status['step']['total'] }} · {{ $status['step']['label'] }}</div>
    @endif

    <div class="mt-3 grid gap-2 sm:grid-cols-2">
        @foreach($status['fields'] ?? [] as $field)
            <div class="flex justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 text-sm">
                <span>{{ $field['label'] }}</span><span class="font-medium">{{ $field['status']['label'] }}</span>
            </div>
        @endforeach
    </div>

    <div class="mt-3 grid gap-2 text-xs text-gray-600 sm:grid-cols-2">
        <div>Provider: {{ data_get($status, 'provider.name', 'Chưa cấu hình') }} · {{ data_get($status, 'provider.connection_label', 'Chưa kiểm tra') }}</div>
        <div>Provider request: {{ data_get($status, 'provider_request.label', 'Chưa gửi') }}</div>
        <div>Worker: {{ data_get($status, 'worker.desired_state', 'UNKNOWN') }} / {{ data_get($status, 'worker.health', 'UNKNOWN') }}</div>
        <div>Credit/Quota: {{ data_get($status, 'provider.quota_label', 'Không được provider cung cấp') }}</div>
    </div>

    @if($jobUrl)
        <div class="mt-4"><a href="{{ $jobUrl }}" class="text-sm font-semibold text-primary-600">Xem lịch sử AI</a></div>
    @endif
</div>
