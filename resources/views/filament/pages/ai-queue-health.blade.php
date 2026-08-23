<x-filament-panels::page>
    @php
        $workerHeartbeat = data_get($health, 'worker_heartbeat', []);
        $workerDesiredDisabled = data_get($health, 'worker_desired_state', 'DISABLED') === 'DISABLED';
        $workerActual = data_get($workerHeartbeat, 'health_status', 'OFFLINE');
        $acceptingJobs = (bool) data_get($workerHeartbeat, 'accepting_new_jobs', false);
        $workerState = $workerDesiredDisabled ? 'DISABLED' : ($workerActual === 'ONLINE' && $acceptingJobs ? 'HEALTHY' : 'WARNING');
        $schedulerState = data_get($health, 'scheduler_is_running') ? 'HEALTHY' : 'UNKNOWN';
        $stuck = (int) data_get($health, 'ai_jobs_stuck_count', 0);
        $pending = (int) data_get($health, 'pending_jobs_count', 0);
        $failed = (int) data_get($health, 'failed_jobs_count', 0);
        $processing = (int) data_get($health, 'ai_content_processing_count', 0) + (int) data_get($health, 'ai_product_processing_count', 0);
        $applicationRuntime = data_get($health, 'application_runtime', []);
        $workerRuntime = data_get($health, 'worker_runtime', []);
        $deploymentStatus = data_get($health, 'worker_deployment_status', 'UNKNOWN');
        $selfTest = data_get($health, 'worker_self_test', []);
    @endphp

    <div wire:poll.10s="reload" class="space-y-6">
        <div class="admin-card-grid admin-card-grid-3">
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">AI Worker</span>
                    <x-admin.status-badge :state="$workerState" />
                </div>
                <dl class="mt-3 space-y-2 text-xs text-gray-600 dark:text-gray-300">
                    <div class="flex justify-between gap-3"><dt>Xử lý AI</dt><dd class="font-medium">{{ $workerDesiredDisabled ? 'Tắt' : 'Bật' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Worker process</dt><dd class="font-medium">{{ $workerActual }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Nhận job mới</dt><dd class="font-medium">{{ $acceptingJobs ? 'Có' : 'Không' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Heartbeat</dt><dd class="font-medium">{{ data_get($workerHeartbeat, 'last_seen_human', 'Chưa ghi nhận') ?: 'Chưa ghi nhận' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Đang xử lý</dt><dd class="font-medium">{{ $processing }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Đang chờ</dt><dd class="font-medium">{{ $pending }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Đổi trạng thái</dt><dd class="font-medium">{{ data_get($health, 'worker_desired_changed_at', 'Chưa ghi nhận') ?: 'Chưa ghi nhận' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Triển khai</dt><dd class="font-medium">{{ $deploymentStatus === 'UP_TO_DATE' ? 'Đã cập nhật' : ($deploymentStatus === 'VERSION_MISMATCH' ? 'Lệch phiên bản' : 'Chưa xác minh') }}</dd></div>
                </dl>
                @if(!$workerDesiredDisabled && $workerActual !== 'ONLINE')
                    <p class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-xs text-warning-800">AI Worker chưa hoạt động. Yêu cầu chưa thể xử lý.</p>
                @elseif($workerDesiredDisabled && $workerActual === 'ONLINE')
                    <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700">Process đang online nhưng xử lý AI đã tắt; worker không nhận job mới.</p>
                @endif
            </div>
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">Hàng đợi</span>
                    <x-admin.status-badge :state="$stuck > 0 ? 'WARNING' : 'HEALTHY'" />
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Đang chờ: {{ $pending }} · Đang xử lý: {{ $processing }}</p>
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
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">AI watchdog: {{ data_get($health, 'watchdog_is_running') ? 'Online' : 'Chưa xác minh' }} · {{ data_get($health, 'watchdog_heartbeat', '-') }}</p>
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
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">Ứng dụng / Worker</span>
                    <x-admin.status-badge :state="$deploymentStatus === 'UP_TO_DATE' ? 'HEALTHY' : ($deploymentStatus === 'VERSION_MISMATCH' ? 'CRITICAL' : 'UNKNOWN')" />
                </div>
                <dl class="mt-3 space-y-2 text-xs text-gray-600 dark:text-gray-300">
                    <div class="flex justify-between gap-3"><dt>Web</dt><dd class="font-medium">v{{ data_get($applicationRuntime, 'app_version', '?') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Worker</dt><dd class="font-medium">v{{ data_get($workerRuntime, 'app_version', '?') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Môi trường</dt><dd class="font-medium">{{ data_get($applicationRuntime, 'environment', '?') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Queue</dt><dd class="font-medium">{{ data_get($applicationRuntime, 'queue_connection', '?') }} / {{ data_get($applicationRuntime, 'queue', '?') }}</dd></div>
                </dl>
            </div>
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">Kiểm tra Worker</span>
                    <x-admin.status-badge :state="data_get($selfTest, 'status') === 'COMPLETED' ? 'HEALTHY' : (data_get($selfTest, 'status') === 'FAILED' ? 'CRITICAL' : 'UNKNOWN')" :label="data_get($selfTest, 'status', 'Chưa chạy')" />
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Probe: {{ data_get($selfTest, 'probe_id', '-') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cross-process: {{ data_get($selfTest, 'cross_process') ? 'Đã chứng minh' : 'Chưa chứng minh' }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cập nhật: {{ data_get($selfTest, 'updated_at', '-') }}</p>
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
                <div><div class="admin-kv-label">Worker PID</div><div class="admin-kv-value">{{ data_get($workerHeartbeat, 'pid', '-') }}</div></div>
                <div><div class="admin-kv-label">Worker build</div><div class="admin-kv-value">{{ data_get($workerRuntime, 'build_id', 'Chưa ghi nhận') ?: 'Chưa ghi nhận' }}</div></div>
                <div><div class="admin-kv-label">Web build</div><div class="admin-kv-value">{{ data_get($applicationRuntime, 'build_id', 'Chưa ghi nhận') ?: 'Chưa ghi nhận' }}</div></div>
                <div><div class="admin-kv-label">Worker code hash</div><div class="admin-kv-value">{{ data_get($workerRuntime, 'worker_code_hash', 'Chưa ghi nhận') ?: 'Chưa ghi nhận' }}</div></div>
                <div><div class="admin-kv-label">Worker DB</div><div class="admin-kv-value">{{ data_get($workerRuntime, 'database_connection', '?') }} / {{ data_get($workerRuntime, 'database_name', '?') }}</div></div>
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
