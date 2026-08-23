<x-filament-panels::page>
    @php
        $workerHeartbeat = data_get($health, 'worker_heartbeat', []);
        $workerDesiredDisabled = data_get($health, 'worker_desired_state', 'DISABLED') === 'DISABLED';
        $workerState = $workerDesiredDisabled ? 'DISABLED' : (data_get($workerHeartbeat, 'health_status') ?: 'UNKNOWN');
        $schedulerState = data_get($health, 'scheduler_is_running') ? 'HEALTHY' : 'UNKNOWN';
        $stuck = (int) data_get($health, 'ai_jobs_stuck_count', 0);
        $pending = (int) data_get($health, 'pending_jobs_count', 0);
        $failed = (int) data_get($health, 'failed_jobs_count', 0);
    @endphp

    <div wire:poll.10s="reload" class="space-y-6">
        <div class="admin-card-grid admin-card-grid-3">
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">AI Worker</span>
                    <x-admin.status-badge :state="$workerState" />
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Mong muốn: {{ $workerDesiredDisabled ? 'Đã tắt' : 'Đang bật' }} · Thực tế: {{ data_get($workerHeartbeat, 'health_status', 'OFFLINE') }}</p>
            </div>
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">Hàng đợi</span>
                    <x-admin.status-badge :state="$stuck > 0 ? 'WARNING' : 'HEALTHY'" />
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Đang chờ: {{ $pending }} · Đang xử lý: {{ (int) data_get($health, 'ai_content_processing_count', 0) + (int) data_get($health, 'ai_product_processing_count', 0) }}</p>
            </div>
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">Lịch sử lỗi</span>
                    <x-admin.status-badge :state="$failed > 0 ? 'WARNING' : 'HEALTHY'" :label="number_format($failed).' job'" />
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Job bị kẹt hiện tại: {{ $stuck }}</p>
            </div>
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">Scheduler</span>
                    <x-admin.status-badge :state="$schedulerState" />
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Heartbeat: {{ data_get($health, 'scheduler_heartbeat', 'Chưa ghi nhận') ?: 'Chưa ghi nhận' }}</p>
            </div>
            <div class="admin-status-card">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Kết nối queue</div>
                <div class="mt-1 text-lg font-semibold">{{ data_get($health, 'queue_connection', '-') }}</div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Queue được quản trị: ai_governed</p>
            </div>
            <div class="admin-status-card">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Job xử lý gần nhất</div>
                <div class="mt-1 text-sm font-semibold">{{ data_get($health, 'last_processed_job.event', 'Chưa ghi nhận') }}</div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ data_get($health, 'last_processed_job.created_at', '-') }}</p>
            </div>
        </div>

        @if(! data_get($health, 'scheduler_is_running'))
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning" heading="Scheduler chưa có heartbeat mới">
                <p class="text-sm text-gray-600 dark:text-gray-300">Khôi phục job stale có thể không tự chạy. Đây là trạng thái cần xác minh theo môi trường triển khai, không phải lỗi AI worker.</p>
            </x-filament::section>
        @endif

        <x-filament::section heading="Chi tiết vận hành" description="Thông tin phục vụ chẩn đoán, không phải luồng thao tác hằng ngày." collapsible collapsed>
            <div class="admin-kv-grid">
                <div><div class="admin-kv-label">Worker gần nhất</div><div class="admin-kv-value">{{ data_get($workerHeartbeat, 'worker_name', '-') }}</div></div>
                <div><div class="admin-kv-label">Heartbeat worker</div><div class="admin-kv-value">{{ data_get($workerHeartbeat, 'last_seen_at', '-') }}</div></div>
                <div><div class="admin-kv-label">AI bài viết đang xử lý</div><div class="admin-kv-value">{{ data_get($health, 'ai_content_processing_count', 0) }}</div></div>
                <div><div class="admin-kv-label">AI sản phẩm đang xử lý</div><div class="admin-kv-value">{{ data_get($health, 'ai_product_processing_count', 0) }}</div></div>
            </div>

            <details class="admin-technical-details mt-5">
                <summary>Lệnh vận hành</summary>
                <div class="admin-code-block mt-3">{{ data_get($health, 'worker_command') }}
{{ data_get($health, 'scheduler_command') }}</div>
            </details>

            <details class="admin-technical-details mt-5">
                <summary>Dữ liệu kỹ thuật</summary>
                <pre class="admin-code-block mt-3">{{ json_encode(['worker' => $workerHeartbeat, 'last_processed_job' => data_get($health, 'last_processed_job')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        </x-filament::section>
    </div>
</x-filament-panels::page>
