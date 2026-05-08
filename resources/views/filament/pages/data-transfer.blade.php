<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Recent Exports --}}
            <div class="fi-wi-stats-overview-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5 text-success-600 dark:text-success-400" />
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Exports gần đây</span>
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ \App\Models\DataExportJob::where('created_at', '>=', now()->subDays(7))->count() }}
                </div>
                <div class="mt-1 text-xs text-gray-500">7 ngày qua</div>
            </div>

            {{-- Recent Imports --}}
            <div class="fi-wi-stats-overview-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Imports gần đây</span>
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ \App\Models\DataImportJob::where('created_at', '>=', now()->subDays(7))->count() }}
                </div>
                <div class="mt-1 text-xs text-gray-500">7 ngày qua</div>
            </div>

            {{-- Completed --}}
            <div class="fi-wi-stats-overview-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-success-600 dark:text-success-400" />
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Thành công</span>
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ \App\Models\DataImportJob::where('status', 'completed')->count() + \App\Models\DataExportJob::where('status', 'completed')->count() }}
                </div>
                <div class="mt-1 text-xs text-gray-500">Tổng jobs hoàn thành</div>
            </div>

            {{-- Failed --}}
            <div class="fi-wi-stats-overview-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <x-heroicon-o-x-circle class="h-5 w-5 text-danger-600 dark:text-danger-400" />
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Thất bại</span>
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ \App\Models\DataImportJob::where('status', 'failed')->count() + \App\Models\DataExportJob::where('status', 'failed')->count() }}
                </div>
                <div class="mt-1 text-xs text-gray-500">Tổng jobs lỗi</div>
            </div>
        </div>

        {{-- Recent Export Jobs --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5 text-success-600" />
                    Export Jobs gần đây
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">ID</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Module</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Định dạng</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Số dòng</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Trạng thái</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Người tạo</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Thời gian</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Tải về</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse(\App\Models\DataExportJob::latest()->take(10)->get() as $exportJob)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-3 text-gray-950 dark:text-white">#{{ $exportJob->id }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">
                                    {{ \App\Services\DataTransfer\ModuleRegistry::modules()[$exportJob->module] ?? $exportJob->module }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ strtoupper($exportJob->file_type) }}</td>
                            <td class="px-4 py-3 text-gray-950 dark:text-white">{{ number_format($exportJob->total_rows) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                    {{ $exportJob->status === 'completed' ? 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' : '' }}
                                    {{ $exportJob->status === 'failed' ? 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' : '' }}
                                    {{ $exportJob->status === 'processing' ? 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' : '' }}
                                    {{ $exportJob->status === 'pending' ? 'bg-gray-50 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400' : '' }}
                                ">
                                    {{ $exportJob->status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $exportJob->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $exportJob->created_at?->format('d/m H:i') }}</td>
                            <td class="px-4 py-3">
                                @if($exportJob->isDownloadable())
                                    <a href="{{ route('admin.export.download', $exportJob) }}"
                                       class="inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                        Tải
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                Chưa có export nào.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Import Jobs --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-primary-600" />
                    Import Jobs gần đây
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">ID</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Module</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">File</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Mode</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Tổng</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">OK</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Lỗi</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Trạng thái</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Thời gian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse(\App\Models\DataImportJob::latest()->take(10)->get() as $importJob)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-3 text-gray-950 dark:text-white">#{{ $importJob->id }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">
                                    {{ \App\Services\DataTransfer\ModuleRegistry::modules()[$importJob->module] ?? $importJob->module }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ \Illuminate\Support\Str::limit($importJob->file_name, 25) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    {{ strtoupper($importJob->mode) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-950 dark:text-white">{{ number_format($importJob->total_rows) }}</td>
                            <td class="px-4 py-3 text-success-600 dark:text-success-400 font-medium">{{ number_format($importJob->success_rows) }}</td>
                            <td class="px-4 py-3 text-danger-600 dark:text-danger-400 font-medium">{{ number_format($importJob->failed_rows) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                    {{ $importJob->status === 'completed' ? 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' : '' }}
                                    {{ $importJob->status === 'failed' ? 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' : '' }}
                                    {{ $importJob->status === 'previewing' ? 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' : '' }}
                                    {{ in_array($importJob->status, ['pending', 'validating', 'importing']) ? 'bg-info-50 text-info-700 dark:bg-info-400/10 dark:text-info-400' : '' }}
                                ">
                                    {{ $importJob->status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $importJob->created_at?->format('d/m H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                Chưa có import nào.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Module Field Groups Reference --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-information-circle class="h-5 w-5 text-info-600" />
                    Nhóm dữ liệu theo Module
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach(\App\Services\DataTransfer\ModuleRegistry::modules() as $moduleKey => $moduleName)
                    <div class="space-y-2">
                        <h4 class="font-semibold text-gray-950 dark:text-white">{{ $moduleName }}</h4>
                        @foreach(\App\Services\DataTransfer\ModuleRegistry::fieldGroups($moduleKey) as $groupKey => $group)
                        <div class="bg-gray-50 dark:bg-white/5 rounded-lg p-3">
                            <div class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-1">{{ $group['label'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ implode(', ', $group['fields']) }}
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
