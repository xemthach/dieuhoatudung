<x-filament-panels::page>
    @if($job)
    <div class="space-y-6">

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center">
                <div class="text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($job->total_rows) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tổng dòng đọc được</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center">
                <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ number_format($job->success_rows) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Dòng hợp lệ</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center">
                <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ number_format($job->failed_rows) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Dòng lỗi</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center">
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($job->created_rows) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sẽ tạo mới</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center">
                <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ number_format($job->updated_rows) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sẽ cập nhật</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center">
                <div class="text-sm font-mono font-bold text-gray-700 dark:text-gray-300 uppercase">{{ $job->mode }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Chế độ</div>
            </div>
        </div>

        {{-- Import Info --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Module:</span>
                    <span class="ml-1 font-medium text-gray-950 dark:text-white">
                        {{ \App\Services\DataTransfer\ModuleRegistry::modules()[$job->module] ?? $job->module }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">File:</span>
                    <span class="ml-1 font-medium text-gray-950 dark:text-white font-mono text-xs">{{ $job->file_name }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Định dạng:</span>
                    <span class="ml-1 font-medium text-gray-950 dark:text-white uppercase">{{ $job->file_type }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Matching key:</span>
                    <span class="ml-1 font-medium text-gray-950 dark:text-white font-mono">{{ $job->matching_key ?? 'id' }}</span>
                </div>
            </div>
        </div>

        {{-- Error Details --}}
        @if($job->error_report_json && count($job->error_report_json) > 0)
        <div class="rounded-xl bg-danger-50 dark:bg-danger-400/10 shadow-sm ring-1 ring-danger-200 dark:ring-danger-400/20">
            <div class="border-b border-danger-200 dark:border-danger-400/20 px-6 py-4">
                <h3 class="text-base font-semibold text-danger-700 dark:text-danger-400 flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                    Lỗi ({{ count($job->error_report_json) }} dòng)
                </h3>
            </div>
            <div class="overflow-x-auto max-h-60">
                <table class="w-full text-sm">
                    <thead class="bg-danger-100/50 dark:bg-danger-400/5">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-danger-600 dark:text-danger-400 w-20">Dòng</th>
                            <th class="px-4 py-2 text-left font-medium text-danger-600 dark:text-danger-400">Lỗi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-danger-200 dark:divide-danger-400/20">
                        @foreach(array_slice($job->error_report_json, 0, 20) as $error)
                        <tr>
                            <td class="px-4 py-2 text-danger-700 dark:text-danger-300 font-mono text-xs">{{ $error['row'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-danger-600 dark:text-danger-400 text-xs">
                                {{ implode(' | ', $error['errors'] ?? []) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Preview Data Table --}}
        @if($job->preview_data_json && count($job->preview_data_json) > 0)
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-table-cells class="h-5 w-5 text-info-600" />
                    Preview ({{ min(20, $job->total_rows) }} dòng đầu)
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 w-12">#</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 w-20">Action</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 w-20">Valid</th>
                            @if($job->column_mapping_json)
                                @foreach(array_slice($job->column_mapping_json, 0, 8) as $col)
                                <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $col }}</th>
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach($job->preview_data_json as $previewRow)
                        <tr class="{{ !empty($previewRow['errors']) ? 'bg-danger-50/50 dark:bg-danger-400/5' : '' }}">
                            <td class="px-3 py-2 text-gray-500 font-mono">{{ $previewRow['row_number'] }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $previewRow['action'] === 'create' ? 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' : '' }}
                                    {{ $previewRow['action'] === 'update' ? 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' : '' }}
                                    {{ $previewRow['action'] === 'skip' ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' : '' }}
                                ">
                                    {{ $previewRow['action'] }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                @if(empty($previewRow['errors']))
                                    <x-heroicon-o-check-circle class="h-4 w-4 text-success-500" />
                                @else
                                    <span class="text-danger-500" title="{{ implode(', ', $previewRow['errors']) }}">
                                        <x-heroicon-o-x-circle class="h-4 w-4" />
                                    </span>
                                @endif
                            </td>
                            @if($job->column_mapping_json)
                                @foreach(array_slice($job->column_mapping_json, 0, 8) as $col)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300 max-w-[200px] truncate">
                                    {{ \Illuminate\Support\Str::limit($previewRow['data'][$col] ?? '', 40) }}
                                </td>
                                @endforeach
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($job->total_rows > 20)
            <div class="px-6 py-3 border-t border-gray-200 dark:border-white/10 text-sm text-gray-500 dark:text-gray-400">
                Hiển thị 20 / {{ number_format($job->total_rows) }} dòng
            </div>
            @endif
        </div>
        @endif

    </div>
    @endif
</x-filament-panels::page>
