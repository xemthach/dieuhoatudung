<x-filament-panels::page>
    @php
        $latestJob = \App\Models\R2SyncJob::query()->latest()->first();
        $latestScan = \App\Models\R2SyncJob::query()->where('mode', 'scan_only')->where('status', 'completed')->latest()->first();
        $latestUpload = \App\Models\R2SyncJob::query()->where('mode', 'upload_only')->latest()->first();
        $isR2Enabled = (bool) setting('r2_storage.r2_enabled', false);
        $localFiles = (int) ($latestScan?->total_files ?? 0);
        $syncedFiles = (int) ($latestUpload?->synced_files ?? 0);
        $failedFiles = (int) ($latestUpload?->failed_files ?? 0);
        $missingFiles = max(0, $localFiles - $syncedFiles);
        $statusLabels = [
            'pending' => 'Đang chờ', 'scanning' => 'Đang quét', 'syncing' => 'Đang tải lên',
            'replacing' => 'Đang thay URL', 'completed' => 'Hoàn thành',
            'completed_with_errors' => 'Hoàn thành có lỗi', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy',
        ];
    @endphp

    <div class="space-y-6">
        <div class="admin-card-grid admin-card-grid-3">
            <div class="admin-status-card">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">Cloudflare R2</span>
                    <x-admin.status-badge :state="$isR2Enabled ? 'READY' : 'DISABLED'" :label="$isR2Enabled ? 'Đang bật' : 'Đã tắt'" />
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Chế độ: {{ $isR2Enabled ? 'CDN' : 'Lưu trữ local' }}</p>
            </div>
            <div class="admin-status-card"><div class="admin-kv-label">Media local</div><div class="mt-1 text-2xl font-semibold">{{ number_format($localFiles) }}</div></div>
            <div class="admin-status-card"><div class="admin-kv-label">Đã đồng bộ</div><div class="mt-1 text-2xl font-semibold text-success-600">{{ number_format($syncedFiles) }}</div></div>
            <div class="admin-status-card"><div class="admin-kv-label">Chưa đồng bộ</div><div class="mt-1 text-2xl font-semibold text-warning-600">{{ number_format($missingFiles) }}</div></div>
            <div class="admin-status-card"><div class="admin-kv-label">File lỗi gần nhất</div><div class="mt-1 text-2xl font-semibold {{ $failedFiles > 0 ? 'text-danger-600' : 'text-gray-950 dark:text-white' }}">{{ number_format($failedFiles) }}</div></div>
            <div class="admin-status-card"><div class="admin-kv-label">Lần quét gần nhất</div><div class="mt-1 text-sm font-semibold">{{ $latestScan?->updated_at?->diffForHumans() ?? 'Chưa có' }}</div></div>
        </div>

        @if(! $isR2Enabled)
            <x-filament::section icon="heroicon-o-information-circle" icon-color="gray" heading="R2 đang tắt">
                <p class="text-sm text-gray-600 dark:text-gray-300">Quét media local vẫn hoạt động. Tải lên và di chuyển URL chỉ khả dụng sau khi R2 được cấu hình trong Cài đặt website.</p>
            </x-filament::section>
        @endif

        <x-filament::section heading="Tiến trình gần nhất" description="Tóm tắt tác vụ R2/CDN mới nhất.">
            @if(! $latestJob)
                <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">Chưa có tác vụ đồng bộ. Hãy quét media local để bắt đầu.</div>
            @else
                @php
                    $total = max(0, (int) $latestJob->total_files);
                    $processed = min($total, (int) $latestJob->synced_files + (int) $latestJob->failed_files);
                    $progress = $total > 0 ? min(100, (int) round(($processed / $total) * 100)) : (in_array($latestJob->status, ['completed', 'completed_with_errors']) ? 100 : 0);
                @endphp
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="font-semibold text-gray-950 dark:text-white">{{ $latestJob->name }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cập nhật {{ $latestJob->updated_at?->diffForHumans() }} · {{ number_format($total) }} file</div>
                        </div>
                        <x-admin.status-badge :state="match($latestJob->status) { 'completed' => 'READY', 'completed_with_errors' => 'WARNING', 'failed', 'cancelled' => 'FAILED', default => 'PROCESSING' }" :label="$statusLabels[$latestJob->status] ?? str($latestJob->status)->headline()" />
                    </div>

                    @if($total > 0)
                        <div>
                            <div class="mb-2 flex justify-between text-xs text-gray-500"><span>{{ number_format($processed) }} / {{ number_format($total) }} file</span><span>{{ $progress }}%</span></div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div class="h-full rounded-full {{ $latestJob->status === 'failed' ? 'bg-danger-500' : ($latestJob->status === 'completed_with_errors' ? 'bg-warning-500' : 'bg-primary-500') }}" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if($latestJob->error_message)
                        <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">{{ $latestJob->error_message }}</div>
                    @endif
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Lịch sử tác vụ" description="Các thao tác chạy lại hoặc hủy vẫn tuân theo quyền R2 hiện hành.">
            {{ $this->table }}
        </x-filament::section>

        <x-filament::section heading="Di chuyển URL" description="Luồng có tác động dữ liệu: chạy thử, xem kết quả, sau đó mới áp dụng." collapsible collapsed>
            <div class="rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300">
                Thay URL thật là thao tác nguy hiểm và không có hoàn tác tự động. Nút thao tác nằm trong menu “Di chuyển URL”, yêu cầu quyền <code>r2.sync</code>, xác nhận và có dry-run trước đó.
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
