<x-filament-panels::page>
    @php
        $latestJob = \App\Models\R2SyncJob::latest()->first();
        $isR2Enabled = setting('r2_storage.r2_enabled', false);
    @endphp

    {{-- System Status Inline --}}
    <div class="flex items-center justify-between px-5 py-3 bg-white border border-surface-200 rounded-xl shadow-sm dark:bg-surface-800 dark:border-surface-700">
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2.5">
                <span class="text-sm font-semibold text-surface-500 uppercase tracking-wider">Trạng thái R2:</span>
                @if($isR2Enabled)
                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-md text-xs font-bold bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20">
                        <div class="w-2 h-2 rounded-full bg-success-600 animate-pulse"></div>BẬT (ON)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-md text-xs font-bold bg-surface-100 text-surface-600 ring-1 ring-inset ring-surface-500/20">
                        <div class="w-2 h-2 rounded-full bg-surface-400"></div>TẮT (OFF)
                    </span>
                @endif
            </div>
            
            <div class="w-px h-6 bg-surface-200 dark:bg-surface-600"></div>
            
            <div class="flex items-center gap-2.5">
                <span class="text-sm font-semibold text-surface-500 uppercase tracking-wider">Mode:</span>
                <span class="text-sm font-bold {{ $isR2Enabled ? 'text-primary-600' : 'text-surface-700' }}">
                    {{ $isR2Enabled ? 'CDN Active' : 'Local Storage' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Action State Panel --}}
    <x-filament::card>
        @if(!$latestJob)
            <div class="text-center py-8">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary-50">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-8 w-8 text-primary-600" />
                </div>
                <h3 class="mt-4 text-xl font-bold text-surface-900">Chưa có dữ liệu đồng bộ</h3>
                <p class="mt-2 text-sm text-surface-500">Hệ thống chưa quét file local nào. Bấm nút Scan Local Media ở trên để bắt đầu.</p>
            </div>
        @else
            @php
                $progress = $latestJob->total_files > 0 ? min(100, round(($latestJob->synced_files / $latestJob->total_files) * 100)) : 0;
            @endphp
            
            <div class="flex flex-col gap-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-surface-900 flex items-center gap-2">
                            Tiến trình gần nhất: {{ $latestJob->name }}
                            <x-filament::badge :color="match($latestJob->status) {
                                'completed' => 'success',
                                'failed', 'cancelled' => 'danger',
                                default => 'warning',
                            }">{{ strtoupper($latestJob->status) }}</x-filament::badge>
                        </h3>
                        <p class="text-sm text-surface-500 mt-1 flex items-center gap-4">
                            <span><x-filament::icon icon="heroicon-o-clock" class="inline w-4 h-4 mr-1"/> Cập nhật: {{ $latestJob->updated_at->diffForHumans() }}</span>
                            @if($latestJob->mode === 'upload_only')
                                <span><x-filament::icon icon="heroicon-o-document" class="inline w-4 h-4 mr-1"/> Phát hiện: <strong class="text-surface-700">{{ number_format($latestJob->total_files) }} file</strong></span>
                            @endif
                        </p>
                    </div>
                </div>

                @if($latestJob->mode === 'upload_only' && in_array($latestJob->status, ['syncing', 'replacing', 'completed']))
                    <div class="w-full bg-surface-100 rounded-full h-3 dark:bg-surface-700 overflow-hidden">
                        <div class="h-3 rounded-full transition-all duration-500 {{ $progress === 100 ? 'bg-success-500' : 'bg-primary-500 relative overflow-hidden' }}" style="width: {{ $progress }}%">
                            @if($progress < 100 && $progress > 0)
                                <div class="absolute inset-0 bg-white/20" style="animation: shimmer 2s infinite linear; background-image: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);"></div>
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-between text-xs text-surface-500 font-semibold">
                        <span>{{ number_format($latestJob->synced_files) }} file đã upload</span>
                        <span class="{{ $progress === 100 ? 'text-success-600' : 'text-primary-600' }}">{{ $progress }}%</span>
                    </div>
                @endif
                
                @if($latestJob->mode === 'upload_only' && $latestJob->status === 'completed')
                    <div class="rounded-lg bg-success-50 p-4 border border-success-200">
                        <div class="flex">
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-success-500" />
                            <div class="ml-3">
                                <h3 class="text-sm font-bold text-success-800">Scan & Upload hoàn tất!</h3>
                                <p class="mt-1 text-sm text-success-700">Tất cả file đã sẵn sàng trên R2. Hãy tiếp tục bấm <strong>Dry Run Replace URLs</strong> để kiểm tra thay thế DB an toàn.</p>
                            </div>
                        </div>
                    </div>
                @elseif($latestJob->status === 'failed')
                    <div class="rounded-lg bg-danger-50 p-4 border border-danger-200 flex justify-between items-center">
                        <div class="flex">
                            <x-filament::icon icon="heroicon-o-x-circle" class="h-6 w-6 text-danger-500 mt-0.5" />
                            <div class="ml-3">
                                <h3 class="text-sm font-bold text-danger-800">Tiến trình thất bại</h3>
                                <p class="mt-1 text-sm text-danger-700">{{ $latestJob->error_message ?? 'Đã xảy ra lỗi không xác định. Vui lòng xem logs hệ thống.' }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-filament::card>

    <style>
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(200%); }
        }
    </style>

    {{ $this->table }}
</x-filament-panels::page>
