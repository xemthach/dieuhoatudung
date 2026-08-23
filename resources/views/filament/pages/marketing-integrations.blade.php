<x-filament-panels::page>
    @php
        $integrations = $health['integrations'] ?? [];
        $summary = $health['summary'] ?? [];
        $events = $health['recommended_events'] ?? [];
        $needsSetup = collect($integrations)->filter(fn ($integration) => ! ($integration['configured'] ?? false))->count();
        $criticalIssues = count($summary['critical_missing'] ?? []);
    @endphp

    <div class="space-y-6">
        @if (! empty($lastUploadResult))
            <x-filament::section>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="font-semibold">Lần gửi Google Ads gần nhất</span>
                    <span>Đã kiểm tra: {{ $lastUploadResult['checked'] ?? 0 }}</span>
                    <span>Đã gửi: {{ $lastUploadResult['uploaded'] ?? 0 }}</span>
                    <span>Lỗi: {{ $lastUploadResult['failed'] ?? 0 }}</span>
                    <span>Bỏ qua: {{ $lastUploadResult['skipped'] ?? 0 }}</span>
                </div>
            </x-filament::section>
        @endif

        <div class="admin-card-grid admin-card-grid-4">
            <div class="admin-status-card">
                <div class="admin-kv-label">Đã cấu hình</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $summary['configured_count'] ?? 0 }}/{{ $summary['total_count'] ?? count($integrations) }}
                </div>
            </div>

            <div class="admin-status-card">
                <div class="admin-kv-label">Cần thiết lập</div>
                <div class="mt-1 text-2xl font-semibold text-warning-600 dark:text-warning-400">
                    {{ $needsSetup }}
                </div>
            </div>

            <div class="admin-status-card">
                <div class="admin-kv-label">Vấn đề nghiêm trọng</div>
                <div @class(['mt-1 text-2xl font-semibold', 'text-danger-600 dark:text-danger-400' => $criticalIssues > 0, 'text-success-600 dark:text-success-400' => $criticalIssues === 0])>
                    {{ $criticalIssues }}
                </div>
            </div>

            <div class="admin-status-card">
                <div class="admin-kv-label">Sự kiện theo dõi</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ count($events) }}
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($integrations as $key => $integration)
                <x-filament::section>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold">{{ $integration['label'] ?? $key }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $integration['configured'] ?? false ? 'Kết nối đã sẵn sàng' : 'Cần bổ sung cấu hình' }}</p>
                        </div>
                        <x-admin.status-badge
                            :state="($integration['configured'] ?? false) ? 'READY' : (($integration['severity'] ?? null) === 'critical' ? 'ERROR' : 'NEEDS_CONFIGURATION')"
                        />
                    </div>

                    @if (! empty($integration['missing']))
                        <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                            <div class="font-medium">Thông tin còn thiếu</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-gray-600 dark:text-gray-300">
                                @foreach($integration['missing'] as $missing)<li>{{ $missing }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($integration['capabilities']))
                        <div class="mt-4">
                            <div class="text-sm font-medium">Khả năng hỗ trợ</div>
                            <ul class="mt-2 grid gap-1 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-2">
                                @foreach ($integration['capabilities'] as $capability)
                                    <li class="flex items-start gap-2"><span class="mt-1 text-success-500">✓</span><span>{{ str($capability)->replace('_', ' ')->headline() }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($integration['values']))
                        <details class="admin-technical-details mt-4">
                            <summary>Chi tiết kỹ thuật</summary>
                            <pre class="admin-code-block mt-2">{{ json_encode($integration['values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section>
            <h2 class="text-base font-semibold">Sự kiện chuyển đổi khuyến nghị</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($events as $event)
                    <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                        {{ str($event)->replace('_', ' ')->headline() }}
                    </span>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
