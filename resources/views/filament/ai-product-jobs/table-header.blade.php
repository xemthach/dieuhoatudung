<div class="space-y-4 p-1">
    <div class="admin-card-grid admin-card-grid-4">
        @foreach([
            ['label' => 'Đang chờ', 'value' => $summary['queued'] ?? 0, 'color' => 'gray'],
            ['label' => 'Đang xử lý', 'value' => $summary['processing'] ?? 0, 'color' => 'warning'],
            ['label' => 'Cần duyệt', 'value' => $summary['review'] ?? 0, 'color' => 'info'],
            ['label' => 'Hoàn thành', 'value' => $summary['completed'] ?? 0, 'color' => 'success'],
            ['label' => 'Lỗi / bị chặn', 'value' => $summary['failed'] ?? 0, 'color' => 'danger'],
        ] as $item)
            <div class="admin-status-card">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $item['label'] }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($item['value']) }}</div>
            </div>
        @endforeach
    </div>

    <details class="admin-technical-details rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <summary>Chính sách vận hành AI</summary>
        <div class="admin-kv-grid admin-kv-grid-4 mt-4">
            @foreach([
                'provider' => 'Nhà cung cấp',
                'model' => 'Model',
                'request_timeout_seconds' => 'Timeout',
                'max_attempts' => 'Số lần thử tối đa',
                'max_retries' => 'Retry',
                'worker_queue' => 'Hàng đợi',
                'hard_budget_mode' => 'Ngân sách cứng',
                'single_operator_policy' => 'Chính sách operator',
                'single_operator_auto_approve' => 'Tự duyệt',
                'single_operator_auto_apply' => 'Tự áp dụng',
            ] as $key => $label)
                <div class="min-w-0">
                    <div class="admin-kv-label">{{ $label }}</div>
                    <div class="admin-kv-value">
                        @if(is_bool($policy[$key] ?? null))
                            <x-admin.status-badge :state="($policy[$key] ?? false) ? 'READY' : 'DISABLED'" :label="($policy[$key] ?? false) ? 'Bật' : 'Tắt'" />
                        @elseif($key === 'request_timeout_seconds')
                            {{ $policy[$key] ?? '-' }} giây
                        @else
                            {{ $policy[$key] ?? '-' }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </details>
</div>
