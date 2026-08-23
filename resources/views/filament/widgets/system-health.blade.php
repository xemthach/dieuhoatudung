<x-filament-widgets::widget>
    @php
        $labels = [
            'database' => 'Cơ sở dữ liệu',
            'cache' => 'Bộ nhớ đệm',
            'queue' => 'Hàng đợi',
            'storage' => 'Lưu trữ',
            'scheduler' => 'Lịch tác vụ',
            'worker' => 'AI Worker',
        ];
    @endphp

    <x-filament::section heading="Tình trạng hệ thống" description="Tóm tắt vận hành chỉ đọc. Chẩn đoán chi tiết nằm trong khu vực Vận hành.">
        <div class="admin-card-grid admin-card-grid-3">
            @foreach($health['components'] as $name => $healthComponent)
                <div class="admin-status-card">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $labels[$name] ?? str($name)->headline() }}</div>
                        <x-admin.status-badge :state="$healthComponent['state']" />
                    </div>
                    @if(isset($healthComponent['pending']) || isset($healthComponent['failed']))
                        <div class="mt-3 flex gap-4 text-xs text-gray-500 dark:text-gray-400">
                            @isset($healthComponent['pending'])<span>Đang chờ: <strong>{{ $healthComponent['pending'] }}</strong></span>@endisset
                            @isset($healthComponent['failed'])<span>Lịch sử lỗi: <strong>{{ $healthComponent['failed'] }}</strong></span>@endisset
                        </div>
                    @endif
                    @if(isset($healthComponent['desired']))
                        <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            Mong muốn: <strong>{{ $healthComponent['desired'] === 'DISABLED' ? 'Đã tắt' : $healthComponent['desired'] }}</strong>
                            <span aria-hidden="true">·</span>
                            Thực tế: <strong>{{ $healthComponent['actual'] ?? '-' }}</strong>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">Cập nhật: {{ \Carbon\Carbon::parse($health['generated_at'])->format('d/m/Y H:i:s') }}</div>
    </x-filament::section>
</x-filament-widgets::widget>
